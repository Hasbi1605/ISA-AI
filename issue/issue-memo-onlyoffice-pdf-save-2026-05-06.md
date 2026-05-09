# Issue: Stabilkan Export PDF dan Callback OnlyOffice Memo

## Latar Belakang
Setelah memo berhasil digenerate, tombol PDF pada header dokumen kembali macet. Di saat yang sama OnlyOffice menampilkan peringatan bahwa dokumen tidak bisa disimpan dan user harus menekan OK.

## Bukti
- Log production menunjukkan `/memos/{id}/export-pdf` berjalan hampir 2 menit.
- Request OnlyOffice ke `/memos/{id}/signed-file` baru dilayani setelah request export timeout.
- Proses production menjalankan `php artisan serve`; meskipun `PHP_CLI_SERVER_WORKERS=4` sudah diset, proses `php -S` yang dipanggil Laravel tetap berjalan sebagai single worker.
- Log OnlyOffice juga menunjukkan callback autosave gagal saat Laravel tidak bisa menerima request paralel.

## Dugaan Akar Masalah
Export PDF memanggil OnlyOffice converter secara sinkron. Converter lalu mengambil DOCX kembali ke Laravel lewat signed URL. Karena server Laravel production masih single worker, request export menahan worker yang sama sehingga signed URL dan callback OnlyOffice tertunda atau gagal.

## Tujuan
- Pastikan Laravel production benar-benar berjalan dengan multi-worker PHP built-in server.
- Pastikan export PDF bisa melayani request signed-file paralel saat konversi berlangsung.
- Kurangi kegagalan callback OnlyOffice yang memicu modal "dokumen tidak bisa disimpan".

## Scope
- `laravel/docker/serve.sh`
- `laravel/Dockerfile`
- `docker-compose.production.yml`

## Risiko
- Perubahan command container harus tetap menjalankan router Laravel dari direktori `public`.
- Healthcheck `/up` dan reverse proxy Caddy harus tetap mengarah ke port internal 8000.
- Deploy akan restart container Laravel sehingga sesi editor OnlyOffice yang sedang terbuka bisa menerima satu callback gagal selama restart.

## Langkah Implementasi
1. Tambahkan wrapper `ista-serve` yang menjalankan `php -S` langsung dari direktori `public`.
2. Jadikan Dockerfile default command menggunakan wrapper tersebut.
3. Ubah command production dari `php artisan serve` ke `ista-serve`.
4. Verifikasi konfigurasi compose dan test Laravel yang terkait memo/OnlyOffice.
5. Setelah push, deploy ulang branch PR tanpa merge.

## Rencana Verifikasi
- `cd laravel && php artisan test tests/Feature/Memos/MemoPolicyTest.php tests/Feature/Memos/OnlyOfficeCallbackTest.php`
- `cd laravel && ./vendor/bin/pint --test app/Http/Controllers/Memos/MemoFileController.php app/Services/OnlyOffice/DocumentConverter.php app/Http/Controllers/OnlyOfficeCallbackController.php tests/Feature/Memos/MemoPolicyTest.php tests/Feature/Memos/OnlyOfficeCallbackTest.php`
- `docker compose --env-file deploy/digitalocean.env.example -f docker-compose.production.yml config`
- Production: cek `docker compose top laravel` dan pastikan ada beberapa proses `php -S`.
