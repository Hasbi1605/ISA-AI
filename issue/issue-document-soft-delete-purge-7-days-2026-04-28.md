# Issue Plan: Soft Delete Dokumen dengan Scheduled Purge 7 Hari

## Latar Belakang
Dokumen yang dihapus user saat ini sudah dibersihkan dari storage dan vector store, tetapi row database masih disimpan sebagai soft delete melalui model [`Document`](</Users/macbookair/Magang-Istana/laravel/app/Models/Document.php:11>) dan lifecycle delete di [`DocumentLifecycleService`](</Users/macbookair/Magang-Istana/laravel/app/Services/DocumentLifecycleService.php:106>). Dengan kondisi sekarang, metadata dokumen yang sudah dihapus dapat menumpuk tanpa mekanisme purge berkala.

## Tujuan
- Mempertahankan perilaku delete saat ini sebagai soft delete.
- Menambahkan purge permanen otomatis setelah dokumen berada di status soft-deleted selama 7 hari.
- Memastikan purge terjadwal benar-benar punya runner di deployment production.

## Ruang Lingkup
- Menambah jalur purge permanen untuk dokumen soft-deleted yang melewati retensi 7 hari.
- Menambahkan Artisan command khusus purge dokumen terhapus.
- Menjadwalkan command tersebut berjalan harian dari scheduler Laravel.
- Menambahkan service scheduler di [`docker-compose.production.yml`](</Users/macbookair/Magang-Istana/docker-compose.production.yml>) agar schedule berjalan di production.
- Menambah test Laravel untuk perilaku purge dan menjaga delete tetap soft delete.

## Di Luar Scope
- Mengubah UX tombol hapus dokumen di chat atau halaman dokumen.
- Mengubah kontrak HTTP Laravel ke Python untuk upload, summarize, atau chat.
- Menambahkan fitur restore dokumen dari soft delete.
- Mengubah retensi menjadi configurable lintas environment di tahap ini.

## Area / File Terkait
- [`/Users/macbookair/Magang-Istana/laravel/app/Services/DocumentLifecycleService.php`](/Users/macbookair/Magang-Istana/laravel/app/Services/DocumentLifecycleService.php)
- [`/Users/macbookair/Magang-Istana/laravel/app/Console/Commands`](/Users/macbookair/Magang-Istana/laravel/app/Console/Commands)
- [`/Users/macbookair/Magang-Istana/laravel/routes/console.php`](/Users/macbookair/Magang-Istana/laravel/routes/console.php)
- [`/Users/macbookair/Magang-Istana/laravel/tests/Feature/Documents/DocumentDeletionTest.php`](/Users/macbookair/Magang-Istana/laravel/tests/Feature/Documents/DocumentDeletionTest.php)
- File test console baru untuk purge
- [`/Users/macbookair/Magang-Istana/docker-compose.production.yml`](/Users/macbookair/Magang-Istana/docker-compose.production.yml)

## Risiko
- Scheduler Laravel tidak akan benar-benar jalan di production bila compose tidak punya process runner untuk `schedule:work`.
- Purge yang terlalu agresif bisa menghapus metadata audit yang masih dibutuhkan jika retensi dihitung salah.
- Refactor cleanup yang terlalu lebar bisa mengubah perilaku delete yang saat ini sudah dipakai UI.

## Langkah Implementasi
1. Refactor cleanup dokumen di `DocumentLifecycleService` menjadi helper yang bisa dipakai ulang.
2. Tambahkan method purge untuk memilih dokumen soft-deleted dengan `deleted_at <= now()->subDays(7)` lalu `forceDelete()`.
3. Tambahkan Artisan command untuk menjalankan purge dan menampilkan jumlah dokumen yang dipurge.
4. Daftarkan scheduler harian di `routes/console.php`.
5. Tambahkan service `scheduler` di `docker-compose.production.yml` untuk menjalankan `php artisan schedule:work`.
6. Tambahkan test Laravel untuk command purge dan verifikasi delete tetap soft delete.

## Rencana Test
- Jalankan test Laravel yang relevan:
  - `cd laravel && php artisan test tests/Feature/Documents/DocumentDeletionTest.php tests/Feature/Console/PurgeDeletedDocumentsTest.php`
- Pastikan test baru mencakup:
  - dokumen yang baru soft-delete belum dipurge
  - dokumen yang soft-delete lebih dari 7 hari dipurge permanen
  - delete biasa dari UI tetap menghasilkan soft delete

## Kriteria Selesai
- Delete dokumen dari UI tetap menghasilkan soft delete.
- Dokumen soft-deleted lebih dari 7 hari dapat dihapus permanen lewat command purge.
- Scheduler Laravel menjadwalkan purge harian dan production compose punya runner scheduler.
- Test Laravel yang relevan hijau.
