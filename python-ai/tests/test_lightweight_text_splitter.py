import importlib
import os
import sys

from langchain_core.documents import Document

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.services.lightweight_text_splitter import LightweightRecursiveTextSplitter


def _count_tokens(text: str) -> int:
    return len(text.split())


def test_splitter_creates_multiple_chunks_with_overlap():
    splitter = LightweightRecursiveTextSplitter(
        chunk_size=6,
        chunk_overlap=2,
        length_function=_count_tokens,
        add_start_index=True,
        separators=["\n\n", "\n", ". ", " ", ""],
    )

    docs = [
        Document(
            page_content="alpha beta gamma delta epsilon zeta eta theta iota kappa",
            metadata={"filename": "uji.txt"},
        )
    ]

    chunks = splitter.split_documents(docs)

    assert len(chunks) >= 2
    assert all(chunk.metadata["filename"] == "uji.txt" for chunk in chunks)
    assert all("start_index" in chunk.metadata for chunk in chunks)
    assert chunks[0].page_content != chunks[1].page_content
    assert "epsilon zeta" in chunks[1].page_content


def test_rag_ingest_import_stays_lightweight():
    before = set(sys.modules)

    importlib.import_module("app.services.rag_ingest")

    after = set(sys.modules) - before

    assert not any(name.startswith("torch") for name in after)
    assert not any(name.startswith("spacy") for name in after)
    assert not any(name.startswith("langchain_text_splitters") for name in after)
