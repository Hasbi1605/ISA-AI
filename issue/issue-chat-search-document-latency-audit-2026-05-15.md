# Audit Latency Chat, Web Search, dan Chat Dokumen

## Latar Belakang
User merasakan output chat biasa, web search, dan chat dengan dokumen masih lambat, kadang puluhan detik. Audit ini menelusuri alur end-to-end dari Laravel UI/job sampai Python AI, LLM provider, web search, dan RAG/document retrieval untuk mencari bottleneck dan peluang perbaikan tanpa menurunkan kualitas jawaban.

## Tujuan
- Petakan cara kerja chat biasa, web search, dan chat dokumen saat ini.
- Temukan penyebab latency yang didukung bukti kode, konfigurasi, atau test.
- Identifikasi improvement yang menjaga atau meningkatkan kualitas:
  - ketepatan web search
  - ketepatan retrieval dokumen/RAG
  - waktu menuju respons awal dan waktu selesai
- Bedakan quick wins berisiko rendah dari perubahan besar yang perlu issue terpisah.

## Scope
- Laravel chat flow: Livewire component, queue job, service orchestration, polling/loading state.
- Python AI chat API: request classification, LLM manager, streaming/non-streaming path.
- Web search: intent/policy, LangSearch service, jumlah result, timeout/fallback, prompt injection.
- Chat dokumen: retrieval, hybrid search, rerank/summarization/context packing, Chroma query.
- Config dan test yang langsung terkait performa/kualitas.

## Out of Scope
- Redesign UI besar.
- Migrasi provider/model besar tanpa bukti kuat.
- Perubahan security/auth/deploy production kecuali ditemukan sebagai bottleneck langsung.
- Merge/PR/deploy.

---

## GitHub Master Issue Scope

Issue ini sengaja mencakup seluruh konteks audit agar tidak kehilangan hubungan antar bottleneck. Implementasi tetap disarankan dipecah menjadi beberapa PR kecil, dengan urutan:

1. **PR 1 — Streaming end-to-end ke UI**
   - Target utama: output chat mulai terlihat saat token/chunk sudah diterima, bukan setelah job selesai.
   - Pertahankan persistensi final assistant message ke DB.
   - Polling tetap ada sebagai fallback/recovery, bukan jalur utama.
   - Wajib mencakup chat biasa, web search, dan chat dokumen.

2. **PR 2 — Tuning latency Python AI risiko rendah**
   - HyDE `always` → `smart` atau mode selektif berbasis query.
   - Timeout cascade model lebih ketat untuk slot awal.
   - Parallel score-query dual search.
   - Eager import/warm-up modul berat.
   - Polling UI diturunkan setelah streaming live aktif.

3. **PR 3 — RAG/web search quality-speed tuning berbasis evaluasi**
   - Evaluasi `top_k` 8 → 5 memakai eval set dokumen.
   - Tambah metrik latency per tahap.
   - Pastikan perubahan tidak menurunkan recall dokumen atau ketepatan sumber web.

4. **PR 4 — Infrastruktur produksi bila diperlukan**
   - Evaluasi `php -S` vs FPM/Octane.
   - Pisahkan queue chat dari mail/default bila antrean mulai memengaruhi latency.
   - Tuning worker Python/Horizon berbasis metrik production.

Catatan validasi audit Opus:
- Audit Opus valid untuk akar masalah utama: stream dari Python/PHP tidak sampai ke UI karena job mengakumulasi jawaban final dan UI bergantung pada polling.
- Estimasi seperti "300–600 ms", "1–10 s", atau "8–11 s blank" masuk akal sebagai indikasi, tetapi harus diperlakukan sebagai hipotesis sampai ada metrik riil per tahap.
- Rekomendasi terbaik tetap: selesaikan streaming end-to-end dulu, lalu tuning Python/RAG/web search dengan benchmark agar kualitas tidak turun.

---

## Ringkasan Eksekutif

