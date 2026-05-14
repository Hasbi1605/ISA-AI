# Issue: Perbaiki Regression UI Memo Workspace

## Latar Belakang
Halaman memo di tab chat mengalami beberapa regression UI dan state setelah perubahan terbaru: toggle Chat/Memo tidak selalu menandai tab aktif, tombol kembali ke Chat dari tab Memo memicu error server, layout toggle memo terasa sempit, tab Editor sulit kembali ke Preview, serta chat memo tidak boleh hilang ketika user berpindah memo dari riwayat.

## Tujuan
- Sidebar kiri memo konsisten dengan sidebar chat: tampil "Kembali ke Beranda" dengan ikon rumah dan ukuran area yang sama.
- Toggle Chat/Memo memakai state parent ChatIndex, bukan property child MemoWorkspace, sehingga tidak memicu 500.
- Toggle aktif terlihat jelas dengan background merah/primary.
- Toggle di tab Memo diletakkan di area header yang lebih lega.
- Preview/Editor dapat berpindah dua arah secara stabil.
- Composer memo lebih proporsional pada kolom sempit.
- Avatar ISTA AI dan user di chat memo konsisten dengan halaman chat utama.
- Riwayat chat memo tetap ada saat user membuka memo dari daftar riwayat, termasuk setelah reload halaman.

## Scope
- `laravel/app/Livewire/Memos/MemoWorkspace.php`
- `laravel/app/Models/Memo.php`
- `laravel/database/migrations/*_add_chat_messages_to_memos_table.php`
- `laravel/resources/views/livewire/chat/chat-index.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-memo-tab-toggle.blade.php`
- `laravel/resources/views/livewire/memos/partials/*.blade.php`
- `laravel/resources/js/chat-page.js`
- Test Livewire terkait chat UI dan memo workspace.

## Risiko
- Riwayat chat memo yang dipertahankan pada patch ini adalah transcript sederhana per memo, bukan version history editor DOCX.
- OnlyOffice tetap perlu QA manual karena test tidak menjalankan Document Server sungguhan.

## Langkah Implementasi
1. Ubah ChatIndex root agar punya state Alpine `activeTab` dan handler event `chat-tab-switch`.
2. Ubah partial toggle Chat/Memo agar mengirim event ke parent dan memberi state active dari `activeTab`.
3. Pindahkan toggle di tab Memo dari header kolom chat ke header panel kanan, berdampingan dengan Preview/Editor.
4. Rapikan sidebar memo agar mengikuti ukuran dan header sidebar chat.
5. Rapikan composer memo dan samakan avatar dengan chat utama.
6. Tambahkan penyimpanan thread memo per memo di `MemoWorkspace`.
7. Simpan transcript chat memo ke record `memos` agar bisa dipulihkan setelah reload.
8. Pakai method `switchPreviewMode()` dan z-index header agar Preview/Editor stabil.
9. Tambahkan test relevan dan jalankan verifikasi Laravel.

## Rencana Test
- `cd laravel && php artisan test tests/Feature/Chat/ChatUiTest.php tests/Feature/Memos/MemoWorkspaceTest.php`
- `cd laravel && ./vendor/bin/pint --test`
- `cd laravel && npm run build`

## Kriteria Selesai
- Toggle Chat/Memo tidak lagi memanggil property yang tidak ada di `MemoWorkspace`.
- UI memo sesuai permintaan visual utama.
- Test relevan hijau atau kegagalan lingkungan dicatat jelas.
