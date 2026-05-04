# Issue Plan: Integrasi Google Drive Kantor (Upload/Download Terpusat) (Fase 4 — Fitur D)

## Latar Belakang
Issue #1 Tahap 7 meminta integrasi Google Drive (dan OneDrive) supaya pegawai bisa langsung memproses file dari cloud storage tanpa download-upload manual. User memilih **Google Drive kantor saja dulu** sebagai scope MVP fase ini. Drive yang dipakai bukan akun Drive masing-masing user, melainkan satu Drive/folder kantor yang dapat diakses semua user ISTA AI sesuai permission aplikasi.

Pendekatan praktis: implementasi **direct Google Drive API di Laravel** (tanpa MCP wrapper di MVP), memakai `google/apiclient` dengan credential server-side untuk Drive kantor. Opsi yang paling cocok adalah **Google service account** yang diberi akses ke folder Shared Drive / folder Drive kantor. Flysystem tidak dijadikan adapter utama karena kebutuhan fitur mencakup metadata Drive, folder picker, permission guard aplikasi, download file sumber, dan upload/export hasil kembali ke Drive. MCP wrapper bisa ditambahkan di iterasi berikutnya tanpa mengubah kontrak service.

## Tujuan
- Admin/devops mengonfigurasi credential Drive kantor satu kali di server.
- User bisa browse file/folder Drive kantor di dalam ISTA AI dan pilih file untuk diproses pipeline RAG.
- File yang dipilih di-download sementara → diproses oleh pipeline ingest yang sudah ada (Tahap 3 — chunk + embed + ChromaDB) → file temporary dihapus.
- User bisa meng-upload hasil olahan ISTA AI kembali ke Google Drive, minimal untuk output yang sudah ada seperti hasil export dokumen/tabel atau memo DOCX/PDF.
- User ISTA AI tidak perlu connect/disconnect akun Google pribadi.
- File hasil ingest dari Drive ditandai `source_provider = google_drive` agar bisa dibedakan dari upload manual.
- File hasil upload ke Drive ditandai dengan metadata asal/output agar bisa dilacak dari ISTA AI.

## Ruang Lingkup
- Tambah dependensi `google/apiclient` untuk Drive API read/write.
- Konfigurasi credential Drive kantor via env/secret, misalnya path JSON service account atau JSON base64.
- Kolom baru `source_provider`, `source_external_id`, `source_synced_at` di tabel `documents`.
- Metadata output Drive untuk mencatat file hasil upload, misalnya tabel baru `cloud_storage_files` atau kolom relasi yang scoped ke dokumen/memo/export terkait.
- Komponen picker Google Drive yang tertanam di halaman `/chat` untuk list & pilih file dari folder kantor yang diizinkan.
- Service Laravel untuk list, download sementara, upload file hasil, dan membaca metadata.
- UI aksi "Upload ke Google Drive" pada output yang relevan:
  - Tombol logo Google Drive di composer chat untuk mengambil file Drive sebagai dokumen chat.
  - Tombol Google Drive di toolbar jawaban AI, di sebelah WhatsApp, dengan popup pilihan format sebelum upload.
  - Tombol terpisah "Upload ke GDrive Kantor" di preview dokumen, di sebelah tombol "Ekspor".

## Di Luar Scope
- Integrasi OneDrive (akan jadi fase berikutnya jika permintaan tetap ada).
- MCP wrapper (akan jadi fase berikutnya, tidak wajib untuk MVP).
- Sync otomatis perubahan file (MVP: tombol "Refresh manual"; webhook/push notification fase berikutnya).
- Akses file Google Workspace (Docs/Sheets/Slides) — MVP fokus PDF/DOCX/XLSX di Drive. Google Docs perlu export khusus, bisa fase lanjut.
- Akun Google Drive pribadi per user.
- OAuth connect/disconnect Google per user.
- Sinkronisasi dua arah otomatis. MVP cukup aksi manual: ambil dari Drive dan simpan hasil ke Drive.
- Editing file Drive secara langsung dari ISTA AI. MVP upload output baru atau overwrite hanya jika aman dan eksplisit.
- Halaman Google Drive standalone seperti `/cloud-storage` atau `/cloud-storage/google-drive`. Integrasi user-facing harus berada di `/chat`.

