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
