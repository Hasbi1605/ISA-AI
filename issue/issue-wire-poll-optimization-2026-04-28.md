# Judul

Optimasi `wire:poll` agar hanya aktif saat dokumen benar-benar diproses

## Latar Belakang

Polling Livewire saat ini masih berjalan di beberapa area UI dokumen, termasuk saat halaman sedang idle. Pola ini menambah request berkala, re-hydration Livewire, dan query database yang sebenarnya tidak dibutuhkan ketika semua dokumen sudah `ready`.

Sebelumnya polling membantu menjaga status upload/proses tetap terasa hidup. Namun untuk penggunaan harian dengan banyak pengguna, polling yang terus aktif saat idle tetap menjadi beban yang bisa dihindari.

## Tujuan

- Mengurangi request berkala saat user hanya membuka halaman tanpa melakukan aksi apa pun.
- Tetap mempertahankan update status dokumen ketika ada file yang `pending` atau `processing`.
- Menjaga UX tetap masuk akal tanpa membuat halaman terasa basi saat proses dokumen berjalan.

## Ruang Lingkup

- Mengubah polling di sidebar dokumen chat agar hanya aktif ketika ada dokumen dalam status proses.
- Mengubah polling di halaman daftar dokumen agar tidak aktif saat semua dokumen sudah selesai diproses.
- Menambahkan atau menyesuaikan test untuk memastikan polling hanya muncul pada kondisi yang memang perlu.

## Di Luar Scope

- Menghapus seluruh mekanisme refresh status dokumen.
- Mengganti polling dengan websocket atau event broadcasting.
- Mengubah alur upload, delete, atau summarize dokumen.
- Mengubah kualitas RAG atau model AI.

## Area / File Terkait

- `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php`
- `laravel/resources/views/livewire/documents/document-index.blade.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/app/Livewire/Documents/DocumentIndex.php`
- test Livewire untuk chat dan dokumen

## Risiko

- Jika polling dimatikan terlalu agresif, status `processing` bisa terlambat berubah menjadi `ready` di UI.
- Perubahan atribut polling pada root view harus tetap kompatibel dengan Livewire agar tidak memicu perilaku re-render yang aneh.
- Test HTML perlu cukup spesifik agar tidak false positive.

## Langkah Implementasi

1. Tambahkan indikator kondisi dokumen sedang diproses pada halaman dokumen yang bisa dipakai untuk menentukan polling.
2. Ubah sidebar chat agar polling hanya aktif ketika ada dokumen `pending` atau `processing`.
3. Ubah halaman daftar dokumen agar polling hanya aktif pada kondisi yang sama, bukan terus-menerus.
4. Tambahkan test yang membuktikan polling ada saat proses berlangsung dan tidak ada saat idle.
5. Jalankan verifikasi Laravel yang terdampak.

## Rencana Test

- `cd laravel && php artisan test tests/Feature/Documents/DocumentIndexTest.php tests/Feature/Chat/DocumentUploadTest.php`
- Bila perlu, jalankan subset test tambahan yang memeriksa render HTML Livewire.

## Kriteria Selesai

- Polling UI dokumen tidak berjalan terus saat semua dokumen sudah selesai.
- Status dokumen tetap ter-update otomatis saat ada dokumen yang sedang diproses.
- Test relevan lulus.
- Perubahan tidak mengganggu alur upload, delete, atau summarize dokumen.
