# Issue #190 — Tambahkan Metrik Latency, TTFT, dan Eval Set Sebelum Tuning Kualitas

## Latar Belakang

Audit (#188) menemukan bottleneck estimasi di pipeline chat, tetapi angka TTFT, waktu LangSearch, waktu HyDE, waktu retrieval, dan fallback provider belum diukur di environment nyata. Sebelum tuning yang bisa memengaruhi kualitas, perlu baseline metrik dan eval set kecil.

## Tujuan

1. Tambah `request_id` / correlation id dari Laravel ke Python agar log bisa dikorelasikan per request
2. Log timing per tahap penting di Python dan Laravel
3. Buat eval set RAG dokumen (20-30 query) untuk mencegah regresi retrieval
4. Buat benchmark runner lokal sederhana

## Arsitektur Logging

### Correlation ID

- Laravel `GenerateChatResponse` job dan `ChatStreamController` generate `request_id` (UUID) dan kirim sebagai header `X-Request-ID` ke Python
- Python `chat_api.py` baca header `X-Request-ID` dan sertakan di semua log timing untuk request tersebut
- Format log: structured JSON-like dengan field `request_id`, `stage`, `duration_ms`, `extra`

### Tahap yang Di-log

**Laravel:**
- `job_start` — saat job mulai handle (queue wait = `job_start - created_at`)
- `python_call_start` / `python_call_end` — durasi PHP → Python
- `db_save` — durasi simpan assistant message ke DB
- `job_total` — total durasi job

**Python:**
- `request_received` — saat endpoint menerima request
- `web_search_start` / `web_search_end` — durasi LangSearch search
- `rerank_start` / `rerank_end` — durasi rerank web/dokumen
- `hyde_start` / `hyde_end` — durasi HyDE query generation
- `embedding_start` / `embedding_end` — durasi embedding query
- `retrieval_start` / `retrieval_end` — durasi vector/BM25 retrieval
- `llm_first_chunk` — TTFT (time to first token)
- `llm_stream_end` — total LLM duration
- `request_total` — total durasi request Python

### Format Log

```
[LATENCY] stage=web_search_end request_id=abc123 duration_ms=342 extra={"results": 5}
[LATENCY] stage=llm_first_chunk request_id=abc123 duration_ms=1240 extra={"model": "gemini-pro"}
```

## Scope Implementasi

### File Baru
- `python-ai/app/services/latency_logger.py` — helper untuk structured latency logging
- `python-ai/tests/test_rag_eval_set.py` — eval set 25 query RAG dokumen
- `python-ai/tests/test_latency_logger.py` — unit test latency logger
- `python-ai/scripts/benchmark_chat.py` — benchmark runner lokal
- `issue/issue-190-latency-metrics-eval-set-2026-05-15.md` — issue plan ini

### File Diubah
- `python-ai/app/chat_api.py` — baca `X-Request-ID`, log timing per tahap
- `python-ai/app/services/rag_policy.py` — log timing web search + rerank
- `python-ai/app/services/rag_retrieval.py` — log timing HyDE, embedding, retrieval, rerank
- `python-ai/app/services/llm_streaming.py` — log TTFT dan total LLM duration
- `laravel/app/Jobs/GenerateChatResponse.php` — generate request_id, log job timing
- `laravel/app/Http/Controllers/Chat/ChatStreamController.php` — generate request_id, log stream timing

## Keamanan Logging

- Tidak log isi query/dokumen/konten user — hanya metadata (hash, length, duration)
- Tidak log token, API key, atau secret
- `request_id` adalah UUID random, tidak mengandung data user

## Acceptance Criteria

- [ ] Log latency per tahap dapat dikorelasikan untuk satu conversation/request via `request_id`
- [ ] Ada baseline metric sebelum dan sesudah PR berikutnya
- [ ] Ada eval set minimal untuk mencegah regresi retrieval dokumen
- [ ] Dokumentasi cara menjalankan benchmark tersedia di `scripts/benchmark_chat.py`

## Cara Menjalankan Pengukuran

```bash
# Jalankan eval set RAG
cd python-ai && source venv/bin/activate && pytest tests/test_rag_eval_set.py -v

# Jalankan benchmark lokal
cd python-ai && source venv/bin/activate && python scripts/benchmark_chat.py

# Lihat log latency di server
grep "\[LATENCY\]" python-ai/fastapi.log | jq -r '. | select(.stage == "llm_first_chunk")'
```

## Risiko

- Logging overhead minimal (< 1ms per log call) — tidak mempengaruhi latency production
- `request_id` tidak disimpan ke DB — hanya untuk korelasi log sementara
