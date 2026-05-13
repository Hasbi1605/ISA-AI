# Issue: Samakan Pola Sidebar History Memo Dengan Chat

## Latar Belakang
Sidebar history chat sudah memakai bucket waktu yang lebih akurat, pencarian, section collapse, serta kontrol `Lihat semua` / `Ringkas`. Sidebar memo masih memakai grup sederhana `Hari Ini`, `7 Hari Terakhir`, dan `Lebih Lama` tanpa search atau section collapse yang konsisten.

## Tujuan
- Membuat sidebar memo terasa satu keluarga dengan sidebar chat.
- Menjaga memo tetap berbeda sebagai workspace dokumen dengan metadata waktu dan versi.
- Memakai bucket waktu yang tidak overlap: `Hari Ini`, `7 Hari Terakhir`, `30 Hari Terakhir`, `Lebih Lama`.
- Menambahkan pencarian memo dan kontrol buka/tutup section lama.

## Ruang Lingkup
1. Update `memo-history-sidebar.blade.php` untuk bucket waktu, search, collapse, dan tombol `Lihat semua` / `Ringkas`.
2. Tambahkan state Alpine memo history di `chat-page.js`.
3. Pertahankan item memo dengan ikon dokumen, judul, waktu update, dan info versi bila tersedia.
4. Update test MemoWorkspace agar kontrak UI memo history tercakup.

## Di Luar Scope
- Tidak mengubah proses generate/revisi memo.
- Tidak mengubah model, migration, atau queue.
- Tidak menambahkan pending/background indicator baru untuk memo generation.
- Tidak mengubah layout panel chat memo dan preview dokumen.

## Area / File Terkait
- `laravel/resources/views/livewire/memos/partials/memo-history-sidebar.blade.php`
- `laravel/resources/js/chat-page.js`
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`

## Risiko
- Search dan collapse memo perlu tetap aman saat Livewire re-render.
- Memo aktif harus tetap terlihat saat berada di bucket lama.
- Metadata versi harus tidak memicu error jika memo belum memiliki current version.

## Langkah Implementasi
1. Hitung bucket memo dengan timezone `Asia/Jakarta`.
2. Tambahkan search input `Cari memo...` dengan style search chat yang sudah dirapikan.
3. Tambahkan state Alpine `memoHistory` untuk search, collapse, persist section, dan active section auto-open.
4. Tampilkan `Versi N` jika current version tersedia.
5. Update test render memo workspace untuk bucket/search/collapse dan tanpa count kategori.

## Rencana Test
- `php artisan test tests/Feature/Memos/MemoWorkspaceTest.php`
- `npm run build`
- `git diff --check`

## Kriteria Selesai
- Sidebar memo memiliki pola navigasi yang konsisten dengan chat.
- Memo tetap menampilkan metadata dokumen yang relevan.
- Test dan build frontend lulus.
