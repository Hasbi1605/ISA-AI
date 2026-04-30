# Issue Plan: Canvas Memo Editor (OnlyOffice Word-like) (Fase 3 — Fitur B)

## Latar Belakang
Mentor meminta ISTA AI memiliki fitur "canvas" sampingan yang membantu pegawai menyusun memo: AI tidak hanya menyiapkan heading/kop surat, tapi juga **men-generate body memo lengkap**, lalu user bisa **mengedit di dalam ISTA AI** dengan tampilan dan rasa yang **benar-benar mirip Microsoft Word**. User memilih opsi **OnlyOffice Document Server (Community Edition, AGPL, gratis)** karena memberikan ribbon penuh, page view persis Word, dan menyimpan file dalam format DOCX native.

Saat ini di repo belum ada fitur memo/document generator sama sekali (Tahap 6 di Issue #1 belum dimulai). Dependensi Composer juga belum mencakup `phpoffice/phpword` atau library serupa.

## Tujuan
- User bisa membuat memo baru dari ISTA AI: pilih jenis memo + isi instruksi singkat → AI generate body lengkap → buka di editor Word-like (OnlyOffice) → edit → save sebagai DOCX.
- File memo tersimpan sebagai `.docx` real di Laravel storage, bukan HTML internal.
- Editor di-embed via iframe, ribbon penuh (Home/Insert/Layout/References/Review/View), pagination A4, header/footer, track changes, comments — semua datang gratis dari OnlyOffice.
- Memo bisa diekspor ke PDF (reuse Fase 2) atau di-share sebagai DOCX.

## Ruang Lingkup
- Tambah container `onlyoffice/documentserver` (Community Edition) di `docker-compose.yml` untuk dev dan `docker-compose.production.yml` untuk produksi.
- Tabel baru `memos` untuk metadata + path file DOCX.
- Endpoint Python baru `POST /api/memos/generate-body`: input jenis memo + konteks → output draft DOCX (atau HTML yang dikonversi ke DOCX).
- Komponen Livewire `MemoCanvas` yang embed iframe OnlyOffice editor + form input AI.
- Endpoint Laravel callback yang dipanggil OnlyOffice saat user save (signed dengan JWT shared secret).
- Konfigurasi prompt template generate memo di `python-ai/app/config/prompts/`.

## Di Luar Scope
- Co-editing real-time multi-user (OnlyOffice mendukung, tapi MVP single-user dulu).
- Versioning history per memo (bisa fase berikutnya).
- Template memo untuk semua jenis surat dinas (MVP: 2-3 jenis paling umum, mis. memo internal & nota dinas).
- Editor untuk XLSX/PPTX (OnlyOffice bisa, tapi MVP fokus DOCX memo).
- Integrasi tanda tangan digital.

## Area / File Terkait
- `docker-compose.yml`, `docker-compose.production.yml` (tambah service `onlyoffice`)
- `laravel/database/migrations/` (migrasi baru: `create_memos_table`)
- File baru: `laravel/app/Models/Memo.php`
- File baru: `laravel/app/Livewire/Memos/MemoCanvas.php`
- File baru: `laravel/app/Livewire/Memos/MemoIndex.php`
- File baru: `laravel/app/Http/Controllers/OnlyOfficeCallbackController.php`
- File baru: `laravel/app/Services/OnlyOffice/JwtSigner.php`
- File baru: `laravel/app/Services/Memo/MemoGenerationService.php`
- `laravel/config/ai.php` (tambah seksi `memo` + `onlyoffice`)
- `laravel/routes/web.php`
- `laravel/resources/views/livewire/memos/memo-canvas.blade.php` (baru)
- `python-ai/app/routers/` (router baru `memos.py`)
- File baru: `python-ai/app/services/memo_generation.py`
- `python-ai/app/config/prompts/` (template baru `memo_internal_prompt.txt`, `memo_nota_dinas_prompt.txt`)
- `python-ai/requirements.txt` (`python-docx` — sudah ditambah di Fase 2, reuse)

## Risiko
- Container OnlyOffice idle ~1.5–2 GB RAM. Droplet production sudah punya concern memori (lihat `issue-python-ai-memory-optimization-2026-04-28.md`). Mitigasi: profile usage saat fitur baru deploy, set memory limit di compose, dan dokumentasikan kebutuhan minimum droplet (rekomendasi minimal 4 GB RAM total).
- Limit Community Edition: 20 simultaneous connections. Untuk istana cukup, tapi harus dimonitor; kalau tercapai, request ditolak. Tambah error handling di UI.
- JWT secret OnlyOffice harus disimpan aman di env, tidak hard-coded. Wajib bypass `git` dengan `.env.example`.
- Callback URL OnlyOffice harus reachable dari container OnlyOffice ke Laravel (gunakan service name di compose, bukan `localhost`).
- HTML→DOCX dari hasil AI generation harus rapi. Mitigasi: Python pakai `python-docx` langsung membangun document object (heading, paragraph, table, list) berdasarkan output JSON dari LLM, bukan parsing HTML kompleks.
- Lisensi AGPL: integrasi via API/iframe tanpa modifikasi source = aman untuk pemakaian internal. Dokumentasikan di README.
- Track changes dan comments tersimpan di DOCX, bukan di DB Laravel. Konsekuensi: query/search isi memo via DB tidak praktis. Mitigasi: ekstrak teks ringkasan saat save callback dan simpan di kolom `searchable_text`.

## Langkah Implementasi
1. **Backend Python dulu** (sesuai pola repo):
   - Tulis `python-ai/app/services/memo_generation.py` dengan fungsi `generate_memo(jenis, judul, konteks, lampiran_ids) -> dict` yang panggil LLM via `llm_manager` dengan prompt template, lalu parse output structured JSON menjadi `python-docx` Document object.
   - Tambah router `python-ai/app/routers/memos.py` dengan endpoint `POST /api/memos/generate-body` → return file DOCX binary atau path DOCX yang sudah disimpan ke shared storage.
   - Tambah prompt template di `python-ai/app/config/prompts/memo_*.txt`.
   - Tambah test di `python-ai/tests/test_memo_generation.py`.
2. **Database**:
   - Migrasi `create_memos_table`: kolom `id, user_id, title, jenis (enum), file_path, status (draft|generated|edited|finalized), source_conversation_id NULL, source_document_ids JSON, searchable_text TEXT NULL, created_at, updated_at, deleted_at`.
   - Model `Memo` dengan SoftDeletes, relasi `belongsTo User`.
3. **Infra OnlyOffice**:
   - Tambah service `onlyoffice` di `docker-compose.yml`:
     ```yaml
     onlyoffice:
       image: onlyoffice/documentserver:latest
       environment:
         JWT_ENABLED: "true"
         JWT_SECRET: ${ONLYOFFICE_JWT_SECRET}
       ports:
         - "8080:80"
     ```
   - Tambah env: `ONLYOFFICE_JWT_SECRET`, `ONLYOFFICE_INTERNAL_URL` (untuk callback dari container ke Laravel), `ONLYOFFICE_PUBLIC_URL` (untuk iframe di browser).
   - Update `.env.example` dan dokumen deploy.
4. **Laravel — service & callback**:
   - `JwtSigner`: sign/verify token dengan HS256.
   - `OnlyOfficeCallbackController`: terima POST dari OnlyOffice (status 0/1/2/3/4/6/7), download file dari URL yang dikirim OnlyOffice saat status 2 (ready to save), simpan ke storage Laravel dengan path `memos/{user_id}/{memo_id}.docx`. Update kolom `status` dan `searchable_text`.
   - `MemoGenerationService`: panggil endpoint Python, simpan DOCX awal ke storage, buat baris `memos`.
5. **Laravel — UI**:
   - `MemoIndex` Livewire: list memo user, tombol "New Memo".
   - `MemoCanvas` Livewire: form input (jenis + judul + konteks + checkbox lampirkan dokumen) → klik "Generate" → call service → arahkan ke editor.
   - View `memo-canvas.blade.php` render iframe ke OnlyOffice public URL dengan config JSON yang berisi document URL (Laravel signed URL) + JWT.
   - Tombol export PDF (reuse Fase 2 endpoint).
6. **Routes & policy**:
   - `Route::get('/memos', MemoIndex::class)`, `Route::get('/memos/{memo}', MemoCanvas::class)`, `Route::post('/onlyoffice/callback/{memo}', ...)`, `Route::get('/memos/{memo}/file', ...)` (signed).
   - Tambah `MemoPolicy` untuk ownership.
7. **Testing**:
   - Python: `test_memo_generation.py` — fixture LLM mock, assert DOCX dihasilkan dengan struktur sesuai input.
   - Laravel: `MemoCanvasTest` — happy path generate; `OnlyOfficeCallbackTest` — JWT verify + save flow; `MemoPolicyTest` — akses kontrol.

## Rencana Test
- Python:
  - `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py`
- Laravel:
  - `cd laravel && php artisan test tests/Feature/Memos/MemoCanvasTest.php tests/Feature/Memos/OnlyOfficeCallbackTest.php tests/Unit/Services/Memo/MemoGenerationServiceTest.php`
  - Lint: `cd laravel && ./vendor/bin/pint --test`
- Manual QA: jalankan `docker compose up`, buka `/memos`, generate memo, edit di iframe OnlyOffice, klik save, verifikasi file DOCX tersimpan dan reload menampilkan perubahan.

## Kriteria Selesai
- User bisa generate body memo via AI dan langsung edit di canvas Word-like (OnlyOffice).
- File memo tersimpan sebagai DOCX real di Laravel storage.
- Save dari iframe OnlyOffice ter-callback ke Laravel dan persist (verifikasi via test).
- JWT validation aktif; request tanpa token valid ditolak.
- User bisa export memo ke PDF.
- Test Python dan Laravel hijau.
- Container OnlyOffice tercatat di docker-compose dan dokumen deploy.
- README/CHANGELOG mencantumkan fitur baru + catatan lisensi AGPL OnlyOffice.
