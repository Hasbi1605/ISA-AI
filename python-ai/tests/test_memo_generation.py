import os
import sys
from io import BytesIO

from docx import Document
from docx.oxml.ns import qn
from docx.shared import Inches
from fastapi.testclient import TestClient

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))
os.environ["AI_SERVICE_TOKEN"] = "test_internal_api_secret"

from app.documents_api import app
from app.services.memo_generation import MemoDraft
from app.services.memo_generation import build_memo_prompt
from app.services.memo_generation import generate_memo_docx


def _all_document_text(document):
    texts = [paragraph.text for paragraph in document.paragraphs if paragraph.text]
    for table in document.tables:
        for row in table.rows:
            for cell in row.cells:
                if cell.text:
                    texts.append(cell.text)
    for section in document.sections:
        texts.extend(paragraph.text for paragraph in section.footer.paragraphs if paragraph.text)
    return "\n".join(texts)


def _find_table_containing(document, text):
    for table in document.tables:
        for row in table.rows:
            for cell in row.cells:
                if cell.text == text:
                    return table
    raise AssertionError(f"Table containing {text!r} not found")


def test_generate_memo_docx_builds_official_memorandum_document():
    configuration = {
        "number": "M-02/I-Yog/IT.02/04/2026",
        "recipient": "Kepala Pusat Pengembangan dan Layanan Sistem Informasi",
        "sender": "Kepala Istana Kepresidenan Yogyakarta",
        "subject": "Penyampaian Nama PIC Aplikasi Virtual Meeting",
        "date": "3 April 2026",
        "basis": "Menindaklanjuti memorandum Bapak nomor M-01/PPLSI/IT.02.00/04/2026.",
        "content": "Sampaikan nama, NIP, pangkat/gol., dan jabatan PIC.",
        "closing": "Atas perhatian dan kerja sama Bapak, kami ucapkan terima kasih.",
        "signatory": "Deni Mulyana",
        "carbon_copy": "Kepala Subbagian Tata Usaha, Istana Kepresidenan Yogyakarta, Sekretariat Presiden",
        "page_size": "folio",
    }

    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Penyampaian Nama PIC Aplikasi Virtual Meeting",
        context="Bahas PIC aplikasi virtual meeting.",
        text_generator=lambda prompt: "Mohon setiap unit menyiapkan laporan progres.\n- Bawa data pendukung.",
        configuration=configuration,
    )

    document = Document(BytesIO(draft.content))
    paragraphs = [paragraph.text for paragraph in document.paragraphs if paragraph.text]

    assert draft.filename == "penyampaian-nama-pic-aplikasi-virtual-meeting.docx"
    assert paragraphs[:3] == [
        "KEMENTERIAN SEKRETARIAT NEGARA RI",
        "SEKRETARIAT PRESIDEN",
        "ISTANA KEPRESIDENAN YOGYAKARTA",
    ]
    assert "MEMORANDUM" in paragraphs
    assert "Nomor M-02/I-Yog/IT.02/04/2026" in paragraphs
    assert "Mohon setiap unit menyiapkan laporan progres." in paragraphs
    assert any("Bawa data pendukung." in paragraph for paragraph in paragraphs)
    assert "Atas perhatian dan kerja sama Bapak, kami ucapkan terima kasih." in paragraphs
    assert "Deni Mulyana" in _all_document_text(document)
    assert "Dokumen ini telah ditandatangani secara elektronik" in document.sections[0].footer.paragraphs[0].text
    assert document.sections[0].page_height == Inches(14)
    assert draft.page_size == "folio"
    assert document.styles["Normal"].font.name == "Arial"
    assert document.paragraphs[0].runs[0].font.name == "Arial"
    assert document.tables[0].cell(0, 2).text == configuration["recipient"]
    assert document.tables[0].cell(2, 2).text == configuration["subject"]
    qr_table = _find_table_containing(document, "QR\nTTE")
    assert qr_table.cell(0, 1).text == "QR\nTTE"
    assert qr_table.cell(1, 0).text == "Deni Mulyana"
    table_indent = qr_table._tbl.tblPr.find(qn("w:tblInd"))
    assert table_indent is not None
    assert int(table_indent.get(qn("w:w"))) >= 5600
    assert "Penyampaian Nama PIC Aplikasi Virtual Meeting" in draft.searchable_text


def test_generate_memo_docx_does_not_add_default_closing_when_blank():
    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Permohonan Data Rapat",
        context="Mohon dibuatkan memo permohonan data rapat.",
        text_generator=lambda prompt: "Mohon unit terkait menyiapkan data rapat paling lambat 10 Mei 2026.",
        configuration={
            "number": "M-04/I-Yog/UM.01/05/2026",
            "recipient": "Kepala Subbagian Tata Usaha",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "subject": "Permohonan Data Rapat",
            "date": "6 Mei 2026",
            "content": "Minta data rapat paling lambat 10 Mei 2026.",
            "signatory": "Deni Mulyana",
            "page_size": "letter",
        },
    )

    document = Document(BytesIO(draft.content))
    paragraphs = [paragraph.text for paragraph in document.paragraphs if paragraph.text]

    assert "Demikian, mohon arahan lebih lanjut." not in paragraphs
    assert "Demikian, mohon arahan lebih lanjut." not in draft.searchable_text
    assert "Deni Mulyana" in _all_document_text(document)


