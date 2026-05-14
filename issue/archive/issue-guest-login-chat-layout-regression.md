# Issue: Perbaiki Regression Layout Chat Setelah Login dari Guest

## Latar Belakang
User guest bisa membuka alur `Buka Chat`, diarahkan ke halaman login, lalu setelah login langsung dibawa ke `/chat` lewat `redirectIntended(..., navigate: true)`.

Sesudah refactor chat pada Issue #61, alur ini mengalami regresi: halaman chat pertama kali tampil dalam state rusak, dengan sidebar kanan melebar dan kontrol penting seperti composer chat serta toggle dark mode tidak muncul sampai browser di-refresh manual.

## Tujuan
- Menemukan perubahan yang memicu regresi pada alur guest -> login -> chat.
- Memperbaiki inisialisasi UI chat agar layout normal tanpa perlu refresh browser.
- Menjaga scope perbaikan sekecil mungkin dan tidak mengubah perilaku chat yang sudah normal di hard refresh.

## Ruang Lingkup
- Audit route `guest-chat`, redirect login, dan inisialisasi frontend chat.
- Patch minimal pada registrasi state Alpine/Livewire yang dibutuhkan chat page.
- Tambah atau sesuaikan test Laravel yang relevan untuk memastikan redirect intended tetap benar.

## Di Luar Scope
- Refactor besar komponen chat atau auth.
- Perubahan desain sidebar/chat composer.
- Perubahan perilaku backend pengiriman pesan atau dokumen.

## Area / File Terkait
- `laravel/routes/web.php`
- `laravel/resources/views/livewire/pages/auth/login.blade.php`
- `laravel/resources/views/livewire/chat/chat-index.blade.php`
- `laravel/resources/views/livewire/chat/partials/*.blade.php`
- `laravel/resources/js/app.js`
- `laravel/tests/Feature/Auth/*`

## Risiko
- Registrasi Alpine yang dipindah ke JS global bisa memengaruhi load order jika tidak dijaga idempotent.
- Bug ini terutama muncul pada navigasi SPA (`navigate: true`), jadi perlu memastikan fix tetap aman pada hard refresh biasa.
- Test backend tidak bisa menangkap penuh regression visual, jadi perlu verifikasi manual/browser-aware semampunya.

## Langkah Implementasi
1. Verifikasi akar masalah dari history refactor chat terbaru.
2. Pindahkan atau stabilkan registrasi state Alpine chat agar tersedia pada navigasi SPA pasca-login.
3. Rapikan `chat-index.blade.php` agar tidak bergantung pada inline registration yang hanya aman saat full reload.
4. Tambahkan/cek test auth redirect yang relevan.
5. Jalankan verifikasi Laravel pada area auth/chat yang terdampak.

## Rencana Test
- Jalankan test auth/feature Laravel yang relevan dengan login dan redirect intended.
- Jika ada test yang menyentuh chat route atau guest redirect, jalankan juga.
- Verifikasi manual alur: guest klik `Buka Chat` -> login -> masuk `/chat` tanpa refresh dan layout tetap normal.

## Kriteria Selesai
- Akar masalah regresi teridentifikasi jelas dari perubahan sebelumnya.
- Halaman chat ter-render normal setelah login dari guest tanpa perlu refresh manual.
- Perubahan tetap kecil dan terfokus.
- Test Laravel yang relevan sudah dijalankan dan hasilnya jelas.
