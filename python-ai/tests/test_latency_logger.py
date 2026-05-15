"""
Unit test untuk latency_logger helper.
"""
import logging
import os
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.services.latency_logger import LatencyTracker, log_event, log_latency, timed_stage


# ---------------------------------------------------------------------------
# log_latency
# ---------------------------------------------------------------------------

def test_log_latency_emits_info_with_prefix(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        log_latency("web_search_end", 342.5, request_id="abc-123", extra={"results": 5})

    assert any("[LATENCY]" in r.message for r in caplog.records)
    assert any("web_search_end" in r.message for r in caplog.records)
    assert any("abc-123" in r.message for r in caplog.records)
    assert any("342.5" in r.message for r in caplog.records)


def test_log_latency_works_without_request_id(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        log_latency("llm_first_chunk", 1240.0)

    assert any("[LATENCY]" in r.message for r in caplog.records)
    assert any("llm_first_chunk" in r.message for r in caplog.records)
    assert any("none" in r.message for r in caplog.records)


def test_log_latency_does_not_log_sensitive_data(caplog):
    """Pastikan log tidak mengandung konten query atau dokumen."""
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        log_latency(
            "retrieval",
            88.0,
            request_id="rid-001",
            extra={"chunks": 5, "success": True},
        )

    for record in caplog.records:
        # extra hanya berisi metadata numerik/boolean, bukan konten
        assert "query" not in record.message.lower() or "query_len" in record.message
        assert "content" not in record.message.lower()


# ---------------------------------------------------------------------------
# log_event
# ---------------------------------------------------------------------------

def test_log_event_emits_info_without_duration(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        log_event("request_received", request_id="req-xyz", extra={"docs_active": False})

    assert any("[LATENCY]" in r.message for r in caplog.records)
    assert any("request_received" in r.message for r in caplog.records)
    assert any("req-xyz" in r.message for r in caplog.records)
    # event log tidak punya duration_ms field
    assert not any("duration_ms" in r.message for r in caplog.records)


# ---------------------------------------------------------------------------
# timed_stage context manager
# ---------------------------------------------------------------------------

def test_timed_stage_emits_latency_log(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        with timed_stage("embedding", request_id="rid-embed"):
            pass  # simulate work

    assert any("embedding" in r.message for r in caplog.records)
    assert any("duration_ms" in r.message for r in caplog.records)


def test_timed_stage_emits_log_even_on_exception(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        with pytest.raises(ValueError):
            with timed_stage("hyde", request_id="rid-hyde"):
                raise ValueError("simulated error")

    assert any("hyde" in r.message for r in caplog.records)


# ---------------------------------------------------------------------------
# LatencyTracker
# ---------------------------------------------------------------------------

def test_tracker_start_end_emits_latency(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        tracker = LatencyTracker(request_id="tracker-001")
        tracker.start("retrieval")
        tracker.end("retrieval", extra={"chunks": 10})

    assert any("retrieval" in r.message for r in caplog.records)
    assert any("tracker-001" in r.message for r in caplog.records)
    assert any("duration_ms" in r.message for r in caplog.records)


def test_tracker_end_without_start_returns_minus_one(caplog):
    tracker = LatencyTracker(request_id="tracker-002")
    result = tracker.end("nonexistent_stage")
    assert result == -1.0


def test_tracker_end_total_emits_total_duration(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        tracker = LatencyTracker(request_id="tracker-003")
        tracker.end_total("request_total", extra={"mode": "general_chat"})

    assert any("request_total" in r.message for r in caplog.records)
    assert any("tracker-003" in r.message for r in caplog.records)


def test_tracker_event_emits_without_duration(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        tracker = LatencyTracker(request_id="tracker-004")
        tracker.event("job_start", extra={"conversation_id": 42})

    assert any("job_start" in r.message for r in caplog.records)
    assert not any("duration_ms" in r.message for r in caplog.records)


def test_tracker_multiple_stages(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        tracker = LatencyTracker(request_id="tracker-multi")
        tracker.start("web_search")
        tracker.start("embedding")
        tracker.end("embedding", extra={"provider": "openai"})
        tracker.end("web_search", extra={"results": 8})
        tracker.end_total("request_total")

    messages = [r.message for r in caplog.records]
    assert any("web_search" in m for m in messages)
    assert any("embedding" in m for m in messages)
    assert any("request_total" in m for m in messages)


def test_tracker_without_request_id(caplog):
    with caplog.at_level(logging.INFO, logger="app.services.latency_logger"):
        tracker = LatencyTracker()
        tracker.start("doc_rerank")
        tracker.end("doc_rerank", extra={"candidates": 25, "top_n": 5})

    assert any("doc_rerank" in r.message for r in caplog.records)
    assert any("none" in r.message for r in caplog.records)
