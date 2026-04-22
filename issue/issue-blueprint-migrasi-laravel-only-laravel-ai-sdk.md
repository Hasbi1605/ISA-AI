# Issue Plan: Blueprint Migrasi Bertahap AI Service ke Laravel-Only dengan Laravel AI SDK

## Latar Belakang
Saat ini aplikasi memakai arsitektur hybrid `Laravel + python-ai`. Laravel menangani UI, auth, queue, dan metadata dokumen, sedangkan `python-ai` menangani chat AI, ingest dokumen, retrieval RAG, summarization, web-search policy, dan vector store. Boundary ini saat ini terlihat jelas pada:

- `laravel/app/Services/AIService.php`
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/app/Services/DocumentLifecycleService.php`
- `python-ai/app/main.py`
- `python-ai/app/routers/documents.py`
- `python-ai/app/services/rag_ingest.py`

Struktur tersebut membuat deploy lebih berat karena Python membawa dependency parsing/OCR/vector yang besar, runtime terpisah, healthcheck terpisah, serta lifecycle dokumen yang harus melewati boundary HTTP internal. Repo juga masih berada di `PHP ^8.2` dan `Laravel ^11.31` pada `laravel/composer.json`, sementara `laravel/ai` resmi memerlukan jalur platform yang kompatibel dengan `PHP 8.3` dan versi Laravel yang didukung.

## Tujuan
- Menyusun blueprint migrasi yang decision-complete untuk memindahkan capability AI utama ke Laravel-only.
- Menetapkan target arsitektur `provider-managed` sebagai default untuk chat, dokumen, retrieval, dan summarization.
- Menentukan prasyarat platform, fase migrasi, strategi cutover, fallback, dan kriteria dekomisioning `python-ai`.
- Memecah migrasi besar menjadi issue turunan yang lebih kecil dan aman diimplementasikan.

## Ruang Lingkup
- Inventaris tanggung jawab yang saat ini masih berada di `python-ai` dan pemetaan satu per satu ke target ekosistem Laravel.
- Definisi target boundary Laravel untuk:
  - chat generation
  - document ingestion
  - retrieval dan source handling
  - summarization
  - source policy dokumen vs realtime/web
- Penetapan jalur upgrade minimum agar `laravel/ai` dapat dipakai secara resmi.
- Penentuan strategi migrasi data dokumen dan vector context, dengan default `re-index / re-ingest`, bukan migrasi langsung dari Chroma.
- Penyusunan fase delivery:
  - foundation/platform
  - dual-run terbatas
  - cutover
  - penghapusan Python service
- Definisi acceptance criteria, test matrix, observability minimum, dan rollback point per fase.

## Di Luar Scope
- Implementasi kode migrasi pada issue ini.
- Instalasi package, upgrade framework, atau perubahan infra production langsung.
- Refactor UI/UX chat yang tidak dibutuhkan oleh migrasi arsitektur.
- Penggantian database utama aplikasi dari MySQL.
- Menjadikan `pgvector` self-hosted sebagai target utama. Opsi ini hanya dicatat sebagai fallback jika provider-managed terbukti tidak menutup kebutuhan penting.

## Perubahan Interface yang Harus Didefinisikan
- Blueprint ini harus mendefinisikan target pengganti untuk boundary internal HTTP yang saat ini dipakai Laravel ke Python:
  - `/api/chat`
  - `/api/documents/process`
  - `/api/documents/{filename}`
  - `/api/documents/summarize`
- Hasil akhir blueprint harus menetapkan service boundary Laravel yang akan menjadi pengganti internal tersebut, sekaligus kapan compatibility layer lama boleh dipertahankan sementara dan kapan harus dihapus.

## Area / File Terkait
- `laravel/app/Services/AIService.php`
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/app/Services/DocumentLifecycleService.php`
- `python-ai/app/main.py`
- `python-ai/app/routers/documents.py`
- `python-ai/app/services/rag_ingest.py`
- `python-ai/app/services/rag_retrieval.py`
- `python-ai/app/services/rag_policy.py`
- `docker-compose.yml`
- `laravel/composer.json`
- `python-ai/requirements.txt`

## Risiko
- Upgrade ke platform kompatibel `laravel/ai` bisa membuka kerja tambahan di luar area AI.
- Paritas ingest dokumen mungkin tidak 1:1, terutama untuk PDF scan/OCR dan format non-PDF.
- Provider-managed AI/RAG mengurangi beban VPS tetapi menambah ketergantungan vendor dan potensi perubahan biaya.
- Source metadata, perilaku dokumen-vs-web, dan formatting rujukan bisa berubah jika tidak didefinisikan eksplisit.
- Re-index dokumen lama bisa memerlukan job migrasi terpisah dan kontrol rollout yang hati-hati.

## Langkah Implementasi
1. Petakan semua capability Python saat ini menjadi capability target Laravel atau provider-managed.
2. Tetapkan prerequisite platform, termasuk target versi PHP/Laravel dan dampak upgrade paket inti.
3. Definisikan target service boundary Laravel beserta kontrak input/output minimum untuk chat, ingest, retrieval, summarization, dan sources.
4. Tentukan fitur yang harus tetap setara saat cutover pertama, dan fitur yang boleh ditunda ke fase lanjutan.
5. Susun strategi dual-run atau canary untuk memverifikasi hasil Laravel-only tanpa memutus flow yang berjalan.
6. Tetapkan strategi re-ingest dokumen dan aturan cleanup saat `python-ai` dan Chroma dinonaktifkan.
7. Pecah hasil blueprint menjadi issue turunan implementasi yang kecil, berurutan, dan bisa diverifikasi.

## Rencana Test
Blueprint ini harus menghasilkan test matrix implementasi minimal untuk skenario berikut:

- chat tanpa dokumen
- chat dengan dokumen aktif
- upload PDF, DOCX, dan XLSX
- dokumen besar
- PDF scan atau fallback OCR jika masih dibutuhkan
- summarization dokumen `ready`
- delete dokumen dan cleanup artefak terkait
- mode dokumen-first vs realtime/web
- source rendering dan rujukan yang tetap konsisten
- fallback atau rollback saat provider atau integrasi baru gagal

## Kriteria Selesai
- Ada blueprint migrasi yang jelas, decision-complete, dan dapat langsung dipecah menjadi issue implementasi.
- Target arsitektur `Laravel-only + provider-managed AI/RAG` sudah dinyatakan sebagai default beserta alasan dan batasannya.
- Prasyarat upgrade platform sudah jelas.
- Fase migrasi, cutover, rollback, dan dekomisioning `python-ai` sudah ditentukan.
- Test matrix dan acceptance criteria untuk implementasi sudah tersedia.
- Daftar issue turunan implementasi sudah terdefinisi dari hasil blueprint ini.

## Asumsi dan Default
- Bahasa issue: Indonesia.
- Label default: `enhancement`.
- Scope issue pertama adalah blueprint migrasi, bukan implementasi kode.
- Jalur utama yang dipilih adalah `provider-managed`, bukan `pgvector` self-hosted.
- Jika ada gap penting yang belum tertutup oleh provider-managed, blueprint ini harus mencatat fallback terkecil yang tetap menjaga arah Laravel-only.
