import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from app.services.langsearch_service import LangSearchService


def test_langsearch_cache_normalizes_query_key():
    service = LangSearchService()
    bucket = service._get_time_bucket()

    service._cache_result("  Hello   WORLD  ", bucket, [{"title": "A"}])

    assert service._get_cached_result("hello world", bucket) == [{"title": "A"}]


def test_langsearch_cache_respects_max_size(monkeypatch):
    monkeypatch.setattr("app.services.langsearch_service.LANGSEARCH_CACHE_MAX_SIZE", 2)
    service = LangSearchService()
    bucket = service._get_time_bucket()

    service._cache_result("q1", bucket, [{"title": "1"}])
    service._cache_result("q2", bucket, [{"title": "2"}])
    service._cache_result("q3", bucket, [{"title": "3"}])

    assert len(service._search_cache) == 2
    assert service._get_cached_result("q1", bucket) is None
    assert service._get_cached_result("q2", bucket) == [{"title": "2"}]
    assert service._get_cached_result("q3", bucket) == [{"title": "3"}]
