# Peningkatan ISTA AI: Chat, Memo Mobile, Typewriter, Drive Picker, History, dan Dark Mode Dashboard

GitHub Issue: https://github.com/Hasbi1605/ISTA-AI/issues/133

## Latar Belakang
Beberapa regresi dan inkonsistensi UI muncul pada ISTA AI: tampilan bubble chat belum konsisten dengan memo, tab memo sulit dipakai di mobile, efek typewriter jawaban AI tidak berjalan lagi, pemilihan file dari Google Drive belum langsung masuk ke input chat, loading tombol Drive bersifat global, grouping history `Today` tidak berbasis tanggal hari ini, dan dashboard awal belum mengikuti dark mode seperti `/chat` dan tab memo.

## Tujuan
- Menyamakan pengalaman visual chat dengan tab memo.
- Membuat tab memo usable pada viewport mobile.
- Mengembalikan efek jawaban AI yang smooth/typewriter untuk jawaban baru.
- Memastikan file Google Drive yang dipilih dari input chat langsung terpilih sebagai dokumen percakapan.
- Membatasi loading tombol `Pakai` hanya pada file Drive yang sedang diproses.
- Memperbaiki grouping history chat agar `Today` hanya berisi percakapan hari ini.
- Menambahkan dark mode dashboard awal yang konsisten dengan `/chat` dan memo.

## Ruang Lingkup
1. Styling bubble user/output chat di tab chat agar ber-background merah seperti bubble user pada panel chat memo.
2. Responsive layout tab memo untuk mobile dengan kontrol berpindah antara panel chat dan panel dokumen.
3. Investigasi dan perbaikan flow typewriter jawaban assistant pada chat.
4. Integrasi hasil import Google Drive ke `conversationDocuments` input chat.
5. Perbaikan loading state tombol `Pakai` pada modal Google Drive picker.
6. Grouping history sidebar chat berdasarkan `updated_at` dalam timezone `Asia/Jakarta`.
7. Dark mode dashboard standalone dan menu profile dashboard.

## Di Luar Scope
- Perubahan besar arsitektur streaming AI/Python kecuali terbukti diperlukan untuk bug typewriter.
- Redesign total dashboard, chat, atau memo di luar konsistensi dark mode/responsive yang diminta.
- Perubahan model database atau migrasi data.
- Perubahan behavior pemrosesan dokumen selain menambahkan dokumen hasil Drive ke konteks percakapan.

## Area / File Terkait
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/app/Livewire/Chat/GoogleDrivePicker.php`
- `laravel/app/Services/Chat/ChatDocumentStateService.php`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-composer.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-left-sidebar.blade.php`
- `laravel/resources/views/livewire/chat/google-drive-picker.blade.php`
- `laravel/resources/views/livewire/memos/memo-workspace.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-preview-panel.blade.php`
- `laravel/resources/js/chat-page.js`
- `laravel/resources/views/dashboard.blade.php`
- `laravel/resources/views/livewire/dashboard-nav-profile.blade.php`
- Test Laravel terkait chat, Google Drive picker, dan state dokumen.

## Risiko
- Efek typewriter melibatkan Livewire streaming + Alpine; perubahan harus menjaga state streaming agar tidak double-render atau menghapus jawaban.
- File Google Drive hasil import bisa berstatus `pending/processing`; chip input perlu bisa tampil tanpa memaksa dokumen siap dipakai di AI sebelum status `ready`.
- Responsive memo mobile harus tidak mengganggu desktop layout dan OnlyOffice preview.
- Dark mode dashboard standalone perlu inisialisasi `localStorage.theme` sendiri agar tidak FOUC dan konsisten dengan layout `/chat`.
- Timezone history harus eksplisit `Asia/Jakarta` agar grouping `Today` tidak tergantung timezone server.

## Langkah Implementasi
1. Buat branch kerja dan GitHub Issue dari plan ini.
2. Perbaiki bubble user chat dan optimistic user message agar memakai background `bg-ista-primary text-white`.
3. Ubah grouping history sidebar chat menjadi `todayChats` dan `olderChats` berdasarkan `updated_at` timezone `Asia/Jakarta`.
4. Scope loading tombol Google Drive picker per file ID.
5. Saat `google-drive-document-imported`, reload dokumen lalu tambahkan `documentId` ke `conversationDocuments`, dan kirim preview event agar chip muncul di composer tanpa membuka Dokumen Saya.
6. Perbaiki flow typewriter dengan menjaga `newMessageId` setelah reload conversation dan mencegah event `message-complete` mematikan animasi persisted terlalu cepat.
7. Tambahkan responsive state `memoMobilePanel`/preview toggle di memo workspace, lalu hide/show panel chat dan preview dengan layout mobile yang jelas.
8. Tambahkan dark mode dashboard: init `darkMode`, toggle button, class `dark`, dark variants pada body/header/search/cards/footer/profile menu.
9. Tambahkan/ubah test untuk ChatIndex history + Drive import dan GoogleDrivePicker loading markup bila memungkinkan.
10. Jalankan verifikasi relevan Laravel dan frontend build/lint yang tersedia.

## Rencana Test
- `cd laravel && php artisan test --filter=ChatUiTest`
- `cd laravel && php artisan test --filter=GoogleDrivePickerTest`
- `cd laravel && php artisan test --filter=ChatDocumentStateServiceTest`
- Jika ada test baru spesifik: jalankan filter nama test tersebut.
- `cd laravel && npm run build` untuk validasi asset Blade/JS/Tailwind.
- Manual/visual: chat bubble merah, memo mobile 375px/768px/desktop, typewriter jawaban baru, Drive picker satu tombol loading, history `Today`, dashboard dark mode.

## Kriteria Selesai
- Semua 7 item permintaan terimplementasi sesuai scope.
- Issue markdown dan GitHub Issue tersedia sebagai acuan.
- Test relevan dan build frontend berjalan dengan hasil jelas.
- Review paralel final tidak menemukan blocker.
- Perubahan di-commit, di-push ke branch kerja, dan PR dibuat.
