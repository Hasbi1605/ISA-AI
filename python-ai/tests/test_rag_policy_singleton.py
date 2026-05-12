import os
import sys
import threading
import time

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))


def test_get_langsearch_service_thread_safe_singleton(monkeypatch):
    import app.services.rag_policy as rag_policy

    rag_policy._langsearch_service = None

    init_calls = {"count": 0}

    class FakeLangSearchService:
        def __init__(self):
            time.sleep(0.01)
            init_calls["count"] += 1

    monkeypatch.setattr("app.services.langsearch_service.LangSearchService", FakeLangSearchService)

    created_ids = []

    def _worker():
        svc = rag_policy.get_langsearch_service()
        created_ids.append(id(svc))

    threads = [threading.Thread(target=_worker) for _ in range(20)]
    for t in threads:
        t.start()
    for t in threads:
        t.join()

    assert len(set(created_ids)) == 1
    assert init_calls["count"] == 1
