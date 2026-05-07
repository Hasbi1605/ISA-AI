from __future__ import annotations

import re
from dataclasses import dataclass
from io import BytesIO
from typing import Any, Callable, Mapping

from docx import Document
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_ROW_HEIGHT_RULE, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


SUPPORTED_MEMO_TYPES = {
    "memo_internal": "Memo Internal",
    "nota_dinas": "Nota Dinas",
    "arahan": "Arahan",
}

HEADER_LINES = [
    "KEMENTERIAN SEKRETARIAT NEGARA RI",
    "SEKRETARIAT PRESIDEN",
    "ISTANA KEPRESIDENAN YOGYAKARTA",
]

FOOTER_NOTICE = (
    "Dokumen ini telah ditandatangani secara elektronik menggunakan sertifikat elektronik\n"
    "yang diterbitkan oleh Balai Sertifikasi Elektronik (BSrE)."
)


@dataclass(slots=True)
class MemoDraft:
    filename: str
    content: bytes
    searchable_text: str
    page_size: str


def normalize_memo_type(memo_type: str) -> str:
    normalized = (memo_type or "").strip().lower().replace("-", "_")
    if normalized not in SUPPORTED_MEMO_TYPES:
        raise ValueError("Jenis memo tidak didukung.")
    return normalized


def build_memo_prompt(
    memo_type: str,
    title: str,
    context: str,
    configuration: Mapping[str, Any] | None = None,
) -> str:
    label = SUPPORTED_MEMO_TYPES[normalize_memo_type(memo_type)]
    config = _normalize_configuration(configuration, title, context)
    content_source = context.strip() if config["revision_instruction"] else (config["content"] or context.strip())
    revision_section = (
        "Instruksi revisi wajib diterapkan:\n"
        f"{config['revision_instruction']}\n\n"
        if config["revision_instruction"]
        else ""
    )
    closing_rule = (
        "- Jangan menulis kalimat penutup akhir karena bagian penutup sudah disediakan konfigurasi.\n"
        if config["closing"]
        else "- Jangan menulis kalimat penutup akhir kecuali user mengisinya di field Penutup.\n"
    )
    revision_rules = (
        "- Karena ini revisi, pertahankan kalimat, urutan, nomor butir, dan informasi yang tidak diminta berubah secara eksplisit.\n"
        "- Jangan meregenerasi seluruh memo; ubah hanya bagian yang disebut dalam instruksi revisi.\n"
        if config["revision_instruction"]
        else ""
    )

    return (
        "Tulis isi memorandum resmi dalam Bahasa Indonesia dengan gaya naskah dinas.\n"
        f"Jenis: {label}\n"
        f"Nomor: {config['number']}\n"
        f"Yth.: {config['recipient']}\n"
        f"Dari: {config['sender']}\n"
        f"Hal: {config['subject']}\n"
        f"Tanggal: {config['date']}\n\n"
        "Konteks/dasar:\n"
        f"{config['basis'] or '-'}\n\n"
        "Isi atau poin wajib:\n"
        f"{content_source}\n\n"
        f"{revision_section}"
        "Arahan tambahan:\n"
        f"{config['additional_instruction'] or '-'}\n\n"
        "Aturan keluaran:\n"
        "- Tulis hanya isi utama memo, tanpa kop, nomor, Yth., Dari, Hal, Tanggal, tanda tangan, tembusan, atau footer.\n"
        "- Gunakan paragraf formal yang singkat, jelas, dan mengikuti contoh memorandum manual.\n"
        "- Jika ada beberapa butir keputusan/permohonan, gunakan daftar bernomor 1., 2., 3.\n"
        "- Awali dengan dasar atau tindak lanjut bila konteks menyediakannya.\n"
        f"{revision_rules}"
        "- Jangan gunakan markdown, tabel, salam pembuka, atau salam penutup.\n"
        f"{closing_rule}"
    )


