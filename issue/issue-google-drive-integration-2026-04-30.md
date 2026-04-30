# Issue Plan: Integrasi Google Drive (OAuth + Ingest dari Drive) (Fase 4 — Fitur D)

## Latar Belakang
Issue #1 Tahap 7 meminta integrasi Google Drive (dan OneDrive) supaya pegawai bisa langsung memproses file dari cloud storage tanpa download-upload manual. User memilih **Google Drive saja dulu** sebagai scope MVP fase ini. Saat ini repo belum punya OAuth flow apa pun — Composer deps tidak mengandung `laravel/socialite`, dan Python tidak punya `google-api-python-client`.

Pendekatan praktis: implementasi **direct Google API** (tanpa MCP wrapper di MVP), memakai `laravel/socialite` untuk OAuth + `google-api-python-client` untuk akses Drive API. MCP wrapper bisa ditambahkan di iterasi berikutnya tanpa mengubah kontrak yang sudah ada.

## Tujuan
- User bisa connect akun Google Drive mereka ke ISTA AI lewat halaman Settings.
- User bisa browse file/folder Drive di dalam ISTA AI dan pilih file untuk diproses pipeline RAG.
- File yang dipilih di-download sementara → diproses oleh pipeline ingest yang sudah ada (Tahap 3 — chunk + embed + ChromaDB) → file temporary dihapus.
- User bisa disconnect Drive kapan saja (revoke token).
- File hasil ingest dari Drive ditandai `source_provider = google_drive` agar bisa dibedakan dari upload manual.

## Ruang Lingkup
- Tambah dependensi `laravel/socialite` + `socialiteproviders/google` (atau `google/apiclient` jika lebih sesuai untuk refresh token cycle).
- Tabel baru `cloud_storage_accounts` untuk simpan token (encrypted at rest).
- Kolom baru `source_provider`, `source_external_id`, `source_synced_at` di tabel `documents`.
- Halaman Settings: tombol "Hubungkan Google Drive" / "Putus Google Drive".
- Komponen Livewire `GoogleDriveBrowser` untuk list & pilih file.
- Endpoint Python untuk download file Drive dan delegasikan ke pipeline ingest existing (atau lakukan download di Laravel kalau lebih simpel).

## Di Luar Scope
- Integrasi OneDrive (akan jadi fase berikutnya jika permintaan tetap ada).
- MCP wrapper (akan jadi fase berikutnya, tidak wajib untuk MVP).
- Sync otomatis perubahan file (MVP: tombol "Refresh manual"; webhook/push notification fase berikutnya).
- Akses file Google Workspace (Docs/Sheets/Slides) — MVP fokus PDF/DOCX/XLSX di Drive. Google Docs perlu export khusus, bisa fase lanjut.
- Dukungan Shared Drive Workspace organisasi — MVP fokus "My Drive" personal.
- Upload balik dari ISTA AI ke Drive (read-only scope).

## Area / File Terkait
- `laravel/composer.json` (tambah `laravel/socialite`, `socialiteproviders/google`)
- `laravel/config/services.php` (tambah `google` provider)
- `laravel/database/migrations/` (migrasi baru: `create_cloud_storage_accounts_table`, `add_source_columns_to_documents_table`)
- File baru: `laravel/app/Models/CloudStorageAccount.php`
- File baru: `laravel/app/Http/Controllers/Auth/GoogleOAuthController.php`
- File baru: `laravel/app/Livewire/CloudFiles/GoogleDriveBrowser.php`
- File baru: `laravel/app/Livewire/Settings/CloudConnections.php`
- File baru: `laravel/app/Services/CloudStorage/GoogleDriveService.php`
- `laravel/app/Services/DocumentLifecycleService.php` (tambah method `ingestFromCloud`)
- `laravel/routes/web.php`
- `laravel/resources/views/livewire/settings/cloud-connections.blade.php` (baru)
- `laravel/resources/views/livewire/cloud-files/google-drive-browser.blade.php` (baru)
- `python-ai/requirements.txt` (`google-api-python-client`, `google-auth-oauthlib` — opsional, kalau download di Python)
- `.env.example` (tambah `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL`)

