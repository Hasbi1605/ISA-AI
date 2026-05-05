# Issue - Rapihkan Modal Google Drive Picker Chat

## Latar Belakang
Modal picker Google Drive di `/chat` sudah berfungsi, tetapi tampilannya masih terlalu verbal. Header, breadcrumb, footer, metadata item, dan label aksi membuat modal terasa padat meski isi filenya sedikit.

## Tujuan
Merapikan layout modal picker Google Drive agar lebih ringkas, mudah dipindai, dan lebih seimbang dengan gaya UI chat yang sudah ada.

## Ruang Lingkup
- Ringkas copy di header dan pencarian.
- Rapikan area breadcrumb dan pagination menjadi toolbar yang lebih padat.
- Kompres metadata file/folder agar tidak terlalu banyak teks.
- Sederhanakan label tombol aksi di setiap item.
- Hilangkan footer informatif yang tidak terlalu penting untuk konteks picker chat.

## Di Luar Scope
- Perubahan alur ingest dokumen Google Drive.
- Perubahan upload/export Google Drive di jawaban AI atau document preview.
- Perubahan struktur data atau endpoint backend.

## File Terkait
- `laravel/resources/views/livewire/chat/google-drive-picker.blade.php`
- `laravel/app/Livewire/Chat/GoogleDrivePicker.php`
- `laravel/tests/Feature/Chat/GoogleDrivePickerTest.php`
- `laravel/tests/Feature/Chat/ChatUiTest.php`

## Risiko
- Perubahan copy dapat mempengaruhi assertion test Livewire/HTTP yang sudah ada.
- Tombol yang terlalu ringkas bisa mengurangi kejelasan jika tidak diseimbangkan dengan ikon dan tooltip.

## Langkah Implementasi
1. Ringkas label root folder dan copy header.
2. Ubah header, toolbar, search field, dan kartu item menjadi lebih padat.
3. Sederhanakan CTA seperti `Buka` dan `Pakai`.
4. Sesuaikan test yang memeriksa teks lama.
5. Jalankan test chat picker yang relevan dan build frontend.

## Rencana Test
- `php artisan test tests/Feature/Chat/GoogleDrivePickerTest.php tests/Feature/Chat/ChatUiTest.php`
- `npm run build`

## Kriteria Selesai
- Modal tetap punya fungsi yang sama.
- Teks berkurang dan hierarchy visual lebih jelas.
- Test chat picker tetap lulus.