Sumber utama "puluhan detik" yang dirasakan user **bukan** dari LLM atau retrieval, melainkan dari **arsitektur transport jawaban**:

1. Laravel mendispatch chat ke queued job (Horizon, Redis), bukan request HTTP yang stream.
2. Job mendrain seluruh stream LLM ke variabel string, baru menyimpan satu kali ke DB pada akhir.
3. UI Livewire melakukan polling 3 detik (`wire:poll.3s`) untuk mengetahui job sudah selesai.
4. Event live streaming token (`assistant-output`, `model-name`, `assistant-sources`) **didengar** oleh JS di `chat-page.js` tetapi **tidak pernah didispatch** dari sisi Laravel/Livewire.

Akibatnya time-to-first-token yang dilihat user ≈ `total LLM time + 0–3s polling`. Untuk respons LLM 8 detik, user bisa menunggu 8–11 detik tanpa karakter satu pun. Untuk respons 30 detik, user lihat blank 30+ detik. Streaming sebenarnya sudah ada di Python (`StreamingResponse` SSE) dan AIService PHP membaca chunk; jalur ini berhenti di queue job.

Faktor lain yang memperparah:
- Cold-start lazy import di tiap request `/api/chat` (`tiktoken`, `litellm`, `langchain_chroma`, `rag_*`).
- HyDE `mode: always` menambah satu LLM call (300–600 ms target, tetapi timeout 3 s) **sebelum** retrieval.
- Web search wajib LangSearch search + LangSearch rerank → dua HTTP calls serial sebelum LLM mulai jawab.
- Cascade fallback model menggunakan `litellm.completion(timeout=30)`. Timeout litellm ini per-attempt dan tidak menjamin time-to-first-token; bila model pertama lambat respond awal, user tetap menunggu sampai TTFB selesai sebelum cascade.
- Chat policy melakukan dua HTTP search berurutan untuk score query (`query` + `query final score`).
- Cascade rate limit pada embedding ingest melakukan `time.sleep(2.0)` × hingga 3 retry per batch.

Quick win paling impactful adalah **mengaktifkan stream end-to-end** sehingga user lihat token mengalir dalam <1 detik setelah submit, dan pekerjaan paralelisasi pada web search/RAG.

---

## Flow Map

### A. Chat biasa (tanpa dokumen, tanpa toggle web)
1. `POST /chat` (Livewire) — `ChatIndex::sendMessage` (`laravel/app/Livewire/Chat/ChatIndex.php:554`).
2. `GenerateChatResponse::dispatch(...)` ke queue Redis (`laravel/app/Livewire/Chat/ChatIndex.php:628`).
3. Worker Horizon (`docker-compose.production.yml:148`, `laravel/config/horizon.php:217`) menjalankan `GenerateChatResponse::handle` (`laravel/app/Jobs/GenerateChatResponse.php:39`).
4. `AIService::sendChat` POST `http://python-ai:8001/api/chat` dengan `stream: true`, baca chunk 1024 byte (`laravel/app/Services/AIService.php:90-104`).
5. FastAPI `chat_stream` panggil `should_use_web_search` → karena `documents_active=False` & `force_web_search=False` & `realtime_intent=low` → `should_web_search=False` → langsung `get_llm_stream` (`python-ai/app/chat_api.py:244`).
6. `get_llm_stream` tetap memanggil `get_context_for_query` → di sana `get_langsearch_service()` (lazy init, no-op kalau tidak search) lalu return tanpa search (`python-ai/app/llm_manager.py:117-129`).
7. `_stream_with_cascade` mencoba model satu per satu; chunk mengalir kembali via SSE (`python-ai/app/services/llm_streaming.py:336`).
8. PHP job mengakumulasi `$fullResponse`, menyimpan ke DB, set `conversation->touch()` (`laravel/app/Jobs/GenerateChatResponse.php:83-119`).
9. UI `wire:poll.3s="refreshPendingChatState"` melakukan reload conversation tiap 3 detik (`laravel/resources/views/livewire/chat/chat-index.blade.php:33`, `laravel/app/Livewire/Chat/ChatIndex.php:642`).

