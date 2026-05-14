# Issue 118: Canvas Memo Editor dan Generate Draft Dokumen

## Latar Belakang
Issue #118 meminta implementasi Tahap 6 untuk membuat draft memo dari instruksi user, membuka hasilnya di editor Word-like berbasis OnlyOffice, lalu menyimpan atau mengunduh dokumen sebagai DOCX. Plan teknis awal tersedia di `issue/issue-canvas-memo-onlyoffice-2026-04-30.md`; dependensi export Fase 2 seperti `phpoffice/phpword` dan `python-docx` sudah tersedia.

## Tujuan
- User terautentikasi dapat membuat memo baru dari jenis, judul, dan konteks singkat.
- Python AI menyediakan endpoint generate body memo dan mengembalikan DOCX.
- Laravel menyimpan memo sebagai resource milik user dengan akses ownership.
- Canvas memo menyiapkan konfigurasi OnlyOffice dengan JWT dan callback save.
- Callback OnlyOffice yang valid menyimpan ulang file DOCX ke storage.

## Ruang Lingkup
- Endpoint Python `POST /api/memos/generate-body` pada document microservice.
- Service Python pembuat DOCX memo dari input terstruktur.
- Tabel/model/policy `memos`.
- Service Laravel untuk generate memo dari Python dan menyimpan DOCX.
- Service JWT OnlyOffice, callback save, signed file route, export DOCX/PDF.
- Livewire page daftar memo dan canvas editor.
- Konfigurasi env dan Docker Compose untuk OnlyOffice.
- Test Python dan Laravel untuk generate, ownership, dan callback JWT.

## Di Luar Scope
- Co-editing multi-user.
- Version history penuh.
- Semua jenis surat dinas.
- Tanda tangan digital.
- Deploy production langsung dari agent.

## Area / File Terkait
- `python-ai/app/routers/memos.py`
- `python-ai/app/services/memo_generation.py`
- `python-ai/app/documents_api.py`
- `laravel/app/Models/Memo.php`
- `laravel/app/Policies/MemoPolicy.php`
- `laravel/app/Services/Memo/MemoGenerationService.php`
- `laravel/app/Services/OnlyOffice/JwtSigner.php`
- `laravel/app/Http/Controllers/Memos/MemoFileController.php`
- `laravel/app/Http/Controllers/OnlyOfficeCallbackController.php`
- `laravel/app/Livewire/Memos/*`
- `laravel/resources/views/livewire/memos/*`
- `laravel/routes/web.php`, `laravel/config/services.php`, `.env.example`
- `docker-compose.yml`, `docker-compose.production.yml`

## Risiko
- OnlyOffice membutuhkan RAM besar; service harus diberi limit dan env jelas.
- Callback file URL dari OnlyOffice harus dipercaya hanya setelah JWT valid.
- Format DOCX hasil AI harus tetap rapi meski LLM memberi output bebas.
- Test tidak menjalankan container OnlyOffice sungguhan, sehingga integrasi iframe tetap perlu QA manual.

## Langkah Implementasi
1. Tambah generator memo Python yang membuat DOCX dengan `python-docx`.
2. Tambah router memo ke document microservice dan test endpoint/routing.
3. Tambah migrasi/model/policy memo di Laravel.
4. Tambah service Laravel untuk memanggil Python, menyimpan DOCX, dan export memo.
5. Tambah signer JWT HS256 untuk konfigurasi OnlyOffice dan callback.
6. Tambah controller file memo signed, callback OnlyOffice, Livewire index/canvas, dan views.
7. Tambah env/compose OnlyOffice dan catatan deploy.
8. Tambah test Laravel untuk ownership, generate flow, dan callback save.
9. Jalankan verifikasi Python dan Laravel relevan.

## Rencana Test
- `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py tests/test_app_routing.py`
- `cd laravel && php artisan test tests/Feature/Memos/MemoCanvasTest.php tests/Feature/Memos/OnlyOfficeCallbackTest.php tests/Feature/Memos/MemoPolicyTest.php tests/Unit/Services/OnlyOffice/JwtSignerTest.php`
- `cd laravel && ./vendor/bin/pint --test`

## Kriteria Selesai
- Endpoint Python memo menghasilkan DOCX valid.
- User hanya dapat melihat/mengunduh memo miliknya.
- Generate memo membuat record dan file DOCX.
- Callback OnlyOffice tanpa JWT valid ditolak dan callback valid menyimpan file.
- Canvas menyiapkan config editor OnlyOffice dengan token.
- Test relevan hijau atau kegagalan lingkungan dicatat jelas.
