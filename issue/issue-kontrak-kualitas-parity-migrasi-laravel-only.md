# Issue Plan: Kontrak Kualitas dan Acceptance Matrix Migrasi Laravel-Only

## Latar Belakang
Issue ini adalah turunan awal dari blueprint migrasi AI service ke Laravel-only pada issue `#67`. Sebelum memindahkan capability dari `python-ai` ke Laravel, sistem perlu memiliki kontrak kualitas yang eksplisit agar migrasi tidak menurunkan mutu yang sudah ada.

Saat ini kualitas perilaku sistem tersebar di beberapa tempat:

- test Python untuk prompt, routing, retrieval, dan policy
- test Laravel untuk upload/delete/process dokumen dan metadata stream
- perilaku implisit di `python-ai/app/main.py`, `python-ai/app/services/rag_policy.py`, `python-ai/app/services/rag_retrieval.py`, dan `laravel/app/Services/ChatOrchestrationService.php`

Tanpa kontrak kualitas yang jelas, migrasi berisiko terlihat “berhasil” secara teknis tetapi diam-diam mengubah jawaban, source, policy dokumen-vs-web, atau perilaku upload/retrieval yang dirasakan user.

## Tujuan
- Mengubah kualitas sistem saat ini menjadi acceptance matrix yang eksplisit.
- Mendefinisikan perilaku mana yang wajib dipertahankan 1:1 saat migrasi ke Laravel.
- Menentukan drift yang masih dapat diterima dan drift yang dianggap blocker.
- Menjadi gate utama sebelum issue implementasi migrasi mulai dikerjakan.

## Ruang Lingkup
- Inventaris seluruh perilaku penting pada chat umum, dokumen, web search, summarization, source rendering, dan lifecycle dokumen.
- Pemetaan test Python dan Laravel yang sudah ada ke acceptance criteria migrasi.
- Klasifikasi perilaku menjadi:
  - wajib setara
  - boleh berubah dengan pembenaran eksplisit
  - boleh ditunda ke fase berikutnya
- Penyusunan fixture dan dataset uji untuk membandingkan runtime lama dan runtime Laravel baru.
- Penyusunan rubric evaluasi hasil shadow/dual-run.

## Di Luar Scope
- Implementasi migrasi runtime baru.
- Upgrade PHP, Laravel, atau instalasi `laravel/ai`.
- Perubahan produk baru di luar menjaga parity kualitas.
- Penghapusan `python-ai`.

## Area / File Terkait
- `python-ai/tests/test_prompt_contracts.py`
- `python-ai/tests/test_prompt_eval_scenarios.py`
- `python-ai/tests/test_ista_ai.py`
- `laravel/tests/Feature/Chat/ChatStreamMetadataTest.php`
- `laravel/tests/Feature/Chat/DocumentUploadTest.php`
- `laravel/tests/Feature/Documents/DocumentDeletionTest.php`
- `laravel/tests/Feature/Jobs/ProcessDocumentTest.php`
- `laravel/app/Services/ChatOrchestrationService.php`
- `python-ai/app/main.py`
- `python-ai/app/services/rag_policy.py`

## Risiko
- Perilaku penting yang selama ini hanya “kebetulan benar” di runtime lama tidak ikut terdokumentasi.
- Acceptance matrix terlalu longgar sehingga regresi lolos.
- Acceptance matrix terlalu ketat pada area yang memang akan berubah karena provider-managed architecture.
- Shadow comparison sulit dibaca jika tidak ada fixture dan kategori evaluasi yang konsisten.

## Langkah Implementasi
1. Inventaris seluruh capability yang saat ini dipakai user:
   - chat tanpa dokumen
   - chat dengan dokumen
   - web search realtime
   - upload/process/delete dokumen
   - summarization
   - source rendering
2. Petakan test yang sudah ada ke perilaku produk yang dijaga.
3. Tambahkan daftar perilaku implisit yang belum tertangkap test tetapi terlihat penting dari code path saat ini.
4. Tetapkan kategori parity:
   - exact parity
   - behavior parity
   - acceptable drift
5. Tetapkan fixture/data uji yang akan dipakai ulang di issue migrasi berikutnya.
6. Tetapkan rubric evaluasi shadow mode:
   - kualitas jawaban
   - kelengkapan source
   - policy dokumen-vs-web
   - kestabilan ingest/summarization
7. Simpulkan daftar blocker kualitas yang harus lolos sebelum cutover.

## Rencana Test
- Jalankan dan dokumentasikan baseline test Python yang relevan.
- Jalankan dan dokumentasikan baseline test Laravel yang relevan.
- Buat daftar skenario manual yang harus dipakai ulang saat dual-run:
  - salam/chat umum
  - pertanyaan realtime
  - pertanyaan dengan dokumen aktif
  - pertanyaan yang tidak ada di dokumen
  - upload PDF/DOCX/XLSX
  - summarization dokumen besar
- Pastikan hasil akhir issue ini berupa acceptance matrix yang bisa dipakai sebagai checklist implementasi.

## Kriteria Selesai
- Ada acceptance matrix migrasi yang jelas dan bisa dipakai sebagai gate.
- Semua capability penting saat ini sudah dipetakan ke kontrak kualitas.
- Sudah ada klasifikasi mana yang harus exact parity dan mana yang boleh drift.
- Sudah ada fixture/dataset uji yang akan dipakai di issue implementasi berikutnya.
- Issue implementasi berikutnya dapat merujuk kontrak ini tanpa menebak standar kualitas.
