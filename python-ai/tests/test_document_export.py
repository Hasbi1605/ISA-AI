import os
import sys
from io import BytesIO

from docx import Document as DocxDocument
from fastapi.testclient import TestClient
from openpyxl import load_workbook

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.documents_api import app
from app.services.document_export import export_content


client = TestClient(app)
AUTH_HEADERS = {"Authorization": "Bearer your_internal_api_secret"}


def _sample_html() -> str:
    return """
    <article>
      <h2>Ringkasan</h2>
      <p>Ini adalah jawaban contoh.</p>
      <table>
        <tr><th>Nama</th><th>Nilai</th></tr>
        <tr><td>A</td><td>10</td></tr>
        <tr><td>B</td><td>20</td></tr>
      </table>
    </article>
    """


def test_export_content_creates_pdf_docx_xlsx_and_csv():
    html = _sample_html()

    pdf = export_content(html, "pdf", "jawaban-ai")
    assert pdf.filename == "jawaban-ai.pdf"
    assert pdf.mime_type == "application/pdf"
    assert pdf.content.startswith(b"%PDF")

    docx = export_content(html, "docx", "jawaban-ai")
    assert docx.filename == "jawaban-ai.docx"
    assert docx.mime_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
    docx_document = DocxDocument(BytesIO(docx.content))
    paragraphs = [paragraph.text for paragraph in docx_document.paragraphs if paragraph.text.strip()]
    assert any("Jawaban Ai" in paragraph or "jawaban ai" in paragraph.lower() for paragraph in paragraphs)
    assert any("Ini adalah jawaban contoh." in paragraph for paragraph in paragraphs)

    xlsx = export_content(html, "xlsx", "jawaban-ai")
    assert xlsx.filename == "jawaban-ai.xlsx"
    workbook = load_workbook(BytesIO(xlsx.content))
    sheet = workbook.active
    assert sheet["A1"].value == "Nama"
    assert sheet["B2"].value == "10"

    csv_export = export_content(html, "csv", "jawaban-ai")
    assert csv_export.filename == "jawaban-ai.csv"
    assert b"Nama" in csv_export.content
    assert b"10" in csv_export.content


def test_documents_export_route_returns_binary_download():
    response = client.post(
        "/api/documents/export",
        headers=AUTH_HEADERS,
        json={
            "content_html": _sample_html(),
            "target_format": "pdf",
            "file_name": "jawaban-ai",
        },
    )

    assert response.status_code == 200
    assert response.headers["content-type"].startswith("application/pdf")
    assert response.headers["content-disposition"].startswith('attachment; filename="jawaban-ai.pdf"')
    assert response.content.startswith(b"%PDF")


def test_documents_extract_tables_route_reads_upload():
    html = _sample_html()
    pdf_artifact = export_content(html, "pdf", "sample-table")

    response = client.post(
        "/api/documents/extract-tables",
        headers=AUTH_HEADERS,
        files={"file": ("sample-table.pdf", pdf_artifact.content, "application/pdf")},
    )

    assert response.status_code == 200
    payload = response.json()
    assert payload["status"] == "success"
    assert payload["tables"]
    first_table = payload["tables"][0]
    assert first_table["header"] == ["Nama", "Nilai"]
    assert first_table["rows"][0] == ["A", "10"]