def test_generate_memo_docx_auto_page_size_uses_generated_body_length():
    long_body = "\n".join(
        [
            "1. " + "Koordinasi lintas unit perlu dilakukan secara tertib dan terdokumentasi. " * 4,
            "2. " + "Setiap unit diminta menyiapkan data pendukung dan batas waktu pelaksanaan. " * 4,
            "3. " + "Hasil pembahasan disampaikan kembali sebagai bahan tindak lanjut pimpinan. " * 4,
        ]
    )

    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Koordinasi Kegiatan",
        context="Buat memo koordinasi kegiatan.",
        text_generator=lambda prompt: long_body,
        configuration={
            "number": "M-05/I-Yog/UM.01/05/2026",
            "recipient": "Kepala Unit Terkait",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "subject": "Koordinasi Kegiatan",
            "date": "7 Mei 2026",
            "content": "Buat memo koordinasi kegiatan.",
            "signatory": "Deni Mulyana",
            "page_size": "auto",
            "page_size_mode": "auto",
        },
    )

    document = Document(BytesIO(draft.content))

    assert draft.page_size == "folio"
    assert document.sections[0].page_height == Inches(14)


def test_build_memo_prompt_keeps_ai_inside_body_scope():
    prompt = build_memo_prompt(
        memo_type="memo_internal",
        title="Permohonan Penempatan Jabatan Fungsional Pranata Komputer",
        context="Mohon penempatan pegawai tetap di Istana Kepresidenan Yogyakarta.",
        configuration={
            "number": "M-09/I-Yog/KP/03/2025",
            "recipient": "Deputi Bidang Administrasi dan Pengelolaan Istana",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "date": "17 Maret 2025",
            "content": "Tulis 3 poin bernomor.",
        },
    )

    assert "Nomor: M-09/I-Yog/KP/03/2025" in prompt
    assert "Yth.: Deputi Bidang Administrasi dan Pengelolaan Istana" in prompt
    assert "Tulis hanya isi utama memo" in prompt
    assert "tanpa kop, nomor, Yth., Dari, Hal, Tanggal" in prompt
    assert "Arahan tambahan:" in prompt
    assert "kecuali user mengisinya di field Penutup" in prompt


def test_build_memo_prompt_prioritizes_revision_instruction_and_current_context():
    prompt = build_memo_prompt(
        memo_type="memo_internal",
        title="Penyampaian Keberatan Untuk Keperluan tersebut",
        context="Isi memo saat ini:\nTembusan:\n1. Kepala A\n2. Kepala B\n\nInstruksi revisi wajib diterapkan:\ntambahkan tembusan nomor 3, Kepala C",
        configuration={
            "number": "M/2312/22D/409L/YK",
            "recipient": "Kepala Komdigi",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "date": "6 Mei 2026",
            "content": "Isi lama dari konfigurasi.",
            "revision_instruction": "tambahkan tembusan nomor 3, Kepala C",
        },
    )

    assert "Isi memo saat ini:" in prompt
    assert "Isi lama dari konfigurasi." not in prompt
    assert "Instruksi revisi wajib diterapkan:" in prompt
    assert "tambahkan tembusan nomor 3, Kepala C" in prompt
    assert "Jangan meregenerasi seluruh memo" in prompt
    assert "ubah hanya bagian yang disebut" in prompt
    assert "baseline, uji, skenario evaluasi, dan auto format" in prompt
    assert "Jangan menulis blok Tembusan" in prompt


def test_generate_memo_docx_sanitizes_evaluation_artifacts_and_forbidden_sections():
    closing = "Demikian disampaikan, atas perhatian dan kerja samanya diucapkan terima kasih."
    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Revisi Ubah Penutup",
        context="Revisi memo evaluasi.",
        text_generator=lambda prompt: (
            "Sehubungan dengan persiapan kegiatan, dimohon melakukan koordinasi.\n"
            "Memo ini juga sebagai baseline untuk uji revisi penutup.\n"
            f"{closing}\n"
            f"{closing}\n"
            "Tembusan:\n"
            "Kepala Bagian Keamanan"
        ),
        configuration={
            "number": "EVAL-33/IST/YK/05/2026",
            "recipient": "Kepala Bagian Protokol",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "subject": "Revisi Ubah Penutup",
            "date": "7 Mei 2026",
            "closing": closing,
            "signatory": "Deni Mulyana",
            "carbon_copy": "Kepala Bagian Keamanan",
            "page_size": "letter",
        },
    )

    document = Document(BytesIO(draft.content))
    all_text = _all_document_text(document)

    assert "baseline" not in all_text.lower()
    assert "uji revisi" not in all_text.lower()
    assert all_text.count(closing) == 1
    assert all_text.count("Tembusan:") == 1
    assert all_text.count("Kepala Bagian Keamanan") == 1


