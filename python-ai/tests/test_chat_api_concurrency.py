import asyncio
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app import chat_api


class _DummyStreamingResponse:
    def __init__(self, body, media_type=None):
        self.body_iterator = body
        self.media_type = media_type


async def _collect_async_iter(it):
    out = []
    async for x in it:
        out.append(x)
    return out


def _collect_iter(it):
    return list(it)


def _collect_stream(it):
    if hasattr(it, "__aiter__"):
        return asyncio.run(_collect_async_iter(it))
    return _collect_iter(it)


def _make_fake_http_request(request_id: str = "test-rid-001"):
    """Buat mock FastAPI Request dengan header X-Request-ID."""
    class _FakeHeaders:
        def __init__(self, data):
            self._data = data

        def get(self, key, default=None):
            return self._data.get(key, default)

    class _FakeRequest:
        def __init__(self):
            self.headers = _FakeHeaders({"X-Request-ID": request_id})

    return _FakeRequest()


def test_chat_api_parallel_doc_and_web(monkeypatch):
    class Req(chat_api.ChatRequest):
        pass

    req = Req(
        messages=[{"role": "user", "content": "cek terbaru"}],
        document_filenames=["doc.pdf"],
        user_id="u1",
        force_web_search=False,
        explicit_web_request=True,
    )

    def fake_policy_helpers():
        return (
            lambda q: True,
            lambda **kwargs: (True, "DOC_WEB_EXPLICIT", "high"),
            lambda *args, **kwargs: {"search_context": "WEB"},
        )

    async def fake_stream_with_sources(messages, sources):
        yield "ok"

    def fake_streamers():
        return (lambda *args, **kwargs: iter(["fallback"]), fake_stream_with_sources)

    def fake_doc_helpers():
        def search_relevant_chunks(*args, **kwargs):
            return ([{"filename": "doc.pdf", "content": "DOC"}], True)

        def build_rag_prompt(query, chunks, web_context=""):
            return f"PROMPT::{web_context}", [{"filename": "doc.pdf"}]

        return search_relevant_chunks, build_rag_prompt

    monkeypatch.setattr(chat_api, "StreamingResponse", _DummyStreamingResponse)
    monkeypatch.setattr(chat_api, "_get_rag_policy_helpers", fake_policy_helpers)
    monkeypatch.setattr(chat_api, "_get_chat_streamers", fake_streamers)
    monkeypatch.setattr(chat_api, "_get_rag_document_helpers", fake_doc_helpers)

    response = asyncio.run(chat_api.chat_stream(req, _make_fake_http_request()))
    chunks = _collect_stream(response.body_iterator)

    assert response.media_type == "text/event-stream"
    assert chunks == ["ok"]


def test_chat_api_no_prefetch_web_on_empty_doc_fallback(monkeypatch):
    req = chat_api.ChatRequest(
        messages=[{"role": "user", "content": "cek terbaru"}],
        document_filenames=["doc.pdf"],
        user_id="u1",
        force_web_search=False,
        explicit_web_request=True,
    )

    policy_calls = {"count": 0}

    def fake_policy_helpers():
        def _ctx(*args, **kwargs):
            policy_calls["count"] += 1
            return {"search_context": "WEB"}

        return (lambda q: True, lambda **kwargs: (True, "DOC_WEB_EXPLICIT", "high"), _ctx)

    def fake_streamers():
        def _fallback(*args, **kwargs):
            yield "fallback"

        async def _with_sources(*args, **kwargs):
            yield "ok"

        return _fallback, _with_sources

    def fake_doc_helpers():
        def search_relevant_chunks(*args, **kwargs):
            return ([], True)

        def build_rag_prompt(*args, **kwargs):
            return "PROMPT", []

        return search_relevant_chunks, build_rag_prompt

    monkeypatch.setattr(chat_api, "StreamingResponse", _DummyStreamingResponse)
    monkeypatch.setattr(chat_api, "_get_rag_policy_helpers", fake_policy_helpers)
    monkeypatch.setattr(chat_api, "_get_chat_streamers", fake_streamers)
    monkeypatch.setattr(chat_api, "_get_rag_document_helpers", fake_doc_helpers)

    response = asyncio.run(chat_api.chat_stream(req, _make_fake_http_request()))
    chunks = _collect_stream(response.body_iterator)

    assert chunks == ["fallback"]
    assert policy_calls["count"] == 0


def test_chat_api_no_prefetch_web_on_retrieval_failure(monkeypatch):
    req = chat_api.ChatRequest(
        messages=[{"role": "user", "content": "cek terbaru"}],
        document_filenames=["doc.pdf"],
        user_id="u1",
        force_web_search=False,
        explicit_web_request=True,
    )

    policy_calls = {"count": 0}

    def fake_policy_helpers():
        def _ctx(*args, **kwargs):
            policy_calls["count"] += 1
            return {"search_context": "WEB"}

        return (lambda q: True, lambda **kwargs: (True, "DOC_WEB_EXPLICIT", "high"), _ctx)

    def fake_streamers():
        def _fallback(*args, **kwargs):
            yield "fallback"

        async def _with_sources(*args, **kwargs):
            yield "ok"

        return _fallback, _with_sources

    def fake_doc_helpers():
        def search_relevant_chunks(*args, **kwargs):
            return ([], False)

        def build_rag_prompt(*args, **kwargs):
            return "PROMPT", []

        return search_relevant_chunks, build_rag_prompt

    monkeypatch.setattr(chat_api, "StreamingResponse", _DummyStreamingResponse)
    monkeypatch.setattr(chat_api, "_get_rag_policy_helpers", fake_policy_helpers)
    monkeypatch.setattr(chat_api, "_get_chat_streamers", fake_streamers)
    monkeypatch.setattr(chat_api, "_get_rag_document_helpers", fake_doc_helpers)

    response = asyncio.run(chat_api.chat_stream(req, _make_fake_http_request()))
    chunks = _collect_stream(response.body_iterator)

    assert chunks == ["fallback"]
    assert policy_calls["count"] == 0

