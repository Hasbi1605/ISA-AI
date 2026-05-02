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