def test_generate_memo_docx_removes_carbon_copy_from_body_but_keeps_configuration_copy():
    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Revisi Kapitalisasi Tembusan",
        context="Kapitalisasi tembusan.",
        text_generator=lambda prompt: (
            "Menindaklanjuti kebutuhan distribusi informasi, diminta agar:\n"
            "1. Informasi disampaikan kepada unit terkait.\n"
            "2. Setiap unit memastikan tindak lanjut.\n"
            "Tembusan:\n"
            "Kementrian Polisi\n"
            "Kemenkue\n"
            "Purbaya Yudhi Sadewa"
        ),
        configuration={
            "number": "EVAL-40/IST/YK/05/2026",
            "recipient": "Kepala Bagian Administrasi",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "subject": "Revisi Kapitalisasi Tembusan",
            "date": "7 Mei 2026",
            "signatory": "Deni Mulyana",
            "carbon_copy": "Kementrian Polisi\nKemenkue\nPurbaya Yudhi Sadewa",
            "page_size": "folio",
        },
    )

    document = Document(BytesIO(draft.content))
    all_text = _all_document_text(document)

    assert all_text.count("Tembusan:") == 1
    assert all_text.count("Kementrian Polisi") == 1
    assert all_text.count("Kemenkue") == 1
    assert all_text.count("Purbaya Yudhi Sadewa") == 1


def test_generate_memo_docx_formats_pic_data_as_key_value_table():
    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Penyampaian Data Pegawai",
        context="Data PIC pegawai.",
        text_generator=lambda prompt: (
            "Menindaklanjuti proses pendataan pegawai, dengan ini disampaikan data sebagai berikut:\n"
            "1. Nama pegawai yang benar: Muhammad Hasbi Ash Shiddiqi\n"
            "2. NIP: 231210013\n"
            "3. Jabatan: Pengadministrasi Perkantoran"
        ),
        configuration={
            "number": "EVAL-38/IST/YK/05/2026",
            "recipient": "Kepala Bagian SDM",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "subject": "Penyampaian Data Pegawai",
            "date": "7 Mei 2026",
            "signatory": "Deni Mulyana",
            "page_size": "letter",
        },
    )

    document = Document(BytesIO(draft.content))
    data_table = _find_table_containing(document, "nama")

    assert data_table.cell(0, 0).text == "nama"
    assert data_table.cell(0, 1).text == ":"
    assert data_table.cell(0, 2).text == "Muhammad Hasbi Ash Shiddiqi"
    assert data_table.cell(1, 0).text == "NIP"
    assert data_table.cell(1, 2).text == "231210013"
    assert data_table.cell(2, 0).text == "jabatan"
    assert data_table.cell(2, 2).text == "Pengadministrasi Perkantoran"


def test_generate_memo_docx_starts_signature_on_new_page_for_long_folio_with_carbon_copy():
    long_body = "\n".join(
        [
            f"{index}. "
            + "Koordinasi dan penataan layanan administrasi internal perlu dilakukan secara bertahap. " * 7
            for index in range(1, 7)
        ]
    )

    draft = generate_memo_docx(
        memo_type="memo_internal",
        title="Memo Panjang Format Folio",
        context="Buat memo panjang format folio.",
        text_generator=lambda prompt: long_body,
        configuration={
            "number": "EVAL-25/IST/YK/05/2026",
            "recipient": "Kepala Pusat Pengembangan Layanan",
            "sender": "Kepala Istana Kepresidenan Yogyakarta",
            "subject": "Memo Panjang Format Folio",
            "date": "7 Mei 2026",
            "signatory": "Deni Mulyana",
            "carbon_copy": "Kepala Biro Perencanaan\nKepala Bagian Administrasi\nKoordinator Layanan",
            "page_size": "folio",
        },
    )

    document = Document(BytesIO(draft.content))
    page_breaks = document._element.xpath(".//w:br[@w:type='page']")

    assert page_breaks


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
    def fake_generate_memo_docx(memo_type, title, context, configuration=None):
        assert configuration == {"number": "M-01/I-Yog/IT.02/05/2026"}

        return MemoDraft(
            filename="memo-unicode.docx",
            content=b"docx-bytes",
            searchable_text="Paragraf awal\n- Poin penting • arahan — selesai",
            page_size="letter",
        )

    monkeypatch.setattr("app.routers.memos.generate_memo_docx", fake_generate_memo_docx)
    client = TestClient(app)

    response = client.post(
        "/api/memos/generate-body",
        headers={"Authorization": "Bearer test_internal_api_secret"},
        json={
            "memo_type": "memo_internal",
            "title": "Judul Unicode",
            "context": "Konteks",
            "configuration": {"number": "M-01/I-Yog/IT.02/05/2026"},
        },
    )

    assert response.status_code == 200
    assert response.content == b"docx-bytes"
    assert "X-Memo-Searchable-Text" not in response.headers
    encoded = response.headers["X-Memo-Searchable-Text-B64"]
    assert isinstance(encoded.encode("latin-1"), bytes)
    assert response.headers["X-Memo-Page-Size"] == "letter"
