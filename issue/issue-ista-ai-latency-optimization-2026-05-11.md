# Issue: Optimasi Latensi Respons ISTA AI

Tanggal: 2026-05-11

## Tujuan

Mempercepat respons ISTA AI pada tiga jalur utama tanpa menurunkan kualitas jawaban yang sudah ada:

1. chat biasa,
2. membaca dokumen / RAG,
3. web search.

## Konteks Temuan

- Jalur utama chat masuk ke `python-ai/app/chat_api.py` lalu streaming melalui `python-ai/app/llm_manager.py`.
- Chat dokumen memakai retrieval di `python-ai/app/retrieval_runner.py` dan service RAG di `python-ai/app/services/*`.
- Web search/context dibuat melalui `python-ai/app/services/rag_policy.py` sebelum LLM mulai streaming.
- Embedding provider dibuat di `python-ai/app/services/rag_embeddings.py` dan saat ini melakukan `embed_query("test")` saat memilih provider, sehingga menambah HTTP call sebelum retrieval/ingest berjalan.
- `get_llm_stream()` selalu memanggil `get_context_for_query()` ulang untuk non-RAG, padahal `chat_api.py` sudah menentukan policy web search. Ini menambah evaluasi policy dan potensi kerja yang tidak perlu.
- HyDE untuk dokumen aktif saat ini `mode: always` dengan timeout 3 detik; kualitas dijaga, tetapi timeout bisa diperkecil agar fallback ke query asli lebih cepat saat model HyDE lambat.

## Scope Implementasi

Prioritas perubahan dibuat konservatif dan low-risk:

1. Hilangkan embedding probe network call saat provider dipilih.
   - File: `python-ai/app/services/rag_embeddings.py`
   - Tambahkan cache provider ringan di proses berjalan.
   - Kualitas embedding aktual tidak berubah karena query/dokumen nyata tetap memakai provider dan fallback yang sama.

2. Deduplikasi web context/policy untuk chat biasa.
   - File: `python-ai/app/chat_api.py`, `python-ai/app/llm_manager.py`
   - `chat_api.py` menghitung context hanya jika `should_web_search` true lalu meneruskan hasil precomputed ke `get_llm_stream()`.
   - `get_llm_stream()` menerima `precomputed_context` agar tidak memanggil policy/search ulang.

3. Percepat fallback HyDE tanpa mematikannya.
   - File: `python-ai/config/ai_config.yaml`
   - Turunkan timeout HyDE dari 3 detik ke 2 detik; mode tetap `always` untuk menjaga kualitas retrieval dokumen.

4. Tambahkan/ubah test Python untuk memastikan perilaku dan kualitas kontrak tetap aman.
   - File target test: `python-ai/tests/test_rag_embeddings.py` dan/atau test chat/manager baru.

## Di Luar Scope Saat Ini

- Mengganti retrieval subprocess menjadi in-process.
- Mengurangi kandidat rerank/chunk secara agresif.
- Mengubah model utama/fallback chat.
- Mengubah prompt kualitas jawaban.
- Refactor besar queue/ingest dokumen.

## Risiko

- Cache embedding provider harus tetap aman jika env/config berubah di proses test; test perlu reset state cache.
- Precomputed web context harus menjaga semua flag (`force_web_search`, `allow_auto_realtime_web`, `documents_active`, `explicit_web_request`) tetap sama.
- HyDE timeout lebih pendek dapat membuat beberapa query dokumen fallback ke query asli lebih cepat bila HyDE lambat; mode tetap `always`, sehingga tidak menghapus fitur kualitas.

## Rencana Verifikasi

- Jalankan test Python relevan:
  - `cd python-ai && source venv/bin/activate && pytest tests/test_rag_embeddings.py`
  - Tambahan test untuk `llm_manager`/`chat_api` jika dibuat.
- Jalankan subset kontrak prompt/RAG bila perubahan menyentuh chat stream:
  - `cd python-ai && source venv/bin/activate && pytest tests/test_prompt_contracts.py tests/test_ista_ai.py`
- Jalankan `git diff --check`.
- Setelah PR dibuat dan dideploy ke `https://ista-ai.app`, lakukan browser QA pada chat biasa, chat dokumen, dan web search.

## Acceptance Criteria

- Chat biasa tidak memanggil web context/policy dua kali.
- Retrieval/ingest tidak melakukan embedding `"test"` probe network call hanya untuk memilih provider.
- HyDE tetap aktif tetapi timeout lebih cepat saat lambat.
- Test relevan lulus.
- PR review/QC tidak menemukan blocker.
