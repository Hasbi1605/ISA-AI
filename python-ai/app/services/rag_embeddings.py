import hashlib
import logging
import threading
import time
from typing import List, Tuple, Optional
from urllib.parse import quote

import requests
import tiktoken
from openai import OpenAI
from langchain_core.embeddings import Embeddings

from app.env_utils import get_env, get_env_int
from app.services.rag_config import (
    EMBEDDING_MODELS,
    MAX_EMBEDDING_DIM,
)

logger = logging.getLogger(__name__)

GITHUB_MODELS_BASE_URL = "https://models.github.ai/inference"
BEDROCK_RUNTIME_URL_TEMPLATE = "https://bedrock-runtime.{region}.amazonaws.com/model/{model_id}/invoke"

# TTL cache embedding provider (detik). Default 300s = 5 menit.
# Set ke 0 untuk disable cache.
_EMBEDDING_CACHE_TTL = max(0, get_env_int("EMBEDDING_CACHE_TTL", 300))

# Cache: key = (model_index, api_key_hash) → (embeddings_instance, provider_name, model_index, cached_at)
_embedding_cache: dict = {}
_embedding_cache_lock = threading.Lock()

try:
    TIKTOKEN_ENCODER = tiktoken.get_encoding("cl100k_base")
    logger.info("✅ Tiktoken encoder initialized (cl100k_base)")
except Exception as e:
    logger.error(f"❌ Failed to initialize tiktoken: {e}")
    TIKTOKEN_ENCODER = None


class GithubOpenAIEmbeddings(Embeddings):
    """Lightweight OpenAI-compatible embeddings wrapper for Chroma/LangChain."""

    def __init__(
        self,
        model: str,
        openai_api_key: str,
        openai_api_base: str,
        dimensions: int,
        client: Optional[OpenAI] = None,
    ) -> None:
        self.model = model
        self.dimensions = dimensions
        self.client = client or OpenAI(
            api_key=openai_api_key,
            base_url=openai_api_base,
        )

    @staticmethod
    def _sanitize_texts(texts: List[str]) -> List[str]:
        return [(text or "").replace("\n", " ") for text in texts]

    @staticmethod
    def _normalize_embedding(embedding: List[float]) -> List[float]:
        vector = list(embedding)
        if len(vector) < MAX_EMBEDDING_DIM:
            vector.extend([0.0] * (MAX_EMBEDDING_DIM - len(vector)))
        return vector[:MAX_EMBEDDING_DIM]

    def embed_documents(self, texts: List[str]) -> List[List[float]]:
        if not texts:
            return []

        response = self.client.embeddings.create(
            model=self.model,
            input=self._sanitize_texts(texts),
            dimensions=self.dimensions,
        )
        return [self._normalize_embedding(item.embedding) for item in response.data]

    def embed_query(self, text: str) -> List[float]:
        vectors = self.embed_documents([text])
        return vectors[0] if vectors else []


