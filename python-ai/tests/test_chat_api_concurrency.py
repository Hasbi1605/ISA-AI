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

    response = asyncio.run(chat_api.chat_stream(req))
    chunks = asyncio.run(_collect_async_iter(response.body_iterator))

    assert response.media_type == "text/event-stream"
    assert chunks == ["ok"]
