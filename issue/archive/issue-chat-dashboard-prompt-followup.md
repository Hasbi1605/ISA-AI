# Issue: Follow-up UI Chat untuk Default Light Mode dan Prompt Dashboard

## Latar Belakang
Setelah regresi layout chat pasca-login guest diperbaiki, masih ada dua masalah lanjutan pada flow dashboard ke chat:

1. Default mode pada halaman chat masih mengikuti `prefers-color-scheme`, sehingga user baru bisa langsung masuk dark mode. Keinginan terbaru adalah default chat tetap light sampai user sendiri mengubah preference.
2. Saat guest mengetik lewat tombol `Mulai bertanya` di dashboard lalu login, prompt seharusnya langsung terkirim dan viewport otomatis scroll ke pesan yang baru dikirim. Perilaku ini sebelumnya sudah benar, lalu regresi setelah refactor chat memecah state Alpine menjadi beberapa komponen.

## Tujuan
- Menjadikan default theme chat adalah light bila belum ada preference tersimpan.
- Mengembalikan flow dashboard -> login -> chat agar prompt otomatis terkirim dan auto-scroll aktif secara stabil.
- Menjaga perubahan tetap kecil dan fokus pada area UI chat yang terdampak.

## Ruang Lingkup
- Audit fallback theme pada layout/chat.
- Audit handoff `pending_prompt` dan auto-submit prompt setelah redirect login.
- Patch kecil pada Alpine/JS chat untuk menghindari race condition pasca-login.
- Tambah atau sesuaikan test Laravel yang relevan.

## Di Luar Scope
- Refactor besar struktur komponen chat.
- Perubahan visual besar selain default theme.
- Perubahan backend orkestrasi chat di luar yang dibutuhkan untuk handoff prompt.

## Area / File Terkait
- `laravel/resources/views/layouts/app.blade.php`
- `laravel/resources/js/chat-page.js`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-composer.blade.php`
- `laravel/resources/views/dashboard.blade.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/tests/Feature/Auth/*`
- `laravel/tests/Feature/DashboardTest.php`

## Risiko
- Perubahan theme fallback bisa memengaruhi halaman lain yang memakai `layouts.app`.
- Fix auto-submit prompt perlu hati-hati agar tidak mengirim ganda saat hard refresh atau reload halaman chat.
- Karena gejalanya bersifat UI timing, test backend tidak akan menutup seluruh risiko tanpa validasi manual.

## Langkah Implementasi
1. Verifikasi akar masalah dari history PR chat sebelumnya.
2. Ubah fallback theme agar default-nya light saat `localStorage.theme` belum ada.
3. Stabilkan auto-submit prompt dashboard setelah login dengan mengurangi ketergantungan pada timing event antar-komponen.
4. Tambahkan/cek test auth/dashboard yang relevan.
5. Jalankan verifikasi Laravel dan build frontend.

## Rencana Test
- Jalankan test auth/dashboard Laravel yang menyentuh guest flow dan redirect.
- Jalankan build frontend untuk memverifikasi sintaks JS.
- Validasi manual flow `Mulai bertanya` -> login -> chat untuk memastikan prompt terkirim dan viewport turun ke pesan user.

## Kriteria Selesai
- Default theme chat menjadi light saat belum ada preference tersimpan.
- Flow `Mulai bertanya` dari dashboard kembali mengirim prompt otomatis setelah login.
- Pesan user langsung terlihat dan auto-scroll aktif tanpa refresh manual.
- Test/verifikasi yang relevan sudah dijalankan dan hasilnya jelas.