class BedrockTitanEmbeddings(Embeddings):
    """Amazon Titan Text Embeddings V2 wrapper for Bedrock Runtime."""

    def __init__(
        self,
        model: str,
        api_key: str,
        region: str,
        dimensions: int,
        normalize: bool = True,
        timeout: int = 30,
    ) -> None:
        self.model = model
        self.api_key = api_key
        self.region = region
        self.dimensions = dimensions
        self.normalize = normalize
        self.timeout = timeout
        encoded_model_id = quote(model, safe="")
        self.url = BEDROCK_RUNTIME_URL_TEMPLATE.format(
            region=region,
            model_id=encoded_model_id,
        )

    @staticmethod
    def _sanitize_text(text: str) -> str:
        return (text or "").replace("\n", " ")

    @staticmethod
    def _normalize_embedding(embedding: List[float]) -> List[float]:
        vector = list(embedding)
        if len(vector) < MAX_EMBEDDING_DIM:
            vector.extend([0.0] * (MAX_EMBEDDING_DIM - len(vector)))
        return vector[:MAX_EMBEDDING_DIM]

    def _embed_one(self, text: str) -> List[float]:
        response = requests.post(
            self.url,
            headers={
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            json={
                "inputText": self._sanitize_text(text),
                "dimensions": self.dimensions,
                "normalize": self.normalize,
            },
            timeout=self.timeout,
        )
        response.raise_for_status()
        data = response.json()
        embedding = data.get("embedding")
        if not isinstance(embedding, list):
            raise ValueError("Bedrock Titan embedding response does not include an embedding vector")
        return self._normalize_embedding(embedding)

    def embed_documents(self, texts: List[str]) -> List[List[float]]:
        if not texts:
            return []
        return [self._embed_one(text) for text in texts]

    def embed_query(self, text: str) -> List[float]:
        vectors = self.embed_documents([text])
        return vectors[0] if vectors else []


def count_tokens(text: str) -> int:
    if TIKTOKEN_ENCODER is None:
        return len(text) // 4

    try:
        return len(TIKTOKEN_ENCODER.encode(text))
    except Exception as e:
        logger.warning(f"⚠️ Token counting failed: {e}, using fallback estimate")
        return len(text) // 4


def _make_cache_key(model_index: int, api_key: str) -> tuple:
    """Buat cache key dari model index dan hash API key (tidak menyimpan key asli)."""
    key_hash = hashlib.sha256(api_key.encode()).hexdigest()[:16]
    return (model_index, key_hash)


def _get_cached_embeddings(model_index: int, api_key: str) -> Optional[Tuple["Embeddings", str, int]]:
    """Return cached embedding instance jika masih valid, atau None."""
    if _EMBEDDING_CACHE_TTL <= 0:
        return None
    cache_key = _make_cache_key(model_index, api_key)
    with _embedding_cache_lock:
        entry = _embedding_cache.get(cache_key)
        if entry is None:
            return None
        embeddings, provider_name, idx, cached_at = entry
        if time.monotonic() - cached_at > _EMBEDDING_CACHE_TTL:
            del _embedding_cache[cache_key]
            return None
        return embeddings, provider_name, idx


def _set_cached_embeddings(model_index: int, api_key: str, embeddings: "Embeddings", provider_name: str, idx: int) -> None:
    """Simpan embedding instance ke cache."""
    if _EMBEDDING_CACHE_TTL <= 0:
        return
    cache_key = _make_cache_key(model_index, api_key)
    with _embedding_cache_lock:
        _embedding_cache[cache_key] = (embeddings, provider_name, idx, time.monotonic())


def clear_embedding_cache() -> None:
    """Hapus seluruh cache embedding. Berguna untuk testing."""
    with _embedding_cache_lock:
        _embedding_cache.clear()


def get_embeddings_with_fallback(model_index: int = 0) -> Tuple[Optional[Embeddings], str, int]:
    for idx in range(model_index, len(EMBEDDING_MODELS)):
        model_config = EMBEDDING_MODELS[idx]
        api_key = get_env(model_config["api_key_env"])

        if not api_key:
            logger.warning(f"⚠️ {model_config['name']}: API key tidak ditemukan")
            continue

        # Cek cache sebelum probe — skip embed_query("test") jika masih valid
        cached = _get_cached_embeddings(idx, api_key)
        if cached is not None:
            embeddings, provider_name, cached_idx = cached
            logger.debug("📦 Embedding cache hit: %s (idx=%d)", provider_name, cached_idx)
            return embeddings, provider_name, cached_idx

        try:
            if model_config["provider"] == "github":
                embeddings = GithubOpenAIEmbeddings(
                    model=model_config["model"],
                    openai_api_base=GITHUB_MODELS_BASE_URL,
                    openai_api_key=api_key,
                    dimensions=model_config.get("dimensions", MAX_EMBEDDING_DIM),
                )
                _ = embeddings.embed_query("test")
                logger.info(f"✅ Menggunakan {model_config['name']} (TPM: {model_config['tpm_limit']:,}, Dim: {MAX_EMBEDDING_DIM})")
                _set_cached_embeddings(idx, api_key, embeddings, model_config["name"], idx)
                return embeddings, model_config["name"], idx

            if model_config["provider"] == "bedrock_titan":
                embeddings = BedrockTitanEmbeddings(
                    model=model_config["model"],
                    api_key=api_key,
                    region=model_config.get("region", get_env("AWS_BEDROCK_REGION", "us-east-1")),
                    dimensions=model_config.get("dimensions", 1024),
                    normalize=model_config.get("normalize", True),
                    timeout=model_config.get("timeout", 30),
                )
                _ = embeddings.embed_query("test")
                logger.info(f"✅ Menggunakan {model_config['name']} (Dim native: {model_config.get('dimensions', 1024)}, Dim index: {MAX_EMBEDDING_DIM})")
                _set_cached_embeddings(idx, api_key, embeddings, model_config["name"], idx)
                return embeddings, model_config["name"], idx

        except Exception as e:
            error_msg = str(e)
            logger.warning(f"⚠️ {model_config['name']} gagal: {error_msg}")
            # Pastikan entry cache yang mungkin stale dihapus
            cache_key = _make_cache_key(idx, api_key)
            with _embedding_cache_lock:
                _embedding_cache.pop(cache_key, None)

    logger.error("❌ Semua embedding provider gagal atau tidak tersedia")
    return None, "none", -1
