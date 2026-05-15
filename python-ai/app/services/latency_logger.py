"""
Latency logger helper untuk ISTA AI.

Menyediakan structured timing log per tahap pipeline (web search, retrieval,
HyDE, embedding, LLM TTFT, dll) yang dapat dikorelasikan via request_id.

Format log:
    [LATENCY] stage=<stage> request_id=<id> duration_ms=<ms> extra=<json>

Keamanan:
- Tidak log isi query, konten dokumen, token, atau secret.
- request_id adalah UUID random, tidak mengandung data user.
- extra hanya berisi metadata numerik/kategorikal (count, model label, dll).
"""

from __future__ import annotations

import logging
import time
from contextlib import contextmanager
from typing import Any, Dict, Generator, Optional

logger = logging.getLogger(__name__)

# Prefix yang mudah di-grep dari log file
_LOG_PREFIX = "[LATENCY]"


def log_latency(
    stage: str,
    duration_ms: float,
    request_id: Optional[str] = None,
    extra: Optional[Dict[str, Any]] = None,
) -> None:
    """
    Emit satu baris structured latency log.

    Args:
        stage: Nama tahap pipeline, e.g. "web_search_end", "llm_first_chunk".
        duration_ms: Durasi dalam milidetik.
        request_id: Correlation ID dari Laravel (UUID). Boleh None.
        extra: Dict metadata tambahan — hanya nilai non-sensitif
               (count, model label, bool flag, dll).
    """
    import json

    rid = request_id or "none"
    extra_str = json.dumps(extra or {}, ensure_ascii=False, separators=(",", ":"))
    logger.info(
        "%s stage=%s request_id=%s duration_ms=%.1f extra=%s",
        _LOG_PREFIX,
        stage,
        rid,
        duration_ms,
        extra_str,
    )


def log_event(
    stage: str,
    request_id: Optional[str] = None,
    extra: Optional[Dict[str, Any]] = None,
) -> None:
    """
    Emit satu baris event log tanpa durasi (untuk titik awal atau event tunggal).

    Args:
        stage: Nama event, e.g. "request_received", "job_start".
        request_id: Correlation ID dari Laravel (UUID). Boleh None.
        extra: Dict metadata tambahan — hanya nilai non-sensitif.
    """
    import json

    rid = request_id or "none"
    extra_str = json.dumps(extra or {}, ensure_ascii=False, separators=(",", ":"))
    logger.info(
        "%s stage=%s request_id=%s extra=%s",
        _LOG_PREFIX,
        stage,
        rid,
        extra_str,
    )


@contextmanager
def timed_stage(
    stage: str,
    request_id: Optional[str] = None,
    extra: Optional[Dict[str, Any]] = None,
) -> Generator[None, None, None]:
    """
    Context manager yang mengukur durasi sebuah blok kode dan emit latency log.

    Usage:
        with timed_stage("web_search", request_id=rid, extra={"query_len": 42}):
            results = langsearch.search(query)

    Args:
        stage: Nama tahap pipeline.
        request_id: Correlation ID dari Laravel (UUID). Boleh None.
        extra: Dict metadata tambahan — hanya nilai non-sensitif.
    """
    t0 = time.perf_counter()
    try:
        yield
    finally:
        duration_ms = (time.perf_counter() - t0) * 1000.0
        log_latency(stage, duration_ms, request_id=request_id, extra=extra)


class LatencyTracker:
    """
    Tracker untuk mengukur beberapa tahap dalam satu request.

    Usage:
        tracker = LatencyTracker(request_id="abc-123")
        tracker.start("retrieval")
        chunks = search_relevant_chunks(...)
        tracker.end("retrieval", extra={"chunks": len(chunks)})
        tracker.end_total("request_total")
    """

    def __init__(self, request_id: Optional[str] = None) -> None:
        self.request_id = request_id
        self._starts: Dict[str, float] = {}
        self._t0 = time.perf_counter()

    def start(self, stage: str) -> None:
        """Catat waktu mulai sebuah tahap."""
        self._starts[stage] = time.perf_counter()

    def end(
        self,
        stage: str,
        extra: Optional[Dict[str, Any]] = None,
    ) -> float:
        """
        Catat waktu selesai sebuah tahap dan emit log.

        Returns:
            Durasi dalam milidetik, atau -1 jika start() belum dipanggil.
        """
        t_end = time.perf_counter()
        t_start = self._starts.pop(stage, None)
        if t_start is None:
            logger.debug("LatencyTracker.end('%s') dipanggil tanpa start()", stage)
            return -1.0

        duration_ms = (t_end - t_start) * 1000.0
        log_latency(stage, duration_ms, request_id=self.request_id, extra=extra)
        return duration_ms

    def end_total(
        self,
        stage: str = "request_total",
        extra: Optional[Dict[str, Any]] = None,
    ) -> float:
        """Emit log total durasi sejak LatencyTracker dibuat."""
        duration_ms = (time.perf_counter() - self._t0) * 1000.0
        log_latency(stage, duration_ms, request_id=self.request_id, extra=extra)
        return duration_ms

    def event(self, stage: str, extra: Optional[Dict[str, Any]] = None) -> None:
        """Emit event log tanpa durasi."""
        log_event(stage, request_id=self.request_id, extra=extra)
