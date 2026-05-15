import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.services.langsearch_service import LangSearchService


# ---------------------------------------------------------------------------
# Cache key normalization
# ---------------------------------------------------------------------------

def test_langsearch_cache_normalizes_query_key():
    service = LangSearchService()
    bucket = service._get_time_bucket()

    service._cache_result("  Hello   WORLD  ", "oneWeek", 5, bucket, [{"title": "A"}])

    assert service._get_cached_result("hello world", "oneWeek", 5, bucket) == [{"title": "A"}]


def test_langsearch_cache_respects_max_size(monkeypatch):
    monkeypatch.setattr("app.services.langsearch_service.LANGSEARCH_CACHE_MAX_SIZE", 2)
    service = LangSearchService()
    bucket = service._get_time_bucket()

    service._cache_result("q1", "oneWeek", 5, bucket, [{"title": "1"}])
    service._cache_result("q2", "oneWeek", 5, bucket, [{"title": "2"}])
    service._cache_result("q3", "oneWeek", 5, bucket, [{"title": "3"}])

    assert len(service._search_cache) == 2
    assert service._get_cached_result("q1", "oneWeek", 5, bucket) is None
    assert service._get_cached_result("q2", "oneWeek", 5, bucket) == [{"title": "2"}]
    assert service._get_cached_result("q3", "oneWeek", 5, bucket) == [{"title": "3"}]


# ---------------------------------------------------------------------------
# Cache key includes freshness and count (fix #191)
# ---------------------------------------------------------------------------

def test_langsearch_cache_key_differs_by_freshness():
    """Query sama dengan freshness berbeda harus punya cache key berbeda."""
    service = LangSearchService()
    bucket = service._get_time_bucket()

    key_week = service._cache_key("berita terbaru", "oneWeek", 5, bucket)
    key_day = service._cache_key("berita terbaru", "oneDay", 5, bucket)

    assert key_week != key_day


def test_langsearch_cache_key_differs_by_count():
    """Query sama dengan count berbeda harus punya cache key berbeda."""
    service = LangSearchService()
    bucket = service._get_time_bucket()

    key_5 = service._cache_key("berita terbaru", "oneWeek", 5, bucket)
    key_10 = service._cache_key("berita terbaru", "oneWeek", 10, bucket)

    assert key_5 != key_10


def test_langsearch_cache_different_freshness_does_not_collide():
    """Hasil cache dengan freshness berbeda tidak saling menimpa."""
    service = LangSearchService()
    bucket = service._get_time_bucket()

    service._cache_result("query sama", "oneDay", 5, bucket, [{"title": "fresh"}])
    service._cache_result("query sama", "oneWeek", 5, bucket, [{"title": "week"}])

    assert service._get_cached_result("query sama", "oneDay", 5, bucket) == [{"title": "fresh"}]
    assert service._get_cached_result("query sama", "oneWeek", 5, bucket) == [{"title": "week"}]


def test_langsearch_cache_different_count_does_not_collide():
    """Hasil cache dengan count berbeda tidak saling menimpa."""
    service = LangSearchService()
    bucket = service._get_time_bucket()

    service._cache_result("query sama", "oneWeek", 5, bucket, [{"title": "5 results"}])
    service._cache_result("query sama", "oneWeek", 10, bucket, [{"title": "10 results"}])

    assert service._get_cached_result("query sama", "oneWeek", 5, bucket) == [{"title": "5 results"}]
    assert service._get_cached_result("query sama", "oneWeek", 10, bucket) == [{"title": "10 results"}]


# ---------------------------------------------------------------------------
# Snippet fallback (fix #191)
# ---------------------------------------------------------------------------

def test_build_search_context_uses_snippet_when_available():
    """Jika snippet ada, gunakan snippet."""
    service = LangSearchService()
    results = [{"title": "Judul", "snippet": "Isi snippet", "url": "https://example.com", "datePublished": ""}]
    context = service.build_search_context(results)
    assert "Isi snippet" in context


def test_build_search_context_falls_back_to_summary_when_snippet_empty():
    """Jika snippet kosong, gunakan summary sebagai fallback."""
    service = LangSearchService()
    results = [{"title": "Judul", "snippet": "", "summary": "Isi summary", "url": "https://example.com", "datePublished": ""}]
    context = service.build_search_context(results)
    assert "Isi summary" in context
    assert "No description" not in context


def test_build_search_context_falls_back_to_summary_when_snippet_missing():
    """Jika snippet tidak ada sama sekali, gunakan summary."""
    service = LangSearchService()
    results = [{"title": "Judul", "summary": "Isi summary dari API", "url": "https://example.com", "datePublished": ""}]
    context = service.build_search_context(results)
    assert "Isi summary dari API" in context


def test_build_search_context_uses_no_description_when_both_empty():
    """Jika snippet dan summary keduanya kosong, gunakan 'No description'."""
    service = LangSearchService()
    results = [{"title": "Judul", "snippet": "", "summary": "", "url": "https://example.com", "datePublished": ""}]
    context = service.build_search_context(results)
    assert "No description" in context


def test_build_search_context_snippet_takes_priority_over_summary():
    """Snippet harus diprioritaskan di atas summary jika keduanya ada."""
    service = LangSearchService()
    results = [{"title": "Judul", "snippet": "Snippet utama", "summary": "Summary cadangan", "url": "https://example.com", "datePublished": ""}]
    context = service.build_search_context(results)
    assert "Snippet utama" in context
    assert "Summary cadangan" not in context


def test_search_uses_summary_when_snippet_is_empty_string(monkeypatch):
    """Jika API mengirim snippet='' (string kosong), summary harus dipakai di search() sebelum masuk build_search_context()."""
    import unittest.mock as mock

    service = LangSearchService()

    fake_api_response = {
        "data": {
            "webPages": {
                "value": [
                    {
                        "name": "Judul Artikel",
                        "snippet": "",
                        "summary": "Isi summary dari API",
                        "url": "https://example.com",
                        "datePublished": "2026-01-01",
                    }
                ]
            }
        }
    }

    mock_response = mock.MagicMock()
    mock_response.raise_for_status.return_value = None
    mock_response.json.return_value = fake_api_response

    monkeypatch.setenv("LANGSEARCH_API_KEY", "fake-key-for-test")
    # Re-create service so api_key is picked up
    service = LangSearchService()

    with mock.patch("app.services.langsearch_service.requests.post", return_value=mock_response):
        results = service.search("test query")

    assert len(results) == 1
    assert results[0]["snippet"] == "Isi summary dari API", (
        f"snippet seharusnya diisi dari summary, got: {results[0]['snippet']!r}"
    )

    context = service.build_search_context(results)
    assert "Isi summary dari API" in context
    assert "No description" not in context
