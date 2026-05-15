import os
import subprocess
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.retrieval_runner import _parse_search_payload, run_retrieval_search


def test_parse_search_payload_uses_last_valid_json_line():
    payload = _parse_search_payload(
        "log line\n"
        "{\"ignored\": true}\n"
        "{\"success\": true, \"chunks\": [{\"filename\": \"doc.pdf\"}]}\n"
    )

    assert payload == {"success": True, "chunks": [{"filename": "doc.pdf"}]}


def test_run_retrieval_search_returns_success_payload(monkeypatch):
    monkeypatch.setenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", "1")
    def fake_run(*args, **kwargs):
        return subprocess.CompletedProcess(
            args=args[0],
            returncode=0,
            stdout="info\n{\"success\": true, \"chunks\": [{\"filename\": \"doc.pdf\", \"content\": \"abc\"}]}\n",
            stderr="",
        )

    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_run)

    chunks, success = run_retrieval_search("apa isi", ["doc.pdf"], top_k=3, user_id="42")

    assert success is True
    assert chunks == [{"filename": "doc.pdf", "content": "abc"}]


def test_run_retrieval_search_returns_false_when_payload_missing(monkeypatch):
    monkeypatch.setenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", "1")
    def fake_run(*args, **kwargs):
        return subprocess.CompletedProcess(
            args=args[0],
            returncode=1,
            stdout="not-json\n",
            stderr="traceback detail",
        )

    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_run)

    chunks, success = run_retrieval_search("apa isi", ["doc.pdf"], top_k=3, user_id="42")

    assert success is False
    assert chunks == []


def test_run_retrieval_search_uses_inprocess_by_default(monkeypatch):
    monkeypatch.delenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", raising=False)

    called = {"inprocess": False, "subprocess": False}

    def fake_inprocess(*args, **kwargs):
        called["inprocess"] = True
        return ([{"filename": "doc.pdf"}], True)

    def fake_subprocess(*args, **kwargs):
        called["subprocess"] = True
        raise AssertionError("subprocess should not run")

    monkeypatch.setattr("app.retrieval_runner._run_retrieval_search_inprocess", fake_inprocess)
    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_subprocess)

    chunks, success = run_retrieval_search("apa isi", ["doc.pdf"], top_k=3, user_id="42")

    assert success is True
    assert chunks == [{"filename": "doc.pdf"}]
    assert called["inprocess"] is True
    assert called["subprocess"] is False


def test_run_retrieval_search_fallback_to_subprocess_when_inprocess_fails(monkeypatch):
    monkeypatch.delenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", raising=False)

    def fake_inprocess(*args, **kwargs):
        raise RuntimeError("boom")

    def fake_run(*args, **kwargs):
        return subprocess.CompletedProcess(
            args=args[0],
            returncode=0,
            stdout='{"success": true, "chunks": [{"filename": "doc.pdf", "content": "abc"}]}\n',
            stderr="",
        )

    monkeypatch.setattr("app.retrieval_runner._run_retrieval_search_inprocess", fake_inprocess)
    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_run)

    chunks, success = run_retrieval_search("apa isi", ["doc.pdf"], top_k=3, user_id="42")

    assert success is True
    assert chunks == [{"filename": "doc.pdf", "content": "abc"}]


def test_run_retrieval_search_uses_subprocess_when_opted_in(monkeypatch):
    monkeypatch.setenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", "1")

    def fake_inprocess(*args, **kwargs):
        raise AssertionError("inprocess should not run")

    def fake_run(*args, **kwargs):
        return subprocess.CompletedProcess(
            args=args[0],
            returncode=0,
            stdout='{"success": true, "chunks": []}\n',
            stderr="",
        )

    monkeypatch.setattr("app.retrieval_runner._run_retrieval_search_inprocess", fake_inprocess)
    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_run)

    chunks, success = run_retrieval_search("apa isi", ["doc.pdf"], top_k=3, user_id="42")

    assert success is True
    assert chunks == []


def test_subprocess_retrieval_passes_document_ids_as_json_arg(monkeypatch):
    """Subprocess path must forward document_ids_json so the retrieval layer
    can filter by document_id for new-style Chroma chunks."""
    monkeypatch.setenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", "1")

    captured_args: list = []

    def fake_run(*args, **kwargs):
        captured_args.extend(args[0])
        return subprocess.CompletedProcess(
            args=args[0],
            returncode=0,
            stdout='{"success": true, "chunks": [{"filename": "report.pdf", "content": "data"}]}\n',
            stderr="",
        )

    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_run)

    chunks, success = run_retrieval_search(
        "laporan keuangan",
        ["report.pdf"],
        top_k=5,
        user_id="7",
        document_ids=["42", "99"],
    )

    assert success is True
    assert chunks == [{"filename": "report.pdf", "content": "data"}]

    # The document_ids_json argument must be present in the subprocess call.
    import json as _json
    doc_ids_arg = None
    for arg in captured_args:
        try:
            parsed = _json.loads(arg)
            if isinstance(parsed, list) and all(isinstance(x, str) for x in parsed):
                doc_ids_arg = parsed
        except (ValueError, TypeError):
            continue

    assert doc_ids_arg == ["42", "99"], f"document_ids not forwarded to subprocess: {captured_args}"


def test_subprocess_retrieval_passes_empty_document_ids_when_none(monkeypatch):
    """When no document_ids are provided, subprocess receives an empty JSON list."""
    monkeypatch.setenv("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", "1")

    captured_args: list = []

    def fake_run(*args, **kwargs):
        captured_args.extend(args[0])
        return subprocess.CompletedProcess(
            args=args[0],
            returncode=0,
            stdout='{"success": true, "chunks": []}\n',
            stderr="",
        )

    monkeypatch.setattr("app.retrieval_runner.subprocess.run", fake_run)

    run_retrieval_search("query tanpa dokumen", filenames=None, top_k=3, user_id="1", document_ids=None)

    import json as _json
    # The last positional argument must be a JSON-encoded empty list.
    last_non_flag = [a for a in captured_args if not a.startswith("-")]
    doc_ids_arg = _json.loads(last_non_flag[-1])
    assert doc_ids_arg == [], f"Expected empty list, got: {last_non_flag[-1]}"
