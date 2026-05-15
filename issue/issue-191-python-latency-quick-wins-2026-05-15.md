# Issue #191 — Quick Wins Python AI untuk Latency Tanpa Menurunkan Kualitas

## Latar Belakang

Audit (#188) dan baseline metrik (#190) menemukan beberapa bottleneck berisiko rendah di pipeline Python AI yang bisa diperbaiki tanpa mengubah behavior kualitas utama.

## Tujuan

Kurangi latency web search dan chat dokumen melalui 4 quick wins:

1. **Cache provider embedding** — skip probe `embed_query("test")` yang berulang
2. **Skip web rerank** jika jumlah hasil sudah `<= web_top_n`
3. **Perbaiki cache key LangSearch** — masukkan `freshness` dan `count`
4. **Snippet fallback** — gunakan `summary` jika `snippet` kosong

## Detail Implementasi

### 1. Cache Provider Embedding (`rag_embeddings.py`)

**Masalah:** `get_embeddings_with_fallback()` dipanggil setiap request retrieval dan selalu menjalankan `embed_query("test")` sebagai probe. Ini menambah latency ~100-500ms per request.

**Solusi:** Cache instance embedding yang sudah lulus probe dalam TTL pendek (default 300 detik). Cache disimpan per `(provider_index, api_key_hash)` agar tidak mencampur provider berbeda.

**Guardrail:**
- Tidak cache fallback sebagai primary permanen
- Cache di-invalidate jika probe gagal saat digunakan
- TTL bisa dikonfigurasi via env `EMBEDDING_CACHE_TTL`

### 2. Skip Web Rerank (`rag_policy.py`)

**Masalah:** Rerank dipanggil bahkan ketika jumlah hasil search sudah `<= web_top_n`. Dalam kondisi ini rerank tidak memangkas kandidat — hanya membuang waktu ~200-400ms.

**Solusi:** Skip rerank jika `len(search_results) <= web_top_n`.

**Guardrail:**
- Tetap rerank jika kandidat lebih banyak dari `web_top_n`
- Tidak mengubah jumlah source final

### 3. Perbaiki Cache Key LangSearch (`langsearch_service.py`)

**Masalah:** Cache key hanya menggunakan `(normalized_query, time_bucket)`. Jika query yang sama dipanggil dengan `freshness="oneDay"` dan `freshness="oneWeek"`, hasilnya bisa tercampur.

**Solusi:** Masukkan `freshness` dan `count` ke dalam cache key: `(normalized_query, freshness, count, time_bucket)`.

**Guardrail:**
- Tidak mengubah TTL atau ukuran cache
- Backward compatible — hanya mengubah key tuple

### 4. Snippet Fallback (`langsearch_service.py`)

**Masalah:** `item.get("snippet", item.get("summary", ""))` sudah ada di `search()` tapi `build_search_context()` hanya pakai `result.get("snippet", "No description")`. Jika snippet kosong, context jadi "No description".

**Solusi:** Di `build_search_context()`, gunakan `snippet or summary or "No description"` sebagai fallback.

## File yang Diubah

- `python-ai/app/services/rag_embeddings.py` — cache provider embedding
- `python-ai/app/services/langsearch_service.py` — fix cache key + snippet fallback
- `python-ai/app/services/rag_policy.py` — skip rerank jika tidak perlu
- `python-ai/tests/test_rag_embeddings.py` — test cache embedding
- `python-ai/tests/test_langsearch_service_cache.py` — test cache key + snippet fallback

## Acceptance Criteria

- [ ] Cache embedding: probe tidak dipanggil ulang dalam TTL
- [ ] Skip rerank: rerank tidak dipanggil jika `len(results) <= web_top_n`
- [ ] Cache key: query sama dengan freshness berbeda tidak saling menimpa
- [ ] Snippet fallback: `summary` dipakai jika `snippet` kosong
- [ ] Full Python test hijau

## Cara Verifikasi

```bash
cd python-ai && source venv/bin/activate && pytest
```