### B. Web search (toggle ON atau auto-realtime)
Sama seperti A sampai langkah 5, tetapi `should_use_web_search` returns `True`:
1. `LangSearchService.search` panggil `https://api.langsearch.com/v1/web-search`, timeout 10 s (`python-ai/app/services/langsearch_service.py:78-127`).
2. Bila score query, panggil search **lagi** dengan `f"{query} final score"` (`python-ai/app/services/rag_policy.py:256-260`).
3. `LangSearchService.rerank_documents` panggil `https://api.langsearch.com/v1/rerank`, timeout 8 s (`python-ai/app/services/langsearch_service.py:233-307`).
4. Search context disisipkan ke system prompt; `_stream_with_cascade` jalan.
5. Sumber web dikirim sebagai `[SOURCES:...]` di akhir stream.

### C. Chat dengan dokumen
Sama seperti A sampai langkah 4, tetapi `documents_active=True`:
1. Policy decide `should_web_search` (default False kecuali user toggle/explicit).
2. `search_relevant_chunks` lewat `asyncio.to_thread` (`python-ai/app/chat_api.py:170-197`).
3. `search_relevant_chunks` jalankan: HyDE LLM call (kalau `mode=always`), embedding query, vector similarity_search, BM25 hybrid, exclude parent, rerank LangSearch, optional PDR parent lookup (`python-ai/app/services/rag_retrieval.py:61`).
4. Build RAG prompt + sources → `get_llm_stream_with_sources` → cascade.

### D. Upload dokumen + ingest
1. UI upload via Livewire, `ProcessDocument` job dispatch ke queue.
2. Job panggil `python-ai-docs:8002 /api/documents/process` (`python-ai/app/routers/documents.py:81`).
3. `run_document_process` spawn **subprocess `python -m app.document_tasks process`** (`python-ai/app/document_runner.py:24-44`). Subprocess ini bootstrap interpreter Python baru, import langchain/chroma/pdfplumber/docx/openpyxl/tiktoken dari nol.
4. Subprocess: load text, lightweight chunking, embed dengan cascading fallback, batch upsert ke Chroma.

---

## Bottleneck dan Bukti

### B1. Streaming tidak sampai ke user (paling impactful)
**Bukti:**
- `laravel/app/Jobs/GenerateChatResponse.php:61-86` — loop `foreach ($aiService->sendChat(...) as $chunk)` mengakumulasi `$fullResponse` saja, tidak ada `Event` / Livewire dispatch / Reverb broadcast.
- `laravel/resources/js/chat-page.js:419` — listener `assistant-output`, `model-name`, `assistant-sources` terdaftar di Livewire.
- `grep -rn "assistant-output" laravel/app laravel/resources/views/` → **0 dispatcher**. Tidak ada code path yang pernah memicu event ini.
- `laravel/resources/views/livewire/chat/chat-index.blade.php:33` — polling 3 s adalah satu-satunya mekanisme refresh.
- `python-ai/app/chat_api.py:204-252` — Python sudah `StreamingResponse` SSE.

**Dampak:** Time-to-first-token dari sudut pandang user = (waktu LLM mulai sampai selesai) + (0–3 s polling). Untuk jawaban panjang/lambat ini terasa puluhan detik karena **tidak ada karakter pun yang muncul** sebelum semua selesai. UI menampilkan placeholder "AI sedang berpikir" dengan timer 8 + 8 detik (`chat-page.js:565-580`), yang membuat user lebih cepat berasumsi macet.

**Severity:** Tinggi. Ini sumber utama persepsi "puluhan detik".

### B2. Polling 3 detik untuk refresh state job
**Bukti:** `laravel/resources/views/livewire/chat/chat-index.blade.php:33`.
**Dampak:** Tambahan 0–3 detik antara job selesai dan UI menampilkan jawaban; juga membebani Livewire roundtrip (full component diff) tiap 3 s untuk semua user yang membuka tab chat.

