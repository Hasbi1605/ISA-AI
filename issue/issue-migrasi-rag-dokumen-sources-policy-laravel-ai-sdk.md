# Issue Plan: Migrasi RAG Dokumen, Source Rendering, dan Policy Dokumen-vs-Web ke Laravel AI SDK

## Latar Belakang
Issue ini adalah turunan inti dari issue `#67` untuk memindahkan capability RAG dokumen ke Laravel-only setelah lifecycle dokumen dasar sudah pindah. Di sistem saat ini, kualitas utama banyak ditentukan oleh retrieval dokumen, policy dokumen-vs-web, dan source rendering. Area ini paling sensitif terhadap regresi.

Capability saat ini tersebar di:

- `python-ai/app/services/rag_retrieval.py`
- `python-ai/app/services/rag_policy.py`
- `python-ai/app/services/rag_hybrid.py`
- `python-ai/app/main.py`
- `laravel/app/Services/ChatOrchestrationService.php`

## Tujuan
- Memindahkan retrieval dokumen aktif ke Laravel-only dengan provider-managed vector stores/file search sebagai target utama.
- Menjaga policy dokumen-first, explicit web, dan fallback ketika dokumen tidak menjawab.
- Menjaga source rendering user-facing tetap konsisten di Laravel.

## Ruang Lingkup
- Menetapkan strategi retrieval target:
  - provider-managed vector store/file search sebagai jalur utama
  - fallback minimal jika ada gap penting
- Porting behavior penting:
  - dokumen aktif memblokir auto web kecuali explicit/force
  - jawaban “tidak ada di dokumen” tetap user-facing dan ringkas
  - source dokumen dan web tetap terformat rapi
- Menentukan parity minimum untuk multi-document selection dan document filtering per user.
- Menentukan apakah rerank/hybrid behavior lama dipertahankan penuh atau disederhanakan di cutover pertama.

## Di Luar Scope
- Upgrade platform.
- Chat umum non-dokumen.
- Lifecycle upload/process/summarize dasar dokumen.
- Cutover final seluruh traffic.

## Area / File Terkait
- `python-ai/app/services/rag_retrieval.py`
- `python-ai/app/services/rag_policy.py`
- `python-ai/app/services/rag_hybrid.py`
- `python-ai/app/main.py`
- `laravel/app/Services/ChatOrchestrationService.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `python-ai/tests/test_ista_ai.py`
- `python-ai/tests/test_prompt_eval_scenarios.py`
- `laravel/tests/Unit/Services/ChatOrchestrationServiceTest.php`

## Risiko
- Provider-managed retrieval tidak menutup semua perilaku hybrid search yang sekarang ada.
- Multi-document filtering atau metadata per user bisa drift jika mapping store/file tidak disiplin.
- Source shape dari provider berbeda dan mengubah rendering rujukan.
- Policy dokumen-vs-web tampak kecil, tetapi drift di sini sangat terasa oleh user.

## Langkah Implementasi
1. Petakan perilaku retrieval saat ini yang benar-benar wajib dipertahankan pada cutover pertama.
2. Tentukan model data metadata dokumen/store/file agar filtering per user dan per dokumen tetap aman.
3. Implementasikan document-first retrieval pada runtime Laravel baru.
4. Implementasikan fallback behavior:
   - dokumen tidak menjawab
   - retrieval gagal
   - explicit web request
5. Standarkan source metadata ke format yang dipakai layer Laravel.
6. Integrasikan capability ini melalui feature flag dan shadow mode.
7. Nilai gap hybrid/rerank lama:
   - pertahankan jika dibutuhkan untuk parity
   - atau catat sebagai follow-up jika tidak blocker untuk cutover pertama

## Rencana Test
- Adaptasi skenario Python yang melindungi:
  - explicit web request
  - docs active blocks auto web
  - no-answer path
  - retrieval success/failure path
  - source handling
- Tambahkan test Laravel untuk:
  - chat dengan dokumen aktif
  - multi-document selection
  - source dokumen tunggal vs kombinasi web/dokumen
  - fallback saat dokumen tidak punya jawaban

## Kriteria Selesai
- Chat dengan dokumen aktif dapat berjalan via Laravel-only dengan quality gate yang memadai.
- Policy dokumen-vs-web tetap konsisten menurut acceptance matrix.
- Source rendering tetap bersih dan familiar di UI.
- Gap retrieval yang tersisa sudah dipersempit ke daftar kecil yang tidak memblokir cutover pertama.
