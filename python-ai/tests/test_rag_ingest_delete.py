import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))


def test_delete_document_vectors_requires_user_id():
    from app.services.rag_ingest import delete_document_vectors

    success, message = delete_document_vectors("agenda.pdf")

    assert success is False
    assert "user_id is required" in message


def test_delete_document_vectors_scopes_filename_by_user(monkeypatch):
    from app.services import rag_ingest

    calls = {"vector": [], "parent": []}

    class FakeCollection:
        def delete(self, where):
            calls["parent"].append(where)

    class FakeChroma:
        def __init__(self, collection_name, **kwargs):
            self.collection_name = collection_name
            self._collection = FakeCollection()

        def delete(self, where):
            calls["vector"].append(where)

    monkeypatch.setattr(rag_ingest, "get_embeddings_with_fallback", lambda: (object(), "fake-provider", 0))
    monkeypatch.setattr(rag_ingest, "Chroma", FakeChroma)

    success, message = rag_ingest.delete_document_vectors("agenda.pdf", user_id="42")

    assert success is True
    assert "agenda.pdf" in message
    assert calls["vector"] == [{
        "$and": [
            {"filename": "agenda.pdf"},
            {"user_id": "42"},
        ],
    }]
    assert calls["parent"] == [{
        "$and": [
            {"filename": "agenda.pdf"},
            {"user_id": "42"},
            {"chunk_type": "parent"},
        ],
    }]


def test_process_document_returns_false_on_partial_batch_failure(monkeypatch, tmp_path):
    """
    Regression for H2: when at least one embedding batch fails during ingest,
    process_document must return (False, ...) — not (True, ...).
    Previously the function always returned True regardless of failed_chunks.
    """
    from langchain_core.documents import Document as LCDoc

    # Fake embeddings that return a plausible embedding vector.
    class FakeEmbeddings:
        def embed_documents(self, texts):
            return [[0.1] * 3072 for _ in texts]

        def embed_query(self, text):
            return [0.1] * 3072

    # Fake Chroma whose add_documents always raises, simulating every batch
    # failing at the embedding provider level.
    class FakeChromaAlwaysFail:
        def __init__(self, *a, **kw):
            pass

        def add_documents(self, *a, **kw):
            raise Exception("Fake provider error — batch rejected")

        def delete(self, *a, **kw):
            pass

        @property
        def _collection(self):
            return None

    # Patch get_embeddings_with_fallback.
    monkeypatch.setattr(
        "app.services.rag_ingest.get_embeddings_with_fallback",
        lambda *args, **kwargs: (FakeEmbeddings(), "fake-provider", 0),
    )

    # Patch the lightweight loader (imported as _load_documents_lightweight in rag_ingest).
    monkeypatch.setattr(
        "app.services.rag_ingest._load_documents_lightweight",
        lambda *args, **kwargs: [LCDoc(page_content="chunk one"), LCDoc(page_content="chunk two")],
    )

    # Patch delete_document_vectors (called on re-ingest) to be a no-op.
    monkeypatch.setattr(
        "app.services.rag_ingest.delete_document_vectors",
        lambda *args, **kwargs: (True, "deleted"),
    )

    # Replace the Chroma name in rag_ingest's module namespace so both
    # the main vectorstore and the PDR parent store use the failing fake.
    monkeypatch.setattr("app.services.rag_ingest.Chroma", FakeChromaAlwaysFail)

    # Minimal temp file — content doesn't matter because the loader is patched.
    test_file = tmp_path / "sample.txt"
    test_file.write_text("placeholder", encoding="utf-8")

    from app.services.rag_ingest import process_document

    success, message = process_document(
        str(test_file),
        "sample.txt",
        user_id="user-test-42",
        document_id="99",
    )

    # H2 fix: partial failure must return (False, ...).
    assert success is False, f"Expected False but got True with message: {message}"
    # The message should mention chunks failing.
    assert any(kw in message.lower() for kw in ("chunk", "gagal", "partial", "fail", "ingest"))