### B3. Lazy import per request di Python chat
**Bukti:** `python-ai/app/chat_api.py:96-120`. `_get_chat_streamers`, `_get_rag_policy_helpers`, `_get_rag_document_helpers` mengimport `app.llm_manager`, `app.services.rag_policy`, `app.retrieval_runner`, `app.services.rag_retrieval` di dalam fungsi. Modul-modul itu menarik `litellm`, `langchain_chroma`, `tiktoken`, `rank_bm25`, `requests`, dan via `rag_retrieval` lagi `chromadb`.
**Dampak:** Pada cold-start worker uvicorn pertama, request pertama bisa menambah 1–3 detik untuk import. Setelah itu di-cache di module registry, biaya hanya satu kali per worker. Tetap mengorbankan request pertama setelah deploy/restart.
**Catatan:** `UVICORN_WORKERS=2` di compose, sehingga ada 2 cold paths terpisah.

### B4. HyDE `mode: always` menambah LLM call sebelum retrieval
**Bukti:** `python-ai/config/ai_config.yaml:273-277` — `enabled: true, mode: always, timeout: 3`. `python-ai/app/services/rag_hybrid.py:54-128` — pakai `litellm.completion(stream=False, timeout=3, max_tokens=100)` ke model chat tier murah pertama. Bila timeout, lanjut attempt kedua.
**Dampak:** Tambahan ≈ 300–1500 ms (atau 3000 ms × 2 atempt = 6000 ms saat provider lemot) sebelum vector search. Kualitas naik untuk pertanyaan abstrak, tapi banyak query sederhana ("apa isi dokumen X", "tolong rangkum") tidak butuh HyDE.

### B5. Dua call serial ke LangSearch (search + rerank) untuk web
**Bukti:** `python-ai/app/services/rag_policy.py:252-307`.
**Dampak:** Search timeout 10 s + rerank timeout 8 s = potensi 18 s **sebelum** LLM jalan. Pada kondisi normal LangSearch ≈ 1–2 s + ≈ 0.7–1.5 s; tetap 2–3.5 s sebelum LLM. Kandidat optimasi: paralel dengan retrieval dokumen, atau drop rerank untuk score query/cache hit, atau lakukan rerank di latar belakang sambil LLM mulai jawab dengan top-k vector.

### B6. Score query memicu dua web search berurutan
**Bukti:** `python-ai/app/services/rag_policy.py:256-260`.
```python
if _is_score_query(query) and score_signal is None:
    focused_query = f"{query} final score"
    focused_results = langsearch.search(focused_query)
```
**Dampak:** Tambahan 1–10 s saat skor tidak terdeteksi di hasil pertama. Kalau cache hit, gratis. Kandidat optimasi: jalankan paralel dari awal saat `_is_score_query=True`.

### B7. AIService PHP read dengan `$body->read(1024)` blocking
**Bukti:** `laravel/app/Services/AIService.php:102-104`.
**Dampak:** Karena `Guzzle` → cURL → blocking read, chunk yang sudah diakumulasi masih harus di-yield ke caller; tidak ada mekanisme streaming ke user, sehingga read 1024 byte yang efisien pun tidak membantu UX. Setelah B1 diperbaiki, ini cukup bagus.

### B8. `set_time_limit(120)` di sendMessage Livewire
**Bukti:** `laravel/app/Livewire/Chat/ChatIndex.php:569`. Tidak ada efek karena seluruh proses LLM sudah pindah ke job, tetapi `php -S` server (`docker/serve.sh`) tidak ideal untuk produksi karena single-threaded blocking — tiap polling/Livewire request memblok satu PHP process. Walau bukan langsung penyebab "puluhan detik", ini menambah variabilitas latensi UI saat banyak request poll bersamaan.