def generate_memo_docx(
    memo_type: str,
    title: str,
    context: str,
    text_generator: Callable[[str], str] | None = None,
    configuration: Mapping[str, Any] | None = None,
) -> MemoDraft:
    normalized_type = normalize_memo_type(memo_type)
    clean_title = _clean_title(title)
    clean_context = (context or "").strip()

    if not clean_context:
        raise ValueError("Konteks memo wajib diisi.")

    config = _normalize_configuration(configuration, clean_title, clean_context)
    generator = text_generator or _default_text_generator
    prompt = build_memo_prompt(normalized_type, clean_title, clean_context, config)
    body = _normalize_generated_text(generator(prompt))
    config["page_size"] = _resolve_page_size(config, body)

    document = _build_official_memo_document(normalized_type, config, body)

    buffer = BytesIO()
    document.save(buffer)

    searchable_text = _build_searchable_text(normalized_type, config, body)

    return MemoDraft(
        filename=f"{_slugify(clean_title)}.docx",
        content=buffer.getvalue(),
        searchable_text=searchable_text,
        page_size=config["page_size"],
    )


def _default_text_generator(prompt: str) -> str:
    from app.llm_manager import get_llm_stream

    chunks: list[str] = []
    for chunk in get_llm_stream([{"role": "user", "content": prompt}]):
        chunks.append(chunk)

    return "".join(chunks)


def _build_official_memo_document(memo_type: str, config: dict[str, str], body: str) -> Document:
    document = Document()
    document.core_properties.title = config["subject"]
    document.core_properties.subject = SUPPORTED_MEMO_TYPES[memo_type]

    _apply_section_layout(document.sections[0], config["page_size"])
    _apply_base_styles(document)
    _add_header(document)
    _add_document_title(document, memo_type, config["number"])
    _add_metadata(document, config)
    _add_separator(document)
    _add_body(document, body)
    _add_closing(document, config["closing"])
    _add_signature_placeholder(document, config["signatory"])
    _add_carbon_copy(document, config["carbon_copy"])
    _add_footer(document.sections[0])

    return document


def _apply_section_layout(section, page_size: str) -> None:
    section.page_width = Inches(8.5)
    section.page_height = Inches(11 if page_size == "letter" else 14)
    section.top_margin = Inches(0.5)
    section.left_margin = Inches(1.18)
    section.right_margin = Inches(0.98)
    section.bottom_margin = Inches(0.38)
    section.footer_distance = Inches(0.22)


def _apply_base_styles(document: Document) -> None:
    normal = document.styles["Normal"]
    normal.font.name = "Arial"
    normal.font.size = Pt(11)
    _set_style_font(normal, "Arial")

    for style_name in ["Header", "Footer"]:
        style = document.styles[style_name]
        style.font.name = "Arial"
        _set_style_font(style, "Arial")


def _add_header(document: Document) -> None:
    for index, line in enumerate(HEADER_LINES):
        paragraph = document.add_paragraph()
        _format_paragraph(
            paragraph,
            alignment=WD_ALIGN_PARAGRAPH.CENTER,
            line_spacing_pt=14.95,
            space_after_pt=33 if index == len(HEADER_LINES) - 1 else 0,
        )
        run = paragraph.add_run(line)
        _format_run(run, size_pt=12, bold=True, color="4D4D4D")


def _add_document_title(document: Document, memo_type: str, number: str) -> None:
    title = "MEMORANDUM" if memo_type == "memo_internal" else SUPPORTED_MEMO_TYPES[memo_type].upper()

    paragraph = document.add_paragraph()
    _format_paragraph(paragraph, alignment=WD_ALIGN_PARAGRAPH.CENTER, line_spacing_pt=13.8)
    run = paragraph.add_run(title)
    _format_run(run, size_pt=11, bold=True)

    paragraph = document.add_paragraph()
    _format_paragraph(
        paragraph,
        alignment=WD_ALIGN_PARAGRAPH.CENTER,
        line_spacing_pt=13.8,
        space_after_pt=10,
    )
    run = paragraph.add_run(f"Nomor {number}")
    _format_run(run, size_pt=11)


def _add_metadata(document: Document, config: dict[str, str]) -> None:
    rows = [
        ("Yth.", config["recipient"]),
        ("Dari", config["sender"]),
        ("Hal", config["subject"]),
        ("Tanggal", config["date"]),
    ]
    table = document.add_table(rows=len(rows), cols=3)
    table.autofit = False

    widths = [Inches(0.62), Inches(0.12), Inches(5.52)]
    for row_index, (label, value) in enumerate(rows):
        cells = table.rows[row_index].cells
        for cell_index, width in enumerate(widths):
            cells[cell_index].width = width
            cells[cell_index].vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.TOP
            _set_cell_margins(cells[cell_index], top=0, start=0, bottom=0, end=0)

        _set_cell_text(cells[0], label)
        _set_cell_text(cells[1], ":")
        _set_cell_text(cells[2], value)


