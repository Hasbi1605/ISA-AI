# Peningkatan UI Chat, Memo Mobile, dan Dark Mode Login/Profile

GitHub Issue: https://github.com/Hasbi1605/ISTA-AI/issues/135

## Latar Belakang
Ada beberapa inkonsistensi dan hambatan UX pada sistem saat ini: bubble pesan user di tab chat terlalu lebar untuk pesan pendek, radius bubble user dan jawaban ISTA AI belum konsisten, tab memo di mobile terlalu padat/sulit dipakai, loading membuka chat masih muncul di tengah panel chat, form input chat terasa berada dalam kotak terpisah, collapse kedua sidebar membuat area chat melebar terlalu banyak, dan halaman login/profile belum mengikuti dark mode yang konsisten dengan halaman lain.

## Tujuan
- Membuat bubble pesan chat lebih natural dan konsisten antara pesan user dan jawaban ISTA AI.
- Menyederhanakan pengalaman tab memo di mobile dengan pemberitahuan agar user membuka desktop untuk pengalaman memo lengkap.
- Mengurangi loading visual yang mengganggu di panel chat utama.
- Membuat composer chat terasa mengambang dan lebih dekat dengan output pesan.
- Menjaga lebar konten chat tetap nyaman saat sidebar kiri/kanan di-collapse.
- Menambahkan dark mode konsisten pada halaman login dan profile.

## Ruang Lingkup
1. Ubah bubble user persisted dan optimistic agar lebarnya dinamis mengikuti isi pesan pendek, dengan batas maksimum tetap nyaman.
2. Pilih satu gaya radius bubble yang konsisten: bubble user dan assistant memakai rounded besar dengan salah satu sudut bawah sedikit lancip sesuai arah bubble.
3. Pada tab memo mobile, tampilkan flash message/pemberitahuan ringkas agar user membuka desktop, dan ringkas header mobile agar tidak bertumpuk.
4. Hilangkan loading overlay `Membuka chat...` di tengah panel chat; loading tetap ada pada kursor/disabled state dan spinner history sidebar.
5. Ubah composer chat agar tidak terlihat sebagai kotak besar yang terpisah; composer tetap mengambang, transparan, dan dekat dengan area pesan.
6. Saat kedua sidebar chat di-collapse pada desktop, batasi area konten pesan dan input supaya tidak ikut melebar penuh layar.
7. Tambahkan inisialisasi/toggle dark mode dan varian warna gelap pada halaman login, partial auth, halaman profile, serta form profile terkait.

## Di Luar Scope
- Redesign total chat, memo, login, atau profile.
- Perubahan backend chat, streaming AI, dokumen, Google Drive, atau database.
- Implementasi penuh editor memo mobile/OnlyOffice mobile; pendekatan yang dipilih adalah pemberitahuan desktop untuk mobile.
- Perubahan flow auth/profile selain styling dark mode.

## Area / File Terkait
- `laravel/resources/views/livewire/chat/chat-index.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-composer.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-memo-tab-toggle.blade.php`
- `laravel/resources/views/livewire/memos/memo-workspace.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-preview-panel.blade.php`
- `laravel/resources/js/chat-page.js`
- `laravel/resources/css/app.css`
- `laravel/resources/css/auth.css`
- `laravel/resources/views/layouts/auth-canvas.blade.php`
- `laravel/resources/views/livewire/pages/auth/login.blade.php`
- `laravel/resources/views/livewire/pages/auth/partials/*.blade.php`
- `laravel/resources/views/profile.blade.php`
- `laravel/resources/views/livewire/profile/*.blade.php`

## Risiko
- Perubahan layout chat dapat memengaruhi scroll dan responsivitas; perlu verifikasi desktop dan mobile.
- Memo mobile tidak diimplementasikan penuh, sehingga copy pemberitahuan harus jelas dan tidak menghalangi navigasi.
- Dark mode login/profile memakai CSS `auth.css` bersama; perubahan harus menjaga tampilan light mode tetap sama.
- Profile page adalah Blade standalone, sehingga perlu inisialisasi dark mode sendiri agar konsisten dengan halaman lain dan tidak FOUC.

## Langkah Implementasi
1. Buat GitHub Issue dari plan ini dan branch kerja `codex/chat-memo-auth-profile-ui-polish`.
2. Perbaiki struktur/lebar bubble chat: container outer tetap align kanan/kiri, bubble memakai `inline-block`/`w-fit` dengan `max-w` dan radius konsisten.
3. Hapus overlay loading `Membuka chat...` dari panel chat utama tanpa menghapus spinner history sidebar.
4. Ubah composer chat agar wrapper/form lebih transparan, shadow ringan, dan tanpa kesan square container besar.
5. Tambahkan pembatas `max-w` pada main chat content/composer agar collapse kedua sidebar tidak membuat konten melebar ekstrem.
6. Tambahkan state/pemberitahuan memo mobile dan ringkas header tab memo pada viewport kecil.
7. Tambahkan dark mode init/toggle pada `auth-canvas` dan `profile`, lalu tambahkan dark variants di auth/profile card, background, input, labels, modal, dan form.
8. Jalankan build/test relevan dan browser QA untuk chat, memo mobile, login, dan profile.

## Rencana Test
- `cd laravel && npm run build`
- `cd laravel && php artisan test` minimal test relevan Laravel karena perubahan menyentuh Blade/CSS/JS.
- Browser QA:
  - Chat desktop: pesan pendek user bubble tidak full width; assistant bubble memakai radius konsisten.
  - Chat desktop: membuka history tidak menampilkan overlay tengah; spinner history tetap ada.
  - Chat desktop: collapse dua sidebar menjaga area pesan/input tetap max-width nyaman.
  - Chat mobile: composer tetap usable dan tidak kotak besar.
  - Memo mobile 375px/390px: header tidak bertumpuk dan ada pemberitahuan desktop.
  - Login/profile light dan dark mode: background, card, input, teks, modal/form tetap terbaca.

## Kriteria Selesai
- Ketujuh poin permintaan user ditangani dengan perubahan minimal dan terarah.
- Issue markdown dan GitHub Issue tersedia.
- Build asset dan test Laravel relevan berhasil atau blocker dijelaskan.
- QA browser memberi bukti untuk area UI yang berubah.
- Perubahan di-commit, push, PR dibuat, preview `https://ista-ai.app` diperbarui, dan review loop tidak menyisakan blocker sebelum meminta approval merge.
