# Issue: Follow-up Bucket Riwayat Chat PR 154

## Konteks
Sidebar riwayat chat saat ini hanya membedakan `Hari Ini` dan `7 Hari Terakhir`. Dalam implementasi aktual, label `7 Hari Terakhir` menampung semua percakapan selain hari ini, termasuk percakapan yang sudah lewat 7 atau 30 hari. Ini membuat label waktu tidak akurat dan sidebar terasa terlalu panjang saat section dibuka.

## Tujuan
- Memisahkan riwayat chat ke bucket waktu yang tidak overlap:
  - `Hari Ini`
  - `7 Hari Terakhir`
  - `30 Hari Terakhir`
  - `Lebih Lama`
- Membuat bucket selain `Hari Ini` default tertutup, kecuali bucket yang berisi chat aktif.
- Menambahkan pencarian ringkas agar user bisa menemukan history lama tanpa membuka semua section manual.
- Menambahkan kontrol `Lihat semua` / `Ringkas` untuk membuka atau menutup seluruh bucket lama.

## Scope Implementasi
1. Hitung bucket berdasarkan `updated_at` dalam timezone `Asia/Jakarta`.
2. `7 Hari Terakhir` berisi 1-7 hari lalu.
3. `30 Hari Terakhir` berisi 8-30 hari lalu.
4. `Lebih Lama` berisi lebih dari 30 hari.
5. Tambahkan Alpine state lokal untuk search, toggle per bucket, active bucket auto-open, dan indikator aktivitas pending/unread di header bucket.
6. Update test feature chat agar label dan bucket baru menjadi kontrak.

## Risiko
- Test lama yang mengasumsikan hanya satu bucket lama perlu diperbarui.
- Search berbasis client-side perlu tetap aksesibel dan tidak mengubah data Livewire.

## Verifikasi
- Jalankan test feature chat terkait.
- Jalankan build frontend Laravel.