def _add_separator(document: Document) -> None:
    paragraph = document.add_paragraph()
    _format_paragraph(paragraph, line_spacing_pt=1, space_before_pt=16, space_after_pt=39)
    _set_paragraph_bottom_border(paragraph)


def _add_body(document: Document, body: str) -> None:
    for block in _split_blocks(body):
        numbered = re.match(r"^(\d+)[.)]\s+(.+)$", block)
        bulleted = re.match(r"^([-*])\s+(.+)$", block)

        if numbered:
            paragraph = document.add_paragraph()
            _format_paragraph(
                paragraph,
                alignment=WD_ALIGN_PARAGRAPH.JUSTIFY,
                line_spacing_pt=13.8,
                left_indent_in=0.28,
                first_line_indent_in=-0.28,
            )
            paragraph.paragraph_format.tab_stops.add_tab_stop(Inches(0.28))
            _append_text_run(paragraph, f"{numbered.group(1)}.\t{numbered.group(2).strip()}")
            continue

        if bulleted:
            paragraph = document.add_paragraph()
            _format_paragraph(
                paragraph,
                alignment=WD_ALIGN_PARAGRAPH.JUSTIFY,
                line_spacing_pt=13.8,
                left_indent_in=0.28,
                first_line_indent_in=-0.28,
            )
            paragraph.paragraph_format.tab_stops.add_tab_stop(Inches(0.28))
            _append_text_run(paragraph, f"-\t{bulleted.group(2).strip()}")
            continue

        paragraph = document.add_paragraph()
        _format_paragraph(
            paragraph,
            alignment=WD_ALIGN_PARAGRAPH.JUSTIFY,
            line_spacing_pt=13.8,
            first_line_indent_in=0.5,
        )
        _append_text_run(paragraph, block)


def _add_closing(document: Document, closing: str) -> None:
    if not closing:
        return

    paragraph = document.add_paragraph()
    _format_paragraph(
        paragraph,
        alignment=WD_ALIGN_PARAGRAPH.JUSTIFY,
        line_spacing_pt=13.8,
        first_line_indent_in=0.5,
        space_before_pt=20,
    )
    _append_text_run(paragraph, closing)


def _add_signature_placeholder(document: Document, signatory: str) -> None:
    spacer = document.add_paragraph()
    _format_paragraph(spacer, line_spacing_pt=1, space_before_pt=42, space_after_pt=0)

    table = document.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.RIGHT
    table.autofit = False
    table.rows[0].height = Inches(0.86)
    table.rows[0].height_rule = WD_ROW_HEIGHT_RULE.EXACTLY

    cell = table.cell(0, 0)
    cell.width = Inches(0.86)
    cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    _set_cell_margins(cell, top=0, start=0, bottom=0, end=0)
    _set_cell_border(cell, color="777777")
    paragraph = cell.paragraphs[0]
    _format_paragraph(paragraph, alignment=WD_ALIGN_PARAGRAPH.CENTER, line_spacing_pt=8)
    run = paragraph.add_run("QR\nTTE")
    _format_run(run, size_pt=6, color="777777")

    name = document.add_paragraph()
    _format_paragraph(
        name,
        alignment=WD_ALIGN_PARAGRAPH.RIGHT,
        line_spacing_pt=13.8,
        space_before_pt=10,
    )
    _append_text_run(name, signatory)


def _add_carbon_copy(document: Document, carbon_copy: str) -> None:
    lines = _split_lines(carbon_copy)
    if not lines:
        return

    heading = document.add_paragraph()
    _format_paragraph(heading, line_spacing_pt=13.8, space_before_pt=48)
    _append_text_run(heading, "Tembusan:")

    should_auto_number = len(lines) > 1 and not any(re.match(r"^\d+[.)]\s+", line) for line in lines)
    for index, line in enumerate(lines, start=1):
        text = f"{index}. {line}" if should_auto_number else line
        numbered = re.match(r"^(\d+)[.)]\s+(.+)$", text)

        paragraph = document.add_paragraph()
        if numbered:
            _format_paragraph(
                paragraph,
                line_spacing_pt=13.8,
                left_indent_in=0.28,
                first_line_indent_in=-0.28,
            )
            paragraph.paragraph_format.tab_stops.add_tab_stop(Inches(0.28))
            _append_text_run(paragraph, f"{numbered.group(1)}.\t{numbered.group(2).strip()}")
        else:
            _format_paragraph(paragraph, line_spacing_pt=13.8)
            _append_text_run(paragraph, text)