### B9. Cascade fallback model: timeout litellm 30 s per attempt
**Bukti:** `python-ai/app/services/llm_streaming.py:323-333`.
**Dampak:** Bila provider pertama hang, user menunggu sampai timeout 30 s sebelum cascade ke provider kedua. Tidak ada per-token / per-first-byte timeout. Untuk litellm, `timeout` umumnya berlaku untuk total request, tetapi pada streaming, behavior bergantung provider.
**Catatan:** Daftar model di `ai_config.yaml` panjang (15+ entry) sehingga worst-case sangat besar. Mitigasi: turunkan timeout ke 15 s untuk slot pertama dan 25 s untuk Bedrock heavy, lalu tambahkan `connect_timeout` lebih ketat.

### B10. RAG `top_k`/`doc_candidates` cukup tinggi → context besar
**Bukti:** `python-ai/config/ai_config.yaml:233-240` — `top_k: 8, top_n: 8, doc_candidates: 25`.
**Dampak:** Semakin banyak chunk → semakin besar prompt ke LLM → TTFT lebih lambat. Untuk kebanyakan query top_k 5 sudah cukup; rerank top_n 8 wajar untuk dokumen multi-topik. Tradeoff perlu diukur dengan eval set.

### B11. Subprocess `run_document_process` bootstrap interpreter penuh
**Bukti:** `python-ai/app/document_runner.py:28-44`.
**Dampak:** Untuk **upload** dokumen (bukan chat), tiap dokumen membuka interpreter baru → import 3–5 s overhead. Bukan penyebab lambat di chat biasa, tetapi terasa di "Sedang membaca dokumen" pertama kali. Sudah memakai `temp_files` dan stream chunk; aman dari memory leak. Tradeoff disengaja agar memori worker FastAPI tidak ditumpuki library berat.

### B12. Embedding cascade `time.sleep(2.0)` × 3 retry per batch
**Bukti:** `python-ai/app/services/rag_ingest.py:373-421`. Bila `429` muncul → cascade ke model berikutnya → `sleep(retry_delay)` mulai 2.0 s, doubling tiap retry. Worst case 14 s tambahan per batch.
**Dampak:** Hanya saat upload heavy dokumen + provider rate limit. Tidak menyentuh chat biasa.

### B13. Loading phase 8s + 8s di UI
**Bukti:** `laravel/resources/js/chat-page.js:565-580`.
**Dampak:** Tidak menambah latensi nyata, tapi membuat user lihat label "AI sedang berpikir" lama dan tidak ada teks yang mengalir karena B1. Ironis: timer ini dipakai untuk meneduhkan UX, sambil token tidak streaming sama sekali.

### B14. `wire:poll.3s` paralel di sidebar dokumen
**Bukti:** `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php:40` — 3 s polling saat ada dokumen processing, 20 s polling saat tidak.
**Dampak:** Tambahan request Livewire reload + DB hit setiap 3 s (saat ada upload aktif). Akumulatif memperberat container Laravel `php -S` 1 CPU.

### B15. `php -S` sebagai server produksi
**Bukti:** `laravel/docker/serve.sh`.
**Dampak:** Single-process built-in PHP server. Tidak ada FPM/Octane. Concurrent request user (poll 3s × N user + livewire submit + livewire updates) bisa antri. Limit memori 1024M & CPU 1.0 di compose.

---

## Faktor yang BUKAN bottleneck utama
- LangSearch caching sudah ada (`OrderedDict`, TTL 5 menit, max 200 entry) → repeat query cepat (`langsearch_service.py:50-72`).
- Chroma vector search dengan filter user_id+filename relatif cepat untuk koleksi <100k chunk.
- BM25 hybrid jalan in-memory atas korpus terbatas (per dokumen) → biasanya <100 ms.
- Sliding window `max_history_messages=20` mencegah prompt membengkak (`ChatOrchestrationService.php:93-102`).
- Tiktoken sudah pre-init di module load (`rag_embeddings.py:22`).

---

## Rekomendasi (kualitas tetap atau naik)

### Prioritas 1: Restore live streaming end-to-end (target: TTFT <1.5 s untuk chat biasa)

Pilih salah satu strategi:

**Opsi A (recommended): Streaming langsung dari Livewire ke UI tanpa queue**
- Ganti dispatch job → endpoint streaming khusus (`Route::get('/chat/stream/{conversation}', ...)` dengan `Symfony\StreamedResponse`).
- Endpoint ini otentikasi user, panggil `AIService::sendChat`, lakukan SSE/chunked output ke browser (`Cache-Control: no-cache`, `X-Accel-Buffering: no` untuk Caddy).
- UI buka EventSource setelah `sendMessage` ack; fallback polling tetap dipertahankan untuk recover dari koneksi putus.
- Persistensi assistant message tetap dilakukan saat stream selesai (di endpoint streaming, bukan di Livewire component).
- Hapus polling 3 s atau turunkan ke 15 s sebagai safety net.

Risiko: Caddy/Nginx buffering; perlu `flush_threshold_bytes` 0 dan disable gzip pada response stream. PHP `php -S` mendukung chunked output.

**Opsi B: Persist token incremental + Livewire poll dipercepat**
- Job tetap ada, tetapi simpan partial content ke `messages.content` setiap N token (debounced 300 ms) atau ke Redis pubsub.
- `wire:poll.500ms` (saat ada pending) hanya read partial buffer dari Redis (cheap).
- Lebih ringan diimplementasikan tetapi 500 ms latency masih ada per chunk dan lebih boros load.

**Opsi C: Laravel Reverb / Pusher broadcast event per token**
- Job dispatch `Event::dispatch(new AssistantToken(...))` per chunk; UI mendengar via Echo.
- Memerlukan Reverb server dan WebSocket — perubahan deploy.

Untuk tim ini, **Opsi A** paling cepat memberi efek dan tidak menyentuh broadcast layer. Opsi B mudah di-implement sebagai stopgap.

### Prioritas 2: Paralelkan web search + retrieval + LLM init

- Saat `documents_active and should_web_search`, jalankan `search_relevant_chunks` dan `get_context_for_query` sebagai dua coroutine (`asyncio.gather`) — sekarang sudah serial (`chat_api.py:170-188`).
- Saat web search aktif, bisa kirim chunk pertama LLM dengan prompt minimal sambil rerank berjalan; tetapi ini menambah kompleksitas signifikan, tunda dulu.
- Quick win: jalankan score-query "focused search" paralel dari awal saat detected, bukan setelah parsing pertama gagal (`rag_policy.py:256-260`).

### Prioritas 3: Tuning HyDE
- Default `mode: always` terlalu agresif. Ubah ke `mode: smart` (kode pendukungnya sudah ada di `_should_use_hyde`).
- Atau pertahankan `always` dengan `timeout: 1.5` dan max 1 attempt (saat ini 2). Bila gagal, lanjut tanpa HyDE — kualitas tetap terjaga karena BM25 hybrid + rerank sudah aktif.

### Prioritas 4: Tuning cascade timeout dan model order
- Turunkan `timeout` litellm di `_run_model` dari 30 → 15 untuk slot pertama (`llm_streaming.py:328`). Tambah `connect_timeout` 5 s.
- Pisahkan model tier "fast" vs "fallback" di `ai_config.yaml`. Slot pertama harus model dengan TTFB rendah konsisten (mis. Groq Llama 3.3 70B atau gemini native).
- Bedrock dipindahkan ke akhir cascade (sudah, baik).

### Prioritas 5: Tuning RAG retrieval
- Set `top_k: 5` (turun dari 8) untuk default. Eval kualitas dengan `python-ai/tests/`.
- `doc_candidates: 25` sudah baik. `bm25_candidates: 25` cukup.
- Tambah opsi `top_k_per_document` sehingga multi-document tidak dimensi blow up (sudah ada implicit `per_doc_k = doc_candidates // n_docs`, dapat dibuat eksplisit).

### Prioritas 6: Polling UI lebih ringan
- Setelah B1 fixed, ganti `wire:poll.3s="refreshPendingChatState"` menjadi `wire:poll.10s` sebagai safety net.
- Sidebar dokumen processing: turunkan ke 5 s saat aktif dan 60 s saat idle, atau pakai broadcast event saat status berubah.