## Area / File Terkait
- `laravel/composer.json` (tambah `google/apiclient`)
- `laravel/config/services.php` atau `laravel/config/cloud-storage.php` (tambah konfigurasi Drive kantor)
- `laravel/database/migrations/` (migrasi baru: `add_source_columns_to_documents_table`, metadata file Drive bila diperlukan)
- File baru: `laravel/app/Livewire/Chat/GoogleDrivePicker.php`
- File baru: `laravel/app/Services/CloudStorage/GoogleDriveService.php`
- `laravel/app/Services/DocumentLifecycleService.php` (tambah method `ingestFromCloud`)
- `laravel/app/Services/DocumentExportService.php` dan/atau service memo/export terkait (tambah aksi upload hasil ke Drive)
- `laravel/routes/web.php`
- `laravel/resources/views/livewire/chat/google-drive-picker.blade.php` (baru)
- `laravel/resources/views/livewire/chat/partials/chat-composer.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`
- `laravel/resources/views/livewire/documents/document-viewer.blade.php`
- `.env.example` (tambah `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON`, `GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH`, `GOOGLE_DRIVE_ROOT_FOLDER_ID`, opsional `GOOGLE_DRIVE_SHARED_DRIVE_ID`)

## Risiko
- Credential service account bocor dari env/server. Mitigasi: jangan simpan secret di DB, jangan log credential, dokumentasikan secret management, dan gunakan akses folder minimum.
- Semua user aplikasi berbagi Drive kantor yang sama. Mitigasi: enforce authorization di aplikasi sebelum list/download/upload, dan batasi root folder ke folder khusus ISTA AI.
- File besar di Drive (>50 MB) — apply same upload limit dengan upload manual (50 MB seperti policy existing). Reject di awal.
- Permission Drive kantor salah. Mitigasi: service account hanya diberi akses ke folder/Shared Drive yang memang dipakai ISTA AI, bukan seluruh Drive organisasi.
- Jika memakai Shared Drive, service account harus menjadi member Shared Drive atau folder target harus dishare ke service account.
- File Google Docs bukan binary native — perlu `export_media` dengan mime tertentu (`application/pdf` atau DOCX). MVP: skip Google Docs files dengan UI message yang jelas, atau export sebagai PDF default.

## Langkah Implementasi
1. **Setup Google Cloud Console / Drive kantor** (manual, dokumentasikan):
   - Project → enable Drive API → buat Service Account.
   - Buat key JSON service account, simpan sebagai secret server (`GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON` atau file path aman).
   - Share folder Drive kantor / Shared Drive target ke email service account dengan permission sesuai kebutuhan (`Viewer` untuk download-only, `Content manager`/`Editor` untuk upload).
   - Catat root folder id ke `.env` sebagai `GOOGLE_DRIVE_ROOT_FOLDER_ID`.
2. **Database**:
   - Migrasi `add_source_columns_to_documents_table`: `source_provider VARCHAR(20) DEFAULT 'local'`, `source_external_id VARCHAR(255) NULL`, `source_synced_at TIMESTAMP NULL`. Update `Document` fillable.
   - Tambah penyimpanan metadata file Drive hasil upload, misalnya `cloud_storage_files`: `user_id, provider, external_id, name, mime_type, web_view_link, local_type, local_id, direction (import|export), created_at, updated_at`.
3. **Drive credential flow**:
   - Install `google/apiclient`.
   - `GoogleDriveService` membuat `Google\Client` dari service account credential server-side.
   - Tambah config `cloud-storage.google_drive.root_folder_id` dan validasi config saat service dipakai.
   - Status konfigurasi ditampilkan secara kontekstual di modal picker chat tanpa menampilkan credential.
