import re
from typing import Callable, Iterable, List, Optional

from langchain_core.documents import Document


class LightweightRecursiveTextSplitter:
    """Token-aware text splitter without the heavyweight optional NLP stack."""

    def __init__(
        self,
        chunk_size: int,
        chunk_overlap: int,
        length_function: Callable[[str], int],
        add_start_index: bool = False,
        separators: Optional[List[str]] = None,
    ) -> None:
        self.chunk_size = chunk_size
        self.chunk_overlap = chunk_overlap
        self.length_function = length_function
        self.add_start_index = add_start_index
        self.separators = separators or ["\n\n", "\n", ". ", " ", ""]

    def split_documents(self, documents: Iterable[Document]) -> List[Document]:
        results: List[Document] = []
        for document in documents:
            start_cursor = 0
            for chunk_text in self.split_text(document.page_content or ""):
                metadata = dict(document.metadata or {})
                if self.add_start_index:
                    anchor = self._anchor_text(chunk_text)
                    found_at = document.page_content.find(anchor, start_cursor) if anchor else -1
                    if found_at < 0:
                        found_at = max(0, start_cursor)
                    metadata["start_index"] = found_at
                    start_cursor = found_at + max(len(anchor), 1)

                results.append(Document(page_content=chunk_text, metadata=metadata))

        return results

    def split_text(self, text: str) -> List[str]:
        if not text:
            return []

        atomic_segments = self._split_recursively(text, self.separators)
        return self._merge_segments(atomic_segments)

    def _split_recursively(self, text: str, separators: List[str]) -> List[str]:
        normalized = text.strip()
        if not normalized:
            return []

        if self.length_function(normalized) <= self.chunk_size:
            return [normalized]

        if not separators:
            return self._split_by_words(normalized)

        separator = separators[0]
        if separator == "":
            return self._split_by_words(normalized)

        if separator not in normalized:
            return self._split_recursively(normalized, separators[1:])

        parts = normalized.split(separator)
        restored_parts = [
            part + separator if idx < len(parts) - 1 else part
            for idx, part in enumerate(parts)
            if part
        ]

        segments: List[str] = []
        for part in restored_parts:
            if self.length_function(part) <= self.chunk_size:
                segments.append(part)
            else:
                segments.extend(self._split_recursively(part, separators[1:]))

        return segments

    def _merge_segments(self, segments: List[str]) -> List[str]:
        chunks: List[str] = []
        current = ""

        for segment in segments:
            candidate = f"{current}{segment}" if current else segment
            if current and self.length_function(candidate) > self.chunk_size:
                finalized = current.strip()
                if finalized:
                    chunks.append(finalized)
                overlap = self._tail_by_tokens(finalized, self.chunk_overlap)
                current = f"{overlap}{segment}" if overlap else segment
                if self.length_function(current) > self.chunk_size:
                    oversized = self._split_by_words(current)
                    chunks.extend(oversized[:-1])
                    current = oversized[-1] if oversized else ""
            else:
                current = candidate

        finalized = current.strip()
        if finalized:
            chunks.append(finalized)

        return chunks

    def _split_by_words(self, text: str) -> List[str]:
        tokens = re.findall(r"\S+\s*", text)
        if not tokens:
            return [text[: self.chunk_size]]

        chunks: List[str] = []
        current = ""

        for token in tokens:
            candidate = f"{current}{token}" if current else token
            if current and self.length_function(candidate) > self.chunk_size:
                finalized = current.strip()
                if finalized:
                    chunks.append(finalized)
                overlap = self._tail_by_tokens(finalized, self.chunk_overlap)
                current = f"{overlap}{token}" if overlap else token
            else:
                current = candidate

        finalized = current.strip()
        if finalized:
            chunks.append(finalized)

        return chunks

    def _tail_by_tokens(self, text: str, max_tokens: int) -> str:
        if not text or max_tokens <= 0:
            return ""

        parts = re.findall(r"\S+\s*", text)
        if not parts:
            return ""

        tail: List[str] = []
        for part in reversed(parts):
            candidate = "".join(reversed([part] + tail))
            if tail and self.length_function(candidate) > max_tokens:
                break
            tail.append(part)

        return "".join(reversed(tail)).lstrip()

    @staticmethod
    def _anchor_text(chunk_text: str) -> str:
        normalized = chunk_text.strip()
        if not normalized:
            return ""
        return normalized[:80]
