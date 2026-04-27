# Issue: Stabilkan Runtime Env untuk Jalur AI

## Gejala
- Chat di halaman `/chat` gagal dengan pesan `Kesalahan sistem saat menghubungi otak AI`.
- Di production, konfigurasi banyak dibaca dari `docker compose env_file` (`.env.droplet`).
- Jalur email sebelumnya sudah terbukti sensitif terhadap nilai env yang dibungkus tanda kutip.

## Dugaan Akar Masalah
- Service AI Laravel dan Python membaca secret/API key langsung dari environment tanpa normalisasi.
- Pada deploy berbasis Docker Compose, nilai dari `env_file` bisa terbawa dengan tanda kutip literal atau whitespace.
- Jika itu terjadi pada token internal atau API key provider AI, request ke service Python atau provider eksternal dapat gagal walau nilainya tampak benar di file env.

## Ruang Lingkup
- Tambah helper normalisasi env di `python-ai`.
- Gunakan helper tersebut pada pembacaan token internal dan API key provider chat/search/embedding.
- Tambah test Python untuk mencegah regresi.
- Verifikasi ulang test Python dan area Laravel yang terdampak.

## Rencana Implementasi
1. Tambah utility pembacaan env yang men-trim whitespace dan melepas quote pembungkus.
2. Ganti pembacaan env raw di jalur AI yang sensitif:
   - token internal
   - API key model chat
   - API key LangSearch
   - API key embedding / HyDE bila relevan
3. Tambah test unit Python untuk helper normalisasi.
4. Jalankan `pytest` di `python-ai`.
5. Jika ada sentuhan Laravel, jalankan `php artisan test` di `laravel`.

## Risiko
- Jika ada nilai env yang memang sengaja membutuhkan quote literal penuh, helper bisa mengubahnya. Risiko ini rendah untuk API key/token/url yang jadi target patch.
- Masalah production tetap bisa berasal dari API key invalid, tetapi patch ini menghilangkan false-negative akibat format env.