4. **Drive picker di chat**:
   - `GoogleDriveService::listFiles($parentFolderId, $pageToken)` panggil Drive API v3 dalam root folder kantor yang diizinkan.
   - `GoogleDriveService::downloadToTemp($fileId)`: download ke `storage/app/tmp/cloud/{uuid}` lalu hapus setelah ingest selesai.
   - `GoogleDriveService::uploadFromPath($localPath, $fileName, $mimeType, $parentFolderId = null)`: upload hasil ke Drive dan simpan metadata file hasil upload.
   - `Livewire\Chat\GoogleDrivePicker`: render modal list paginated, breadcrumb folder, search, tombol "Gunakan di Chat".
5. **Ingest dari cloud**:
   - `DocumentLifecycleService::ingestFromCloud(User $u, string $provider, string $externalId)`: download → buat baris `documents` dengan `source_provider`, `source_external_id` → dispatch job ingest (reuse pipeline existing) → hapus file temp.
6. **Upload/export ke Drive**:
   - Tambah aksi Google Drive pada toolbar jawaban AI dengan pilihan format PDF/DOCX/XLSX/CSV sebelum upload.
   - Pisahkan tombol "Upload ke GDrive Kantor" pada preview dokumen dari dropdown "Ekspor".
   - Reuse `GoogleDriveService::uploadFromPath()`; user bisa memilih folder tujuan atau default ke folder app khusus seperti `ISTA AI`.
   - Setelah upload berhasil, simpan `external_id` dan `web_view_link`, lalu tampilkan link "Buka di Drive".
7. **Refresh manual**:
   - Tombol "Refresh dari Drive" pada dokumen yang `source_provider = google_drive`. Cek `modifiedTime` di Drive vs `source_synced_at` lokal → jika beda, re-ingest.
8. **Testing**:
   - Laravel: test config Drive service account; test browser hanya list folder yang diizinkan; test ingest dari cloud dengan mock `GoogleDriveService`; test upload hasil ke Drive dengan mock service; test error config/permission Drive.

## Rencana Test
- Laravel:
  - `cd laravel && php artisan test tests/Feature/CloudStorage/GoogleDriveIngestTest.php tests/Feature/CloudStorage/GoogleDriveUploadTest.php tests/Unit/Services/CloudStorage/GoogleDriveServiceTest.php`
  - Tambahkan test upload/export Drive, misalnya `tests/Feature/CloudStorage/GoogleDriveUploadTest.php`.
  - Lint: `cd laravel && ./vendor/bin/pint --test`
- Manual QA:
  - Admin/devops set credential service account + root folder id → modal picker chat bisa membuka root Drive kantor atau menampilkan pesan konfigurasi yang aman.
  - User klik tombol Google Drive di composer `/chat` → browse folder Drive kantor → pilih PDF → klik "Gunakan di Chat" → verifikasi dokumen muncul di sidebar dengan label "Google Drive" → verifikasi pipeline RAG berjalan setelah processing selesai.
  - User klik tombol Google Drive di bawah jawaban AI → pilih format → verifikasi file muncul di Drive dan link "Buka di Drive" valid.
  - User membuka preview dokumen → tombol "Ekspor" hanya berisi opsi download → tombol "Upload ke GDrive Kantor" terpisah dan membuka pilihan format upload.

## Kriteria Selesai
- Credential Drive kantor bisa dikonfigurasi via env/secret tanpa OAuth per user.
- File browser menampilkan list file Drive kantor yang diizinkan dengan paginasi & search.
- File terpilih bisa di-ingest dan menjadi sumber RAG layaknya upload manual.
- Output ISTA AI yang relevan bisa di-upload/disimpan ke Google Drive.
- Tidak ada halaman user-facing baru untuk cloud storage; integrasi Google Drive berada di `/chat` dan modal preview dokumen.
- Metadata file hasil upload tersimpan dan user mendapat link Drive setelah upload sukses.
- File di-download ke temp dan dihapus setelah ingest selesai.
- Test Laravel hijau.
- README/CHANGELOG menjelaskan langkah setup Google Cloud Console, service account, dan share folder Drive kantor.
- Tidak ada regresi di pipeline upload/RAG/sidebar.
