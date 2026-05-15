# Issue #193 — Tuning Web Search: Paralel Score Query, Freshness, Rerank, dan Source Quality

## Latar Belakang

Audit (#188) menemukan web search melakukan beberapa HTTP call serial sebelum LLM mulai menjawab:
1. `langsearch.search(query)` — search utama
2. `langsearch.search(f"{query} final score")` — focused search (hanya jika score query dan tidak ada signal)
3. `langsearch.rerank_documents(...)` — rerank

Untuk score query, focused search menunggu search utama selesai dulu (~300-500ms tambahan). Padahal keduanya bisa dijalankan paralel.

## Tujuan

1. **Paralel score query** — jalankan focused search bersamaan dengan search utama menggunakan `concurrent.futures.ThreadPoolExecutor`
2. **Freshness adaptif** — gunakan `oneDay` untuk `realtime_intent=high`, `oneWeek` untuk lainnya
3. **Source quality** — snippet/summary fallback sudah ada di `langsearch_service.py` (#191), pastikan konsisten

## Keputusan Implementasi

### Paralel Score Query

**Sebelum:**
```python
search_results = langsearch.search(query)  # serial
if _is_score_query(query) and score_signal is None:
    focused_results = langsearch.search(f"{query} final score")  # serial, menunggu yang pertama
```

**Sesudah:**
```python
# Jika score query, jalankan keduanya paralel dari awal
if _is_score_query(query):
    with ThreadPoolExecutor(max_workers=2) as executor:
        f_main = executor.submit(langsearch.search, query)
        f_focused = executor.submit(langsearch.search, f"{query} final score")
    search_results = _merge_search_results(f_main.result(), f_focused.result())
else:
    search_results = langsearch.search(query)
```

**Guardrail:** Hanya aktif untuk score query. Non-score query tetap serial (tidak perlu paralel).

### Freshness Adaptif

**Sebelum:** Selalu `oneWeek` (default hardcoded di `langsearch_service.py`)

**Sesudah:**
- `realtime_intent=high` → `oneDay` (berita terbaru, skor hari ini)
- `realtime_intent=medium` → `oneWeek` (default)
- `realtime_intent=low` atau tidak ada → `oneWeek` (default)

**Guardrail:** Freshness hanya diubah untuk intent tinggi. Tidak mengubah jumlah source.

## File yang Diubah

- `python-ai/app/services/rag_policy.py` — paralel score query + freshness adaptif
- `python-ai/tests/test_web_search_tuning.py` (baru) — test untuk perubahan

## Acceptance Criteria

- [ ] Score query menjalankan focused search paralel dengan search utama
- [ ] Freshness `oneDay` dipakai untuk `realtime_intent=high`
- [ ] Non-score query tidak terpengaruh
- [ ] Full Python test hijau

## Rollback

Semua perubahan ada di `rag_policy.py`. Rollback dengan revert file tersebut.
