import logging
from typing import List, Tuple, Optional

import tiktoken
from openai import OpenAI
from langchain_core.embeddings import Embeddings

from app.env_utils import get_env
from app.services.rag_config import (
    EMBEDDING_MODELS,
    MAX_EMBEDDING_DIM,
)

logger = logging.getLogger(__name__)

GITHUB_MODELS_BASE_URL = "https://models.github.ai/inference"

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


def count_tokens(text: str) -> int:
    if TIKTOKEN_ENCODER is None:
        return len(text) // 4

    try:
        return len(TIKTOKEN_ENCODER.encode(text))
    except Exception as e:
        logger.warning(f"⚠️ Token counting failed: {e}, using fallback estimate")
        return len(text) // 4


def get_embeddings_with_fallback(model_index: int = 0) -> Tuple[Optional[Embeddings], str, int]:
    for idx in range(model_index, len(EMBEDDING_MODELS)):
        model_config = EMBEDDING_MODELS[idx]
        api_key = get_env(model_config["api_key_env"])

        if not api_key:
            logger.warning(f"⚠️ {model_config['name']}: API key tidak ditemukan")
            continue

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
                return embeddings, model_config["name"], idx

        except Exception as e:
            error_msg = str(e)
            logger.warning(f"⚠️ {model_config['name']} gagal: {error_msg}")

    logger.error("❌ Semua embedding provider gagal! Total kapasitas: 2M TPM habis atau tidak tersedia")
    return None, "none", -1