### Prioritas 7: Server PHP
- Ganti `php -S` dengan `php-fpm` + Nginx/Caddy reverse proxy, atau Octane (Swoole/RoadRunner) bila concurrent user >20.
- Octane akan memberi keep-alive worker yang juga menghilangkan bootstrap Laravel per request.

### Prioritas 8: Cache warm-up dan eager-load Python
- Tambah `import` eager untuk modul berat di top-level `chat_api.py` agar import biaya terbayar saat startup container, bukan saat request pertama.
- Tambah liveness probe sederhana yang menyentuh path retrieval saat container ready, agar `langchain_chroma`, `litellm`, dll. sudah tervalidasi.

### Prioritas 9: Quality-only improvement
- Web search: pertimbangkan menambahkan `freshness="oneDay"` untuk realtime intent high (saat ini default `oneWeek`).
- RAG prompt: sudah baik. Pastikan saat web + dokumen aktif keduanya tidak meledakkan token (current cap di `compose_enhanced_system_prompt` belum truncate; perlu hard cap di production).
- Tambah `request_id` end-to-end (Laravel job id → Python logger) untuk tracing latency tiap fase. Saat ini log Python tidak bisa dihubungkan ke conversation Laravel kecuali via timing.

---

## Rekomendasi Quick Win (urutan eksekusi)

1. **Fix streaming live (Opsi A atau B)** — biggest impact, target di issue terpisah.
2. **HyDE mode `always` → `smart`** — turunkan median TTFT chat dokumen ≈ 300–600 ms.
3. **Cascade timeout 30→15 s** untuk slot pertama LLM.
4. **Score query: paralel dual search dari awal** — turunkan worst case web search sport.
5. **`top_k` rerank 8→5** — TTFT chat dokumen turun ≈ 300 ms.
6. **Eager import modul di `chat_api.py`** — request pertama setelah deploy lebih cepat.
7. **Polling UI 3s → 10s** setelah streaming live aktif.

Item 2–7 berisiko rendah dan dapat di-bundle jadi 1 PR kecil. Item 1 perlu PR sendiri dengan test.

---

## Test Gap

- Tidak ada test E2E yang mengukur TTFT (time to first token) chat. Tambah test integrasi yang verifikasi response stream mulai mengirim chunk dalam <2 s untuk chat biasa.
- Tidak ada test yang mensimulasikan provider lambat di `_run_model` untuk memvalidasi cascade aman.
- Test RAG yang ada (di `python-ai/tests/`) — perlu cek apakah ada eval-set untuk kualitas retrieval. Bila tidak, tambah dataset kecil 20–30 query → expected chunks untuk regression test sebelum mengubah `top_k` / HyDE mode.

## Risiko Perubahan
- Mengaktifkan streaming live menambah surface untuk error ("connection reset", duplicated message saat retry). Wajib idempotent persist (gunakan transaksi DB seperti yang sudah ada di `sendMessage`).
- Turunkan HyDE & top_k berisiko menurunkan recall. Wajib evaluasi sebelum deploy.
- Cascade timeout lebih ketat berisiko trigger fallback lebih sering. Pastikan logging detail fallback dan kuota provider memadai.

## Fakta vs Asumsi

Fakta (terbukti dari kode):
- Polling 3s, queued job, accumulated `$fullResponse`, listener tanpa dispatcher.
- HyDE `mode: always`, top_k 8, doc_candidates 25.
- Subprocess untuk ingest dokumen.
- `php -S` di produksi.
- Cascade litellm timeout 30 s.

Asumsi (perlu pengukuran):
- LangSearch median latency 1–2 s + 0.7–1.5 s rerank — perlu metric riil di environment ISTA.
- Cold-start worker import 1–3 s — perlu profiling dengan `python -X importtime`.
- TTFT LLM provider GitHub Models GPT-4.1 vs Groq Llama 3.3 — perlu A/B di production.
- Penurunan recall saat top_k 8→5 — perlu eval set internal.

