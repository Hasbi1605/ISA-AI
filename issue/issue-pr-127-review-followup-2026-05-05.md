# Issue Plan - Follow-up Review PR #127

## Latar Belakang
Review terbaru pada PR #127 menemukan tiga blocker yang belum tertutup:

1. Guard root folder Google Drive masih hanya bergantung pada UI, belum ditegakkan server-side.
2. Import file dari Google Drive belum menegakkan batas ukuran upload 50 MB.
3. README belum sinkron dengan jalur OAuth pusat yang sekarang menjadi jalur upload utama.

## Tujuan
- Menegakkan pembatasan root folder Drive di level service untuk browse, download, dan upload.
- Menolak ingest file Drive di atas 50 MB sebelum file besar diproses ke pipeline.
- Menyamakan dokumentasi setup/deploy dengan alur OAuth pusat yang sekarang dipakai.

## Scope
- `laravel/app/Services/CloudStorage/GoogleDriveService.php`
- `laravel/app/Services/DocumentLifecycleService.php`
- `laravel/tests/Unit/Services/CloudStorage/GoogleDriveServiceTest.php`
- `laravel/tests/Feature/CloudStorage/GoogleDriveIngestTest.php`
- `laravel/tests/Feature/CloudStorage/GoogleDriveUploadTest.php`
- `laravel/tests/Feature/Chat/GoogleDrivePickerTest.php`
- `README.md`

## Rencana Implementasi
1. Tambahkan guard server-side di `GoogleDriveService` agar semua folder/file/target upload tetap berada di bawah `GOOGLE_DRIVE_ROOT_FOLDER_ID`.
2. Validasi ukuran file Google Drive terhadap batas 50 MB sebelum dan sesudah download bila metadata size tidak tersedia.
3. Tambahkan regression test untuk:
   - folder di luar root tidak bisa dibrowse
   - file di luar root tidak bisa di-download / di-ingest
   - target upload di luar root tidak bisa dipakai
   - file Drive di atas 50 MB ditolak
4. Sinkronkan README agar setup utama menjelaskan OAuth pusat, sementara service account dijelaskan sebagai jalur baca/list dan fallback Shared Drive.

## Risiko
- Query metadata tambahan ke Google Drive bisa menambah kompleksitas, jadi implementasi harus tetap minimal dan fokus pada ancestry/root validation.
- Test mock service perlu dijaga tetap ringan agar tidak membuat suite jadi rapuh.

## Verifikasi
- `./vendor/bin/pint` untuk file PHP yang berubah
- `php artisan test tests/Feature/Chat/GoogleDrivePickerTest.php tests/Feature/CloudStorage/GoogleDriveIngestTest.php tests/Feature/CloudStorage/GoogleDriveUploadTest.php tests/Unit/Services/CloudStorage/GoogleDriveServiceTest.php`
- Tambahkan verifikasi README/manual note bila perlu di PR follow-up comment