def _add_footer(section) -> None:
    footer = section.footer
    paragraph = footer.paragraphs[0]
    paragraph.text = ""
    _format_paragraph(paragraph, alignment=WD_ALIGN_PARAGRAPH.CENTER, line_spacing_pt=8)
    run = paragraph.add_run(FOOTER_NOTICE)
    _format_run(run, size_pt=7)


def _set_cell_text(cell, text: str) -> None:
    cell.text = ""
    paragraph = cell.paragraphs[0]
    _format_paragraph(paragraph, line_spacing_pt=13.8)
    _append_text_run(paragraph, text)


def _format_paragraph(
    paragraph,
    *,
    alignment=None,
    line_spacing_pt: float = 13.8,
    space_before_pt: float = 0,
    space_after_pt: float = 0,
    first_line_indent_in: float | None = None,
    left_indent_in: float | None = None,
) -> None:
    fmt = paragraph.paragraph_format
    if alignment is not None:
        paragraph.alignment = alignment
    fmt.line_spacing = Pt(line_spacing_pt)
    fmt.line_spacing_rule = WD_LINE_SPACING.EXACTLY
    fmt.space_before = Pt(space_before_pt)
    fmt.space_after = Pt(space_after_pt)
    if first_line_indent_in is not None:
        fmt.first_line_indent = Inches(first_line_indent_in)
    if left_indent_in is not None:
        fmt.left_indent = Inches(left_indent_in)


def _append_text_run(paragraph, text: str):
    run = paragraph.add_run(text)
    _format_run(run, size_pt=11)
    return run


def _format_run(run, *, size_pt: float, bold: bool = False, italic: bool = False, color: str | None = None) -> None:
    run.font.name = "Arial"
    run.font.size = Pt(size_pt)
    run.font.bold = bold
    run.font.italic = italic
    if color:
        run.font.color.rgb = RGBColor.from_string(color)

    r_pr = run._element.get_or_add_rPr()
    r_fonts = r_pr.rFonts
    if r_fonts is None:
        r_fonts = OxmlElement("w:rFonts")
        r_pr.append(r_fonts)
    for key in ["ascii", "hAnsi", "eastAsia", "cs"]:
        r_fonts.set(qn(f"w:{key}"), "Arial")


def _set_style_font(style, font_name: str) -> None:
    r_pr = style.element.get_or_add_rPr()
    r_fonts = r_pr.rFonts
    if r_fonts is None:
        r_fonts = OxmlElement("w:rFonts")
        r_pr.append(r_fonts)
    for key in ["ascii", "hAnsi", "eastAsia", "cs"]:
        r_fonts.set(qn(f"w:{key}"), font_name)


def _set_paragraph_bottom_border(paragraph) -> None:
    p_pr = paragraph._p.get_or_add_pPr()
    p_bdr = p_pr.find(qn("w:pBdr"))
    if p_bdr is None:
        p_bdr = OxmlElement("w:pBdr")
        p_pr.append(p_bdr)

    bottom = OxmlElement("w:bottom")
    bottom.set(qn("w:val"), "single")
    bottom.set(qn("w:sz"), "8")
    bottom.set(qn("w:space"), "1")
    bottom.set(qn("w:color"), "000000")
    p_bdr.append(bottom)