## Risiko
- Token bocor dari DB. Mitigasi: simpan `access_token` dan `refresh_token` ter-encrypt via Laravel `Crypt::encryptString`. Wajib unit test bahwa kolom raw di DB tidak plaintext.
- Refresh token dapat di-revoke dari sisi Google. Handle 401 dari Drive API → mark account `disconnected`, prompt user reconnect.
- File besar di Drive (>50 MB) — apply same upload limit dengan upload manual (50 MB seperti policy existing). Reject di awal.
- Google verification untuk scope `drive.readonly`: jika app dipakai >100 user external, perlu verifikasi resmi Google. Untuk pemakaian internal istana, set ke "Internal" di Google Workspace organisasi atau tetap "Testing" mode (cukup untuk uji coba).
- Redirect URI mismatch di Google Cloud Console — wajib setup secara manual; dokumentasikan langkah-langkahnya.
- File Google Docs bukan binary native — perlu `export_media` dengan mime tertentu (`application/pdf` atau DOCX). MVP: skip Google Docs files dengan UI message yang jelas, atau export sebagai PDF default.

## Langkah Implementasi
1. **Setup Google Cloud Console** (manual, dokumentasikan):
   - Project → enable Drive API → buat OAuth 2.0 Client ID (Web application) → set Authorized redirect URI ke `https://<host>/auth/google/callback`.
   - Catat `client_id` + `client_secret` ke `.env`.
2. **Database**:
   - Migrasi `create_cloud_storage_accounts_table`: `id, user_id, provider (enum: google_drive,onedrive), access_token TEXT, refresh_token TEXT, expires_at, scopes JSON, status (active|disconnected), created_at, updated_at`. Unique `(user_id, provider)`.
   - Migrasi `add_source_columns_to_documents_table`: `source_provider VARCHAR(20) DEFAULT 'local'`, `source_external_id VARCHAR(255) NULL`, `source_synced_at TIMESTAMP NULL`. Update `Document` fillable.
3. **OAuth flow**:
   - Install `laravel/socialite` + `socialiteproviders/google`.
   - `GoogleOAuthController::redirect()` dan `callback()`. Simpan token (encrypted) ke `cloud_storage_accounts`.
   - Halaman Settings `Livewire\Settings\CloudConnections` dengan tombol Connect/Disconnect.
   - `disconnect()`: revoke token via Google API + soft delete account row.
4. **Drive browser**:
   - `GoogleDriveService::listFiles($parentFolderId, $pageToken)` panggil Drive API v3 dengan token user.
   - `GoogleDriveService::downloadToTemp($fileId)`: download ke `storage/app/tmp/cloud/{uuid}` lalu hapus setelah ingest selesai.
   - `Livewire\CloudFiles\GoogleDriveBrowser`: render list paginated, breadcrumb folder, search, tombol "Proses dengan AI".
5. **Ingest dari cloud**:
   - `DocumentLifecycleService::ingestFromCloud(User $u, string $provider, string $externalId)`: download → buat baris `documents` dengan `source_provider`, `source_external_id` → dispatch job ingest (reuse pipeline existing) → hapus file temp.
6. **Refresh manual**:
   - Tombol "Refresh dari Drive" pada dokumen yang `source_provider = google_drive`. Cek `modifiedTime` di Drive vs `source_synced_at` lokal → jika beda, re-ingest.
7. **Testing**:
   - Laravel: feature test OAuth callback (mock Socialite); test ingest dari cloud dengan mock `GoogleDriveService`; test encrypt-at-rest token; test disconnect.
   - Python: jika ada penambahan endpoint, tambahkan test yang sesuai.

## Rencana Test
- Laravel:
  - `cd laravel && php artisan test tests/Feature/CloudStorage/GoogleOAuthTest.php tests/Feature/CloudStorage/GoogleDriveIngestTest.php tests/Unit/Services/CloudStorage/GoogleDriveServiceTest.php`
  - Lint: `cd laravel && ./vendor/bin/pint --test`
- Python:
  - `cd python-ai && source venv/bin/activate && pytest` (jika ada perubahan)
- Manual QA:
  - Connect Drive lewat Settings → browse → pilih PDF → klik "Proses dengan AI" → verifikasi dokumen muncul di sidebar dengan label "Google Drive" → verifikasi pipeline RAG berjalan.

## Kriteria Selesai
- User bisa connect akun Google Drive lewat halaman Settings.
- Token tersimpan encrypted di `cloud_storage_accounts`; verified by test.
- File browser menampilkan list file Drive milik user dengan paginasi & search.
- File terpilih bisa di-ingest dan menjadi sumber RAG layaknya upload manual.
- Tombol Disconnect berhasil revoke token Google dan menghapus akses.
- File di-download ke temp dan dihapus setelah ingest selesai.
- Test Laravel hijau.
- README/CHANGELOG menjelaskan langkah setup Google Cloud Console.
- Tidak ada regresi di pipeline upload/RAG/sidebar.