## Risiko / Catatan
- Server `php -S` bukan production-grade; bila tidak diganti, penambahan paralel request (poll lebih cepat, banyak user) akan saling memblok.
- Caddy/Nginx layer perlu konfirmasi tidak buffer SSE response. Caddyfile di `deploy/Caddyfile` perlu cek `flush_interval`.
- Bila Reverb dipakai untuk broadcast token, container tambahan dan Redis pub/sub jadi dependency baru; pertimbangkan trade-off sebelum komit.

## Kriteria Selesai (audit)
- ✅ Flow map tiap mode chat (chat biasa, web search, chat dokumen, ingest dokumen).
- ✅ Daftar bottleneck dengan evidence file/fungsi (B1–B15).
- ✅ Rekomendasi prioritas dengan trade-off kualitas/risiko.
- ➡️ Tindak lanjut: bila user setuju, bisa dibuka issue terpisah untuk tiap prioritas (terutama P1 dan P2/P5) dan diimplementasikan bertahap.

---

## Patch Quick Win yang Sudah Diimplementasikan

### Q1. Cache provider embedding untuk chat dokumen
Sebelum patch, `get_embeddings_with_fallback()` selalu melakukan probe `embed_query("test")` setiap kali retrieval dokumen dimulai. Setelah itu Chroma tetap melakukan embedding untuk query asli, sehingga chat dokumen membayar minimal satu network call embedding tambahan.

Perubahan:
- `python-ai/app/services/rag_embeddings.py`
  - Menambahkan cache provider embedding yang sudah lulus probe selama 300 detik.
  - Cache hanya disimpan jika provider yang sukses adalah provider pada indeks yang diminta.
  - Jika request primary sementara jatuh ke fallback, fallback tidak dicache sebagai primary. Request berikutnya tetap mencoba primary lagi agar kualitas tidak diam-diam turun permanen.

Dampak:
- Chat dokumen berikutnya dalam proses Python yang sama tidak perlu probe embedding ekstra.
- Kualitas retrieval tetap memakai provider primary yang sama saat sehat.

### Q2. Skip web rerank yang tidak memangkas kandidat
Sebelum patch, default LangSearch mengembalikan 5 hasil dan konfigurasi `web_top_n` juga 5. Rerank pada kondisi ini menambah satu HTTP call ke `/rerank`, tetapi tetap mengirim 5 sumber ke LLM.

Perubahan:
- `python-ai/app/services/rag_policy.py`
  - Web rerank hanya dipanggil jika `len(search_results) > web_top_n`.

Dampak:
- Web search default menghindari satu network call rerank yang tidak menambah konteks.
- Jika konfigurasi memakai kandidat lebih banyak daripada final top-N, rerank tetap aktif.

### Q3. Cache key dan snippet quality LangSearch
Sebelum patch, cache LangSearch hanya memakai query + time bucket. Query yang sama dengan `freshness` atau `count` berbeda bisa memakai cache yang tidak sesuai. Selain itu formatter memakai `snippet` apa adanya walaupun kosong dan tidak fallback ke `summary`.

Perubahan:
- `python-ai/app/services/langsearch_service.py`
  - Cache key sekarang mencakup query, `freshness`, `count`, dan time bucket.
  - Formatter memakai `summary` bila `snippet` kosong.

Dampak:
- Ketepatan web search naik untuk variasi freshness/count.
- Konteks web lebih kaya ketika API hanya mengisi summary.

## Verifikasi Patch
- `cd python-ai && source venv/bin/activate && pytest tests/test_rag_embeddings.py tests/test_rag_policy_singleton.py tests/test_langsearch_service_cache.py tests/test_chat_api_concurrency.py tests/test_llm_streaming.py` → 22 passed.
- `cd python-ai && source venv/bin/activate && pytest` → 213 passed.
- `git diff --check` → passed.
