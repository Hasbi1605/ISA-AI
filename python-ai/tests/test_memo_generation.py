import os
import sys
from io import BytesIO

from docx import Document
from fastapi.testclient import TestClient

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.documents_api import app
from app.services.memo_generation import MemoDraft
from app.services.memo_generation import generate_memo_docx


def test_generate_memo_docx_builds_valid_word_document():
    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Rapat Koordinasi Mingguan",
        context="Bahas progres layanan ISTA AI.",
        text_generator=lambda prompt: "Mohon setiap unit menyiapkan laporan progres.\n- Bawa data pendukung.",
    )

    document = Document(BytesIO(draft.content))
    paragraphs = [paragraph.text for paragraph in document.paragraphs if paragraph.text]

    assert draft.filename == "rapat-koordinasi-mingguan.docx"
    assert paragraphs[0] == "MEMO INTERNAL"
    assert "Perihal: Rapat Koordinasi Mingguan" in paragraphs
    assert "Mohon setiap unit menyiapkan laporan progres." in paragraphs
    assert "Bawa data pendukung." in paragraphs
    assert "Rapat Koordinasi Mingguan" in draft.searchable_text


def test_generate_memo_docx_rejects_unknown_type():
    try:
        generate_memo_docx(
            memo_type="surat_bebas",
            title="Judul",
            context="Konteks",
            text_generator=lambda prompt: "Isi",
        )
    except ValueError as exc:
        assert "Jenis memo" in str(exc)
    else:
        raise AssertionError("Expected ValueError")


def test_generate_memo_endpoint_requires_token():
    client = TestClient(app)

    response = client.post("/api/memos/generate-body", json={
        "memo_type": "memo_internal",
        "title": "Judul",
        "context": "Konteks",
    })

    assert response.status_code == 401


def test_generate_memo_endpoint_handles_unicode_searchable_text(monkeypatch):
    def fake_generate_memo_docx(memo_type, title, context):
        return MemoDraft(
            filename="memo-unicode.docx",
            content=b"docx-bytes",
            searchable_text="Paragraf awal\n- Poin penting • arahan — selesai",
        )

    monkeypatch.setattr("app.routers.memos.generate_memo_docx", fake_generate_memo_docx)
    client = TestClient(app)

    response = client.post(
        "/api/memos/generate-body",
        headers={"Authorization": "Bearer your_internal_api_secret"},
        json={
            "memo_type": "memo_internal",
            "title": "Judul Unicode",
            "context": "Konteks",
        },
    )

    assert response.status_code == 200
    assert response.content == b"docx-bytes"
    assert "X-Memo-Searchable-Text" not in response.headers
    encoded = response.headers["X-Memo-Searchable-Text-B64"]
    assert isinstance(encoded.encode("latin-1"), bytes)
