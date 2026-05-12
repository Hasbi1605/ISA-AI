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
