# Issue Plan: Output Multi-Format (Ekstraksi Tabel & Konversi Antar-Format) (Fase 2 — Fitur A)

## Latar Belakang
Mentor meminta ISTA AI bisa menerima dokumen dalam satu format (mis. PDF) dan mengeluarkan ulang dalam format lain (mis. tabel di PDF → spreadsheet Excel; ringkasan AI → DOCX/PDF). Saat ini ISTA AI hanya menerima upload dan memakai isi dokumen sebagai konteks RAG; tidak ada jalur ekstraksi tabel maupun export ke format lain. Pegawai istana terbiasa bekerja dengan output Excel/Word, sehingga fitur ini menambah nilai praktis besar dengan effort moderate.

## Tujuan
- User bisa mengekstrak tabel dari PDF/DOCX yang sudah di-upload dan men-download hasilnya sebagai XLSX, CSV, atau DOCX.
- AI bisa menghasilkan output terstruktur (tabel, list, paragraf) yang bisa langsung di-export ke format yang dipilih user.
- Pipeline export reusable: nantinya dipakai juga oleh Canvas Memo Editor (Fase 3) untuk export memo ke DOCX/PDF.
- Di halaman chat, setiap jawaban AI memiliki toolbar aksi di bawah output untuk `salin`, `bagikan ke WhatsApp`, dan `ekspor`.

## Ruang Lingkup
- Tambah endpoint Python `POST /api/documents/extract-tables`: input file id → output JSON list of tables (header + rows).
- Tambah endpoint Python `POST /api/documents/export`: input `{content_html, target_format: docx|pdf|xlsx|csv}` → return binary file.
- Tambah service Laravel `DocumentExportService` yang membungkus panggilan ke Python dan stream hasil ke browser sebagai download.
- UI: toolbar aksi di bawah jawaban AI pada halaman chat dengan tombol `salin`, `bagikan`, dan `ekspor`; export action dipakai juga untuk tabel yang dihasilkan dari dokumen.

## Di Luar Scope
- OCR untuk PDF hasil scan (perlu tambah pipeline pytesseract/paddleocr — bisa fase lanjut).
- Export ke format lain di luar `docx, pdf, xlsx, csv` di iterasi pertama.
- Editor inline untuk hasil ekstraksi (user hanya download, tidak edit di app).
- Integrasi dengan Google Drive (itu Fase 4).