def _set_cell_margins(cell, *, top: int, start: int, bottom: int, end: int) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)

    for margin_name, value in {"top": top, "start": start, "bottom": bottom, "end": end}.items():
        node = tc_mar.find(qn(f"w:{margin_name}"))
        if node is None:
            node = OxmlElement(f"w:{margin_name}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def _set_cell_border(cell, *, color: str) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = tc_pr.first_child_found_in("w:tcBorders")
    if borders is None:
        borders = OxmlElement("w:tcBorders")
        tc_pr.append(borders)

    for edge in ["top", "left", "bottom", "right"]:
        tag = f"w:{edge}"
        node = borders.find(qn(tag))
        if node is None:
            node = OxmlElement(tag)
            borders.append(node)
        node.set(qn("w:val"), "single")
        node.set(qn("w:sz"), "6")
        node.set(qn("w:space"), "0")
        node.set(qn("w:color"), color)


def _normalize_configuration(
    configuration: Mapping[str, Any] | None,
    title: str,
    context: str,
) -> dict[str, str]:
    raw = dict(configuration or {})

    page_size_mode = _clean_config_value(raw.get("page_size_mode")).lower()
    page_size = _clean_config_value(raw.get("page_size"), default="folio").lower()

    if page_size_mode == "auto" or page_size == "auto":
        page_size = "auto"
    elif page_size not in {"folio", "letter"}:
        page_size = "folio"

    return {
        "number": _clean_config_value(raw.get("number"), default="-"),
        "recipient": _clean_config_value(raw.get("recipient"), default="-"),
        "sender": _clean_config_value(raw.get("sender"), default="Kepala Istana Kepresidenan Yogyakarta"),
        "subject": _clean_config_value(raw.get("subject"), default=title),
        "date": _clean_config_value(raw.get("date"), default="-"),
        "basis": _clean_config_value(raw.get("basis")),
        "content": _clean_config_value(raw.get("content"), default=context),
        "closing": _clean_config_value(raw.get("closing")),
        "signatory": _clean_config_value(raw.get("signatory"), default="Deni Mulyana"),
        "carbon_copy": _clean_config_value(raw.get("carbon_copy")),
        "page_size": page_size,
        "page_size_mode": page_size_mode,
        "additional_instruction": _clean_config_value(raw.get("additional_instruction")),
        "revision_instruction": _clean_config_value(raw.get("revision_instruction")),
    }


def _clean_config_value(value: Any, default: str = "") -> str:
    clean = re.sub(r"[ \t]+", " ", str(value or "").strip())
    return clean if clean else default


def _build_searchable_text(memo_type: str, config: dict[str, str], body: str) -> str:
    parts = [
        *HEADER_LINES,
        "",
        "MEMORANDUM" if memo_type == "memo_internal" else SUPPORTED_MEMO_TYPES[memo_type].upper(),
        f"Nomor {config['number']}",
        "",
        f"Yth.    : {config['recipient']}",
        f"Dari    : {config['sender']}",
        f"Hal     : {config['subject']}",
        f"Tanggal : {config['date']}",
        "",
        body,
        "",
        config["closing"],
        "",
        config["signatory"],
    ]

    if config["carbon_copy"]:
        parts.extend(["", "Tembusan:", config["carbon_copy"]])

    parts.extend(["", FOOTER_NOTICE])
    return "\n".join(part for part in parts if part is not None).strip()


def _resolve_page_size(config: dict[str, str], body: str) -> str:
    requested = config.get("page_size", "folio")
    if requested in {"letter", "folio"}:
        return requested

    carbon_copy_lines = _split_lines(config.get("carbon_copy", ""))
    body_blocks = _split_blocks(body)
    measured_text = "\n".join(
        [
            config.get("basis", ""),
            body,
            config.get("closing", ""),
            "\n".join(carbon_copy_lines),
        ]
    ).strip()
    numbered_count = sum(1 for block in body_blocks if re.match(r"^\d+[.)]\s+.+$", block))
    effective_blocks = len(body_blocks) + len(carbon_copy_lines)

    if len(measured_text) <= 820 and effective_blocks <= 8 and numbered_count <= 5:
        return "letter"

    return "folio"


def _clean_title(title: str) -> str:
    clean = re.sub(r"\s+", " ", (title or "").strip())
    if not clean:
        raise ValueError("Judul memo wajib diisi.")
    if len(clean) > 160:
        raise ValueError("Judul memo terlalu panjang.")
    return clean


def _normalize_generated_text(text: str) -> str:
    clean = (text or "").strip()
    if "[MODEL:" in clean and "]" in clean:
        clean = clean.split("]", 1)[1].strip()
    if not clean:
        raise ValueError("AI tidak menghasilkan isi memo.")
    return clean


def _split_blocks(text: str) -> list[str]:
    blocks = [re.sub(r"\s+", " ", block.strip()) for block in re.split(r"\n{1,}", text)]
    return [block for block in blocks if block]


def _split_lines(text: str) -> list[str]:
    return [line.strip() for line in (text or "").splitlines() if line.strip()]


def _slugify(value: str) -> str:
    slug = re.sub(r"[^\w.\-]+", "-", value.lower(), flags=re.UNICODE)
    slug = re.sub(r"-{2,}", "-", slug).strip("._-")
    return slug or "memo"
