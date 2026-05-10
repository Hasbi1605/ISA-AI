# Perbaikan Layout Header Memo Mobile

## Latar Belakang

Pada workspace memo, posisi kontrol header belum sesuai kebutuhan terbaru. Di tab memo, toggle Chat/Memo masih berada di area kanan bersama tombol Dokumen, sementara tombol Dokumen perlu diposisikan di pojok kanan dan toggle Chat/Memo perlu berada di tengah. Saat dokumen memo dibuka di versi mobile, tombol header seperti Kembali, Regenerate, DOCX, dan PDF juga berisiko saling bertabrakan karena ruang horizontal terbatas.

## Tujuan

- Memindahkan toggle Chat/Memo ke tengah header tab memo.
- Memindahkan tombol Dokumen ke pojok kanan header tab memo.
- Merapikan header dokumen memo pada mobile agar tombol tidak bertumpuk/bertabrakan.
- Mempertahankan fungsi existing: buka panel dokumen, kembali ke chat memo, regenerate, download DOCX, download PDF, dark mode, dan tab switch Chat/Memo.

## Ruang Lingkup

- Penyesuaian struktur dan kelas responsive pada header memo chat.
- Penyesuaian kelas responsive pada header dokumen memo, terutama untuk mobile.
- Perubahan hanya pada UI Blade/Tailwind yang relevan.
- Browser QA dilakukan hanya setelah PR dibuat, sesuai instruksi user.

## Di Luar Scope

- Perubahan backend Livewire/PHP untuk generate memo, versioning, atau download.
- Perubahan Alpine state `memoWorkspace()` kecuali ditemukan kebutuhan kecil yang tidak bisa dihindari.
- Refactor besar workspace memo atau desain ulang panel memo.
- Perubahan template isi dokumen memo.

## Area / File Terkait

- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
  - Header panel chat memo, tombol Dokumen mobile, toggle dark mode, dan include `chat-memo-tab-toggle`.
- `laravel/resources/views/livewire/memos/partials/memo-preview-panel.blade.php`
  - Header panel dokumen memo, tombol Kembali, Regenerate, DOCX, dan PDF.
- `laravel/resources/views/livewire/chat/partials/chat-memo-tab-toggle.blade.php`
  - Komponen toggle Chat/Memo; kemungkinan tidak perlu diubah.
- `laravel/resources/js/chat-page.js`
  - Referensi state mobile memo; hanya untuk verifikasi alur, bukan target utama perubahan.

## Risiko

- Header memo mobile bisa tetap padat pada viewport sangat kecil jika teks tombol tidak disembunyikan.
- Perubahan alignment header bisa memengaruhi tampilan desktop/tablet jika breakpoint tidak dijaga.
- Tombol Dokumen hanya relevan pada mobile/tablet (`lg:hidden`), sehingga posisi desktop perlu tetap aman.
- QA visual baru boleh dilakukan setelah PR dibuat; sebelum PR validasi terbatas pada build/test/diff.

## Langkah Implementasi

1. Ubah header `memo-chat.blade.php` menjadi layout tiga area: kiri untuk sidebar/brand, tengah untuk toggle Chat/Memo, kanan untuk dark mode dan tombol Dokumen.
2. Pastikan tombol Dokumen berada paling kanan dan tetap memanggil `showMemoDocumentPanel()`.
3. Ubah header `memo-preview-panel.blade.php` agar mobile lebih ringkas:
   - tombol aksi memakai padding/gap lebih kecil di mobile;
   - teks Regenerate/DOCX/PDF disembunyikan di mobile dan muncul lagi di breakpoint lebih besar;
   - badge/label Dokumen disembunyikan atau diringkas di mobile bila perlu.
4. Pastikan tombol Kembali tetap tersedia saat mobile panel dokumen dibuka.
5. Jalankan validasi build/test relevan sebelum commit dan PR.
6. Setelah PR dibuat, lakukan browser QA pada live preview mobile dan desktop.

## Rencana Test

- Pra-PR:
  - `cd laravel && npm run build`
  - `cd laravel && php artisan test --filter=Memo`
  - `git diff --check`
- Pasca-PR:
  - Browser QA via subagent pada `https://ista-ai.app` setelah branch PR dideploy.
  - Cek desktop: toggle Chat/Memo di tengah, tombol Dokumen di kanan.
  - Cek mobile: header dokumen memo tidak bertabrakan; tombol Kembali, Regenerate, DOCX, dan PDF terlihat/usable.
  - Cek dark mode dan state kembali dari dokumen ke chat memo.
- Verifikasi akhir penuh sebelum minta approval merge:
  - `cd laravel && php artisan test`
  - `cd python-ai && source venv/bin/activate && pytest`

## Kriteria Selesai

- Toggle Chat/Memo berada di tengah header tab memo.
- Tombol Dokumen berada di pojok kanan header tab memo.
- Pada mobile saat dokumen memo dibuka, tombol header tidak saling bertabrakan/bertumpuk.
- Build frontend dan test relevan lulus.
- PR dibuat, branch PR dideploy ke `https://ista-ai.app`, dan browser QA dilakukan setelah PR dibuat.
- Review/QC PR tidak menemukan blocker sebelum user diminta approval merge.
