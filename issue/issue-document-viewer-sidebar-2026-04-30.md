# Issue Plan: Document Viewer Dinamis di Sidebar (Fase 1 — Fitur C)

## Latar Belakang
Saat ini sidebar kanan chat di `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php` sudah menampilkan daftar "Semua Dokumen Saya" milik user (status, bulk select untuk RAG context, hapus). Yang belum tersedia adalah mekanisme **membaca** isi dokumen langsung di dalam aplikasi ISTA AI. User saat ini hanya bisa men-toggle dokumen sebagai konteks RAG, tetapi tidak bisa membuka dan membaca isinya tanpa download manual.

Mentor meminta sidebar berfungsi sebagai "tempat dokumen user" yang interaktif: user bisa klik dokumen → langsung melihat isinya di area utama atau panel preview, tanpa pindah halaman dan tanpa download.

## Tujuan
- User dapat membuka dan membaca isi dokumen yang sudah di-upload (PDF, DOCX, XLSX) langsung di dalam ISTA AI.
- Tidak mengganggu alur chat + RAG context yang sudah ada.
- Pre-render konten ke HTML saat ingest untuk DOCX/XLSX sehingga preview cepat dan stabil.
- Menjamin akses kontrol per user (user hanya boleh melihat dokumen miliknya sendiri).

## Ruang Lingkup
- Menambah kolom `preview_html_path` dan `preview_status` di tabel `documents`.
- Menambah service Laravel atau task Python untuk render DOCX/XLSX → HTML pas pipeline ingest selesai.
- Menambah endpoint Laravel untuk stream PDF inline dan serve HTML preview untuk DOCX/XLSX.
- Menambah komponen Livewire viewer (modal/panel) yang dipanggil dari sidebar.
- Menambah tombol "Baca" / "Preview" per item di `chat-right-sidebar.blade.php` dan `document-index.blade.php`.

## Di Luar Scope
- Edit dokumen di dalam viewer (untuk edit, gunakan Canvas Memo Editor — Fase 3).
- Pencarian highlight match dalam dokumen (bisa fase berikutnya, reuse `document_chunks`).
- Konversi format antar dokumen (itu Fase 2 — Multi-Format Export).
- Preview file dari Google Drive (itu Fase 4).
- Anotasi/komentar pada dokumen.

## Area / File Terkait
- `laravel/app/Models/Document.php`
- `laravel/database/migrations/` (migration baru: `add_preview_columns_to_documents_table`)
- `laravel/app/Services/DocumentLifecycleService.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/app/Livewire/Documents/DocumentIndex.php`
- `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php`
- `laravel/resources/views/livewire/documents/document-index.blade.php`
- File baru: `laravel/app/Livewire/Documents/DocumentViewer.php`
- File baru: `laravel/app/Http/Controllers/DocumentPreviewController.php`
- `python-ai/app/routers/documents.py` (opsional, kalau render dilakukan di Python pas ingest)

## Risiko
- DOCX dengan layout kompleks (gambar embedded, kolom, footnote, equation) hasil render HTML tidak akan pixel-perfect. Perlu disikapi dengan harapan yang tepat — preview ini untuk **baca**, bukan untuk **layout review**.
- File PDF besar (>20 MB) saat di-stream inline bisa mengganggu memori jika di-buffer. Wajib pakai streamed response.
- Akses kontrol bocor jika endpoint preview tidak memvalidasi `document.user_id === auth()->id()`. Wajib dicek di controller dan service.
- Pre-render saat ingest menambah waktu pipeline. Mitigasi: render asynchronous lewat queue setelah dokumen status `ready`, supaya tidak memperlambat status `ready` itu sendiri.
- Memori Python container saat parsing DOCX/XLSX panjang. Mitigasi: subprocess pattern yang sudah ada di `document_runner.py`.

## Langkah Implementasi
1. Tambah migrasi `add_preview_columns_to_documents_table`: kolom `preview_html_path VARCHAR(500) NULL`, `preview_status ENUM('pending','ready','failed') DEFAULT 'pending'`. Update fillable di `Document.php`.
2. Tambah service `DocumentPreviewRenderer` di Laravel yang:
   - Untuk PDF: tidak render HTML; preview pakai stream inline.
   - Untuk DOCX: convert ke HTML via library PHP (`phpoffice/phpword` atau panggil endpoint Python).
   - Untuk XLSX: convert ke HTML tabel via `phpoffice/phpspreadsheet` atau endpoint Python.
3. Pasang job Laravel `RenderDocumentPreviewJob` yang dijalankan setelah dokumen status `ready` (event listener atau dispatch di akhir lifecycle).
4. Tambah controller `DocumentPreviewController` dengan dua action:
   - `stream(Document $document)`: untuk PDF, return `StreamedResponse` dengan header `Content-Disposition: inline`.
   - `html(Document $document)`: return file HTML preview dari `preview_html_path`. Wajib check ownership.
5. Daftarkan route dengan middleware `auth` + policy `view`. Tambah `DocumentPolicy` jika belum ada.
6. Buat komponen Livewire `DocumentViewer` (modal full-screen / side-panel) yang load preview by document id dan render di iframe untuk PDF / dalam div untuk HTML.
7. Tambah tombol "Baca" pada item dokumen di `chat-right-sidebar.blade.php` dan `document-index.blade.php`. Klik → emit event Livewire ke `DocumentViewer`.
8. Tambah test Laravel:
   - Feature test akses kontrol (user A tidak bisa preview dokumen user B).
   - Feature test stream PDF inline.
   - Feature test render HTML untuk DOCX & XLSX (dengan fixture file kecil).
   - Unit test `DocumentPreviewRenderer` mapping mime type.

## Rencana Test
- Laravel:
  - `cd laravel && php artisan test tests/Feature/Documents/DocumentPreviewTest.php tests/Unit/Services/DocumentPreviewRendererTest.php`
  - Lint: `cd laravel && ./vendor/bin/pint --test`
- Python (jika ada perubahan):
  - `cd python-ai && source venv/bin/activate && pytest tests/`

## Kriteria Selesai
- User bisa klik dokumen di sidebar dan langsung baca isinya tanpa download.
- PDF tampil di iframe inline dengan lazy load.
- DOCX dan XLSX di-pre-render ke HTML saat ingest dan ditampilkan di viewer.
- Akses kontrol terjaga: user A tidak bisa lihat dokumen user B (test coverage).
- Status preview (`pending|ready|failed`) ditampilkan di UI; jika `failed` ada tombol retry.
- Test Laravel hijau.
- Tidak ada regresi di alur upload / chat / RAG context selection yang sudah ada.
