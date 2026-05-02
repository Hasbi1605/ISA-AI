from __future__ import annotations

import re
from dataclasses import dataclass
from io import BytesIO
from typing import Callable

from docx import Document


SUPPORTED_MEMO_TYPES = {
    "memo_internal": "Memo Internal",
    "nota_dinas": "Nota Dinas",
    "arahan": "Arahan",
}


@dataclass(slots=True)
class MemoDraft:
    filename: str
    content: bytes
    searchable_text: str


def normalize_memo_type(memo_type: str) -> str:
    normalized = (memo_type or "").strip().lower().replace("-", "_")
    if normalized not in SUPPORTED_MEMO_TYPES:
        raise ValueError("Jenis memo tidak didukung.")
    return normalized


def build_memo_prompt(memo_type: str, title: str, context: str) -> str:
    label = SUPPORTED_MEMO_TYPES[normalize_memo_type(memo_type)]
    return (
        "Buat draft dokumen resmi dalam Bahasa Indonesia.\n"
        f"Jenis: {label}\n"
        f"Judul: {title.strip()}\n"
        "Instruksi/konteks:\n"
        f"{context.strip()}\n\n"
        "Tulis body memo yang rapi, formal, ringkas, dan siap ditempel ke dokumen Word. "
        "Gunakan paragraf dan bullet bila perlu. Jangan sertakan markdown table."
    )


def generate_memo_docx(
    memo_type: str,
    title: str,
    context: str,
    text_generator: Callable[[str], str] | None = None,
) -> MemoDraft:
    normalized_type = normalize_memo_type(memo_type)
    clean_title = _clean_title(title)
    clean_context = (context or "").strip()

    if not clean_context:
        raise ValueError("Konteks memo wajib diisi.")

    generator = text_generator or _default_text_generator
    prompt = build_memo_prompt(normalized_type, clean_title, clean_context)
    body = _normalize_generated_text(generator(prompt))

    document = Document()
    document.core_properties.title = clean_title
    document.core_properties.subject = SUPPORTED_MEMO_TYPES[normalized_type]

    section = document.sections[0]
    section.top_margin = section.bottom_margin = section.left_margin = section.right_margin

    document.add_heading(SUPPORTED_MEMO_TYPES[normalized_type].upper(), level=1)
    document.add_paragraph(f"Perihal: {clean_title}")
    document.add_paragraph("")

    for block in _split_blocks(body):
        if _is_bullet(block):
            document.add_paragraph(_strip_bullet(block), style="List Bullet")
        else:
            document.add_paragraph(block)

    buffer = BytesIO()
    document.save(buffer)

    searchable_text = "\n".join([clean_title, SUPPORTED_MEMO_TYPES[normalized_type], body]).strip()

    return MemoDraft(
        filename=f"{_slugify(clean_title)}.docx",
        content=buffer.getvalue(),
        searchable_text=searchable_text,
    )


def _default_text_generator(prompt: str) -> str:
    from app.llm_manager import get_llm_stream

    chunks: list[str] = []
    for chunk in get_llm_stream([{"role": "user", "content": prompt}]):
        chunks.append(chunk)

    return "".join(chunks)


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


def _is_bullet(block: str) -> bool:
    return bool(re.match(r"^([-*•]|\d+[.)])\s+", block))


def _strip_bullet(block: str) -> str:
    return re.sub(r"^([-*•]|\d+[.)])\s+", "", block).strip()


def _slugify(value: str) -> str:
    slug = re.sub(r"[^\w.\-]+", "-", value.lower(), flags=re.UNICODE)
    slug = re.sub(r"-{2,}", "-", slug).strip("._-")
    return slug or "memo"
