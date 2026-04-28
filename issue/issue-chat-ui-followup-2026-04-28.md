# Issue: Follow-up UI Chat dan Dokumen

## Latar Belakang

Ada tiga kebutuhan UI lanjutan pada halaman chat:

1. `Shift + Enter` di input chat belum bisa membuat baris baru.
2. Hero tengah berisi logo dan teks pembuka harus hilang segera saat user pertama kali mengirim pesan.
3. Perlu loading animation dan flash message yang jelas saat dokumen berhasil atau gagal dihapus.

## Tujuan

- Memperbaiki ergonomi penulisan pesan multi-baris.
- Membuat state awal halaman chat terasa lebih natural saat percakapan dimulai.
- Memberi feedback yang jelas untuk aksi hapus dokumen.

## Rencana

1. Ubah handler keyboard textarea agar `Enter` mengirim dan `Shift + Enter` tetap membuat newline.
2. Gunakan state optimistik di area pesan supaya hero tengah hilang saat pesan pertama dikirim, tidak menunggu round-trip penuh dari Livewire.
3. Tambahkan state loading delete di Livewire `ChatIndex` dan render flash banner pada sidebar dokumen.
4. Tambahkan test Livewire untuk flash message sukses hapus dokumen.
5. Jalankan build frontend dan test Laravel yang relevan.

## Risiko

- Perubahan state optimistik bisa menimbulkan duplikasi tampilan pesan jika tidak sinkron dengan `optimisticUserMessage`.
- Loading delete harus spesifik per dokumen agar tidak membuat seluruh sidebar tampak macet.

## Verifikasi

- `cd laravel && npm run build`
- `cd laravel && php artisan test --filter=AuthenticationTest::test_chat_page_can_be_rendered_for_authenticated_user`
- `cd laravel && php artisan test --filter=DocumentDeletionTest`
