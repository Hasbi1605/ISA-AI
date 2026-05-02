import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.services.rag_config import MAX_EMBEDDING_DIM
from app.services import rag_embeddings
from app.services.rag_embeddings import GITHUB_MODELS_BASE_URL, GithubOpenAIEmbeddings


class _FakeResponseItem:
    def __init__(self, embedding):
        self.embedding = embedding


class _FakeResponse:
    def __init__(self, embeddings):
        self.data = [_FakeResponseItem(embedding) for embedding in embeddings]


class _FakeEmbeddingsAPI:
    def __init__(self, embeddings):
        self._embeddings = embeddings
        self.calls = []

    def create(self, **kwargs):
        self.calls.append(kwargs)
        return _FakeResponse(self._embeddings)


class _FakeClient:
    def __init__(self, embeddings):
        self.embeddings = _FakeEmbeddingsAPI(embeddings)


class _FakeOpenAI:
    def __init__(self, api_key, base_url):
        self.api_key = api_key
        self.base_url = base_url
        self.embeddings = _FakeEmbeddingsAPI([[0.1, 0.2]])


def test_embed_documents_pads_vectors_and_sanitizes_input():
    client = _FakeClient([[0.1, 0.2]])
    embeddings = GithubOpenAIEmbeddings(
        model="text-embedding-3-large",
        openai_api_key="test",
        openai_api_base="https://models.github.ai/inference",
        dimensions=MAX_EMBEDDING_DIM,
        client=client,
    )

    result = embeddings.embed_documents(["baris satu\nbaris dua"])

    assert len(result) == 1
    assert len(result[0]) == MAX_EMBEDDING_DIM
    assert result[0][:2] == [0.1, 0.2]
    assert client.embeddings.calls[0]["input"] == ["baris satu baris dua"]
    assert client.embeddings.calls[0]["dimensions"] == MAX_EMBEDDING_DIM


def test_embed_query_returns_single_vector():
    client = _FakeClient([[0.3, 0.4, 0.5]])
    embeddings = GithubOpenAIEmbeddings(
        model="text-embedding-3-large",
        openai_api_key="test",
        openai_api_base="https://models.github.ai/inference",
        dimensions=MAX_EMBEDDING_DIM,
        client=client,
    )

    result = embeddings.embed_query("halo")

    assert len(result) == MAX_EMBEDDING_DIM
    assert result[:3] == [0.3, 0.4, 0.5]


def test_embed_documents_handles_empty_input():
    client = _FakeClient([[0.1]])
    embeddings = GithubOpenAIEmbeddings(
        model="text-embedding-3-large",
        openai_api_key="test",
        openai_api_base="https://models.github.ai/inference",
        dimensions=MAX_EMBEDDING_DIM,
        client=client,
    )

    assert embeddings.embed_documents([]) == []
    assert client.embeddings.calls == []


def test_embeddings_client_uses_github_models_endpoint(monkeypatch):
    captured = {}

    def fake_openai(*, api_key, base_url):
        captured["api_key"] = api_key
        captured["base_url"] = base_url
        return _FakeOpenAI(api_key, base_url)

    monkeypatch.setattr("app.services.rag_embeddings.OpenAI", fake_openai)

    embeddings = GithubOpenAIEmbeddings(
        model="text-embedding-3-large",
        openai_api_key="test",
        openai_api_base=GITHUB_MODELS_BASE_URL,
        dimensions=MAX_EMBEDDING_DIM,
    )

    assert embeddings.client.base_url == GITHUB_MODELS_BASE_URL
    assert captured["base_url"] == GITHUB_MODELS_BASE_URL
    assert captured["api_key"] == "test"


def test_get_embeddings_uses_model_native_dimension_before_padding(monkeypatch):
    captured = {}

    class FakeEmbedding(GithubOpenAIEmbeddings):
        def __init__(self, **kwargs):
            captured.update(kwargs)
            super().__init__(client=_FakeClient([[0.1, 0.2]]), **kwargs)

    monkeypatch.setattr(rag_embeddings, "GithubOpenAIEmbeddings", FakeEmbedding)
    monkeypatch.setattr(rag_embeddings, "get_env", lambda name: "test-token")
    monkeypatch.setattr(
        rag_embeddings,
        "EMBEDDING_MODELS",
        [{
            "name": "small",
            "provider": "github",
            "model": "text-embedding-3-small",
            "api_key_env": "GITHUB_TOKEN",
            "tpm_limit": 500000,
            "dimensions": 1536,
        }],
    )

    embeddings, provider, index = rag_embeddings.get_embeddings_with_fallback()

    assert provider == "small"
    assert index == 0
    assert captured["dimensions"] == 1536
    assert len(embeddings.embed_query("halo")) == MAX_EMBEDDING_DIM
