import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.services.rag_config import MAX_EMBEDDING_DIM
from app.services import rag_embeddings
from app.services.rag_embeddings import (
    BEDROCK_RUNTIME_URL_TEMPLATE,
    GITHUB_MODELS_BASE_URL,
    BedrockTitanEmbeddings,
    GithubOpenAIEmbeddings,
    clear_embedding_cache,
)


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


class _FakeBedrockResponse:
    def __init__(self, embedding=None, status_error=None):
        self._embedding = embedding or [0.6, 0.7]
        self._status_error = status_error

    def raise_for_status(self):
        if self._status_error:
            raise self._status_error

    def json(self):
        return {"embedding": self._embedding}


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


def test_bedrock_titan_embeddings_invokes_bedrock_runtime_and_pads(monkeypatch):
    captured = {}

    def fake_post(url, **kwargs):
        captured["url"] = url
        captured.update(kwargs)
        return _FakeBedrockResponse([0.8, 0.9, 1.0])

    monkeypatch.setattr(rag_embeddings.requests, "post", fake_post)

    embeddings = BedrockTitanEmbeddings(
        model="amazon.titan-embed-text-v2:0",
        api_key="test-bedrock-token",
        region="us-east-1",
        dimensions=1024,
        normalize=True,
        timeout=12,
    )

    result = embeddings.embed_documents(["baris satu\nbaris dua"])

    assert len(result) == 1
    assert result[0][:3] == [0.8, 0.9, 1.0]
    assert len(result[0]) == MAX_EMBEDDING_DIM
    assert captured["url"] == BEDROCK_RUNTIME_URL_TEMPLATE.format(
        region="us-east-1",
        model_id="amazon.titan-embed-text-v2%3A0",
    )
    assert captured["headers"]["Authorization"] == "Bearer test-bedrock-token"
    assert captured["json"] == {
        "inputText": "baris satu baris dua",
        "dimensions": 1024,
        "normalize": True,
    }
    assert captured["timeout"] == 12


def test_get_embeddings_falls_back_to_bedrock_titan_when_github_fails(monkeypatch):
    class FailingGithubEmbedding:
        def __init__(self, **_kwargs):
            pass

        def embed_query(self, _text):
            raise RuntimeError("github limited")

    class FakeBedrockEmbedding:
        def __init__(self, **kwargs):
            self.kwargs = kwargs

        def embed_query(self, _text):
            return [0.1] * MAX_EMBEDDING_DIM

    monkeypatch.setattr(rag_embeddings, "GithubOpenAIEmbeddings", FailingGithubEmbedding)
    monkeypatch.setattr(rag_embeddings, "BedrockTitanEmbeddings", FakeBedrockEmbedding)
    monkeypatch.setattr(rag_embeddings, "get_env", lambda name, default=None: {
        "GITHUB_TOKEN": "github-token",
        "AWS_BEARER_TOKEN_BEDROCK": "bedrock-token",
        "AWS_BEDROCK_REGION": default,
    }.get(name, default))
    monkeypatch.setattr(
        rag_embeddings,
        "EMBEDDING_MODELS",
        [
            {
                "name": "github",
                "provider": "github",
                "model": "text-embedding-3-large",
                "api_key_env": "GITHUB_TOKEN",
                "tpm_limit": 500000,
                "dimensions": 3072,
            },
            {
                "name": "titan",
                "provider": "bedrock_titan",
                "model": "amazon.titan-embed-text-v2:0",
                "api_key_env": "AWS_BEARER_TOKEN_BEDROCK",
                "region": "us-east-1",
                "dimensions": 1024,
                "normalize": True,
                "timeout": 30,
            },
        ],
    )

    embeddings, provider, index = rag_embeddings.get_embeddings_with_fallback()

    assert provider == "titan"
    assert index == 1
    assert isinstance(embeddings, FakeBedrockEmbedding)
    assert embeddings.kwargs["api_key"] == "bedrock-token"
    assert embeddings.kwargs["dimensions"] == 1024


# ---------------------------------------------------------------------------
# Cache embedding provider (quick win #191)
# ---------------------------------------------------------------------------

def _make_fake_models(probe_call_counter: dict):
    """Helper: buat EMBEDDING_MODELS fake dengan counter probe calls."""
    class FakeEmbedding(GithubOpenAIEmbeddings):
        def __init__(self, **kwargs):
            super().__init__(client=_FakeClient([[0.1, 0.2]]), **kwargs)

        def embed_query(self, text):
            probe_call_counter["count"] += 1
            return [0.1] * MAX_EMBEDDING_DIM

    return FakeEmbedding, [{
        "name": "cached-model",
        "provider": "github",
        "model": "text-embedding-3-small",
        "api_key_env": "GITHUB_TOKEN",
        "tpm_limit": 500000,
        "dimensions": 1536,
    }]


