# Issue Plan: Foundation Upgrade ke PHP 8.3, Laravel Compatible, dan Laravel AI SDK

## Latar Belakang
Issue ini adalah turunan fondasi dari issue `#67`. Target arsitektur Laravel-only tidak bisa dimulai sebelum aplikasi berada pada platform yang kompatibel dengan `laravel/ai`. Saat ini repo masih memakai `PHP ^8.2` dan `Laravel ^11.31`, sementara paket `laravel/ai` resmi membutuhkan jalur `PHP 8.3` dan komponen Laravel yang kompatibel.

Tanpa fondasi platform ini, issue migrasi chat, dokumen, dan retrieval tidak dapat diimplementasikan secara resmi pada ekosistem Laravel AI SDK.

## Tujuan
- Meng-upgrade foundation aplikasi ke versi PHP dan Laravel yang kompatibel dengan `laravel/ai`.
- Menginstal `laravel/ai`, mem-publish konfigurasi, dan memastikan aplikasi tetap bootable.
- Menjaga seluruh perilaku existing tetap stabil setelah upgrade fondasi.

## Ruang Lingkup
- Upgrade requirement platform di `laravel/composer.json`.
- Update dependency Laravel yang diperlukan agar kompatibel dengan `laravel/ai`.
- Instalasi `laravel/ai`.
- Publish config/migration bawaan SDK.
- Penyesuaian minimum jika ada breaking change di bootstrap, config, Horizon, Livewire, atau test suite.
- Verifikasi aplikasi Laravel tetap berjalan sebelum feature migration dimulai.

## Di Luar Scope
- Migrasi chat runtime ke Laravel AI SDK.
- Migrasi dokumen, retrieval, atau summarization.
- Penghapusan `python-ai`.
- Refactor product behavior yang tidak dibutuhkan untuk kompatibilitas platform.

## Area / File Terkait
- `laravel/composer.json`
- `laravel/composer.lock`
- `laravel/config/*`
- `laravel/app/Providers/*`
- `laravel/bootstrap/*`
- `laravel/tests/*`
- file config/migration baru dari `laravel/ai`

## Risiko
- Package lain seperti Livewire, Horizon, atau tool dev bisa ikut terdampak oleh upgrade versi framework.
- Muncul breaking change kecil di auth/session/testing yang tidak terkait AI tetapi memblokir upgrade.
- SDK AI menambah migration/config baru yang perlu diputuskan boundary pemakaiannya sejak awal.

## Langkah Implementasi
1. Tentukan target minimum versi PHP dan Laravel yang dipakai sebagai fondasi migrasi.
2. Upgrade dependency inti secara sekecil mungkin namun tetap kompatibel dengan `laravel/ai`.
3. Install `laravel/ai` dan publish config/migration resminya.
4. Pastikan aplikasi tetap bootable di local/test environment.
5. Rapikan incompatibility yang muncul di package internal tanpa memperluas scope ke feature migration.
6. Putuskan boundary awal pemakaian SDK:
   - config siap dipakai
   - migration conversation SDK tersedia
   - belum dijadikan source of truth untuk fitur user-facing
7. Dokumentasikan perubahan platform yang wajib diikuti issue migrasi berikutnya.

## Rencana Test
- Jalankan seluruh test Laravel:
  - `cd laravel && php artisan test`
- Jalankan smoke check:
  - app boot
  - route utama render
  - queue/Horizon provider tidak error saat bootstrap
- Verifikasi config dan migration `laravel/ai` tersedia dan tidak merusak environment existing.

## Kriteria Selesai
- Laravel app sudah berada pada platform yang kompatibel dengan `laravel/ai`.
- Paket `laravel/ai` sudah terpasang dan konfigurasi dasarnya tersedia.
- Full test Laravel tetap hijau atau gap yang tersisa sudah dipersempit ke isu kompatibilitas yang terdokumentasi jelas.
- Issue migrasi capability berikutnya dapat langsung memakai SDK tanpa harus menangani upgrade platform lagi.