## Area / File Terkait
- `python-ai/app/routers/documents.py` (tambah dua route baru)
- File baru: `python-ai/app/services/table_extraction.py`
- File baru: `python-ai/app/services/document_export.py`
- `python-ai/requirements.txt` (tambah `pdfplumber`, `python-docx`, `openpyxl`, `weasyprint`)
- `laravel/app/Services/AIService.php` atau service baru `DocumentExportService.php`
- `laravel/app/Http/Controllers/DocumentExportController.php` (baru)
- `laravel/routes/web.php`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php` — tambah toolbar aksi di bawah bubble jawaban AI
- `laravel/resources/js/chat-page.js` — perilaku copy/share/export untuk jawaban AI

## Risiko
- Akurasi ekstraksi tabel bervariasi: PDF dengan border jelas akurat; PDF tanpa border atau scan menghasilkan output kurang rapi. Mitigasi: tampilkan hasil ekstraksi sebagai preview terlebih dahulu sebelum download, dan beri label confidence/peringatan.
- File PDF besar dapat membuat `pdfplumber` lambat. Mitigasi: timeout per-halaman, jalankan via subprocess (pola `document_runner.py`), atau batasi halaman yang di-ekstrak per request.
- `weasyprint` punya dependensi sistem (cairo, pango, libffi). Wajib dipastikan ada di Dockerfile Python AI.
- Ukuran response export besar bisa membebani memori. Mitigasi: stream response chunked dari Python ke Laravel ke browser.
- Konflik dimensi vektor / pipeline embedding tidak terdampak (export tidak menyentuh ChromaDB).

## Langkah Implementasi
1. Tambah dependensi Python: `pdfplumber`, `python-docx`, `openpyxl`, `weasyprint` di `python-ai/requirements.txt`. Update Dockerfile bila perlu library system.
2. Tulis `python-ai/app/services/table_extraction.py` dengan fungsi `extract_tables(file_path) -> list[Table]` menggunakan `pdfplumber` (PDF) dan `python-docx` (DOCX). Output schema: `{"tables": [{"header": [...], "rows": [[...]]}]}`.
3. Tulis `python-ai/app/services/document_export.py` dengan `export(content_html, target) -> bytes`:
   - `docx`: parse HTML subset → `python-docx` document.
   - `pdf`: render HTML → `weasyprint`.
   - `xlsx`: parse list-of-tables JSON → `openpyxl`.
   - `csv`: parse list-of-tables JSON → `csv` writer.
4. Tambah endpoint di `python-ai/app/routers/documents.py`:
   - `POST /api/documents/{document_id}/extract-tables` → return JSON.
   - `POST /api/documents/export` → body `{content, target_format}` → return file stream + Content-Disposition.
5. Tambah `DocumentExportService` di Laravel yang panggil endpoint Python via Guzzle (reuse pola `AIService.php`).
6. Tambah `DocumentExportController` dengan action `extractTables(Document $d)` dan `export(Request $r)`. Wajib policy ownership.
7. Tambah toolbar aksi di bawah bubble jawaban AI untuk salin, bagikan via WhatsApp, dan ekspor.
   - Export dari bubble chat memakai payload HTML jawaban AI.
   - Export dari tabel dokumen memakai payload tabel hasil ekstraksi.
8. Tambah test:
   - Python: unit test ekstraksi tabel dari fixture PDF/DOCX kecil.
   - Python: unit test export ke 4 target format dengan input HTML/JSON sederhana.
   - Laravel: feature test akses kontrol + happy path download XLSX dari PDF dengan fixture.

## Rencana Test
- Python:
  - `cd python-ai && source venv/bin/activate && pytest tests/test_table_extraction.py tests/test_document_export.py`
- Laravel:
  - `cd laravel && php artisan test tests/Feature/Documents/DocumentExportTest.php`
  - Lint: `cd laravel && ./vendor/bin/pint --test`

## Kriteria Selesai
- Endpoint `/api/documents/{id}/extract-tables` mengembalikan JSON tabel valid untuk fixture PDF dan DOCX.
- Endpoint `/api/documents/export` menghasilkan file binary valid untuk 4 target format.
- User bisa men-download tabel dari PDF sebagai XLSX dan ringkasan chat sebagai DOCX/PDF lewat UI.
- Akses kontrol terjaga (test coverage).
- Test Python dan Laravel hijau.
- Tidak ada regresi di pipeline upload/RAG.

## Follow-up 2026-05-01: Konversi Viewer untuk Spreadsheet/CSV
- Viewer export harus berfungsi sebagai konversi antar-format dokumen umum, bukan hanya output AI atau ekstraksi tabel PDF/DOCX.
- Sumber PDF/DOCX tetap boleh memakai jalur `extract-tables` khusus saat target XLSX/CSV agar tabel lebih terstruktur.
- Sumber XLSX/CSV harus memakai jalur `content-html` penuh untuk semua target (`pdf`, `docx`, `xlsx`, `csv`) supaya bisa konversi spreadsheet/CSV ke PDF, DOCX, XLSX, atau CSV tanpa kehilangan isi.
- CSV ikut didukung sebagai format upload/preview/export agar workflow seperti CSV -> XLSX dan CSV -> PDF/DOCX tersedia.

## Follow-up 2026-05-01: Jalur Konversi File Asli
- Export viewer dokumen tidak boleh bergantung pada HTML ekstraksi jika user meminta konversi file, karena layout PDF/Word/Excel bisa rusak.
- Tambahkan endpoint konversi file asli yang menerima dokumen tersimpan dan target format.
- Gunakan LibreOffice headless untuk DOCX/XLSX/CSV -> PDF agar layout, page setup, font, dan tabel lebih terjaga.
- Gunakan `pdf2docx` untuk PDF -> DOCX sebelum fallback ke ekstraksi teks.
- Gunakan pembentuk DOCX khusus spreadsheet untuk XLSX/CSV -> DOCX agar tabel tetap terbaca dan tidak berubah menjadi paragraf acak.