def test_embedding_cache_skips_probe_on_second_call(monkeypatch):
    """Probe embed_query('test') tidak dipanggil ulang jika cache masih valid."""
    clear_embedding_cache()
    probe_calls = {"count": 0}
    FakeEmbedding, fake_models = _make_fake_models(probe_calls)

    monkeypatch.setattr(rag_embeddings, "GithubOpenAIEmbeddings", FakeEmbedding)
    monkeypatch.setattr(rag_embeddings, "get_env", lambda name, default=None: "test-token")
    monkeypatch.setattr(rag_embeddings, "EMBEDDING_MODELS", fake_models)
    monkeypatch.setattr(rag_embeddings, "_EMBEDDING_CACHE_TTL", 300)

    # Panggil pertama — probe harus dipanggil
    emb1, provider1, idx1 = rag_embeddings.get_embeddings_with_fallback()
    assert probe_calls["count"] == 1
    assert provider1 == "cached-model"

    # Panggil kedua — probe tidak boleh dipanggil lagi (cache hit)
    emb2, provider2, idx2 = rag_embeddings.get_embeddings_with_fallback()
    assert probe_calls["count"] == 1, "Probe tidak boleh dipanggil ulang saat cache valid"
    assert provider2 == "cached-model"
    assert idx2 == idx1

    clear_embedding_cache()


def test_embedding_cache_disabled_when_ttl_zero(monkeypatch):
    """Jika EMBEDDING_CACHE_TTL=0, probe selalu dipanggil."""
    clear_embedding_cache()
    probe_calls = {"count": 0}
    FakeEmbedding, fake_models = _make_fake_models(probe_calls)

    monkeypatch.setattr(rag_embeddings, "GithubOpenAIEmbeddings", FakeEmbedding)
    monkeypatch.setattr(rag_embeddings, "get_env", lambda name, default=None: "test-token")
    monkeypatch.setattr(rag_embeddings, "EMBEDDING_MODELS", fake_models)
    monkeypatch.setattr(rag_embeddings, "_EMBEDDING_CACHE_TTL", 0)

    rag_embeddings.get_embeddings_with_fallback()
    rag_embeddings.get_embeddings_with_fallback()

    assert probe_calls["count"] == 2, "Dengan TTL=0, probe harus dipanggil setiap kali"

    clear_embedding_cache()


def test_embedding_cache_invalidated_on_probe_failure(monkeypatch):
    """Jika probe gagal, cache entry harus dihapus dan tidak dipakai."""
    clear_embedding_cache()
    call_count = {"count": 0}

    class FailingEmbedding(GithubOpenAIEmbeddings):
        def __init__(self, **kwargs):
            super().__init__(client=_FakeClient([[0.1]]), **kwargs)

        def embed_query(self, text):
            call_count["count"] += 1
            raise RuntimeError("probe gagal")

    monkeypatch.setattr(rag_embeddings, "GithubOpenAIEmbeddings", FailingEmbedding)
    monkeypatch.setattr(rag_embeddings, "get_env", lambda name, default=None: "test-token")
    monkeypatch.setattr(rag_embeddings, "EMBEDDING_MODELS", [{
        "name": "failing-model",
        "provider": "github",
        "model": "text-embedding-3-small",
        "api_key_env": "GITHUB_TOKEN",
        "tpm_limit": 500000,
        "dimensions": 1536,
    }])
    monkeypatch.setattr(rag_embeddings, "_EMBEDDING_CACHE_TTL", 300)

    result, provider, idx = rag_embeddings.get_embeddings_with_fallback()

    assert result is None
    assert provider == "none"
    # Cache tidak boleh menyimpan entry yang gagal
    assert len(rag_embeddings._embedding_cache) == 0

    clear_embedding_cache()


def test_clear_embedding_cache_removes_all_entries(monkeypatch):
    """clear_embedding_cache() harus menghapus semua entry."""
    clear_embedding_cache()
    probe_calls = {"count": 0}
    FakeEmbedding, fake_models = _make_fake_models(probe_calls)

    monkeypatch.setattr(rag_embeddings, "GithubOpenAIEmbeddings", FakeEmbedding)
    monkeypatch.setattr(rag_embeddings, "get_env", lambda name, default=None: "test-token")
    monkeypatch.setattr(rag_embeddings, "EMBEDDING_MODELS", fake_models)
    monkeypatch.setattr(rag_embeddings, "_EMBEDDING_CACHE_TTL", 300)

    rag_embeddings.get_embeddings_with_fallback()
    assert len(rag_embeddings._embedding_cache) == 1

    clear_embedding_cache()
    assert len(rag_embeddings._embedding_cache) == 0
