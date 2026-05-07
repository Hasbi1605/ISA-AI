# Issue: Memo Download Harus Mengikuti Versi Yang Sedang Dibuka

## Gejala
- Tombol download DOCX/PDF kadang mengambil versi pertama walaupun dropdown dan OnlyOffice sedang menampilkan versi lain.
- Sebagian download versi terpilih berhasil, lalu kembali mengambil versi pertama.

## Dugaan Akar Masalah
- Dropdown versi memanggil aktivasi versi yang mengubah `memos.file_path` dan `current_version_id`, sehingga state persisten berubah hanya karena user melihat versi lama.
- Callback OnlyOffice hanya membawa `memo_id`, bukan `version_id`, sehingga callback terlambat dari editor versi lama dapat menulis ke file versi yang sedang aktif.
- Tombol download perlu selalu membawa versi yang sedang dipilih dan tidak mengandalkan `memos.file_path`.

## Rencana Perbaikan
- Jadikan `activeMemoVersionId` sebagai sumber kebenaran UI/editor/download.
- `switchMemoVersion` tidak lagi mengubah `memos.file_path/current_version_id`.
- `editorConfig` memilih file dari `MemoVersion` aktif dan mengirim `version_id` pada signed file URL serta callback URL.
- Callback OnlyOffice resolve `version_id` dan hanya menulis ke versi tersebut.
- Callback lama yang belum membawa query `version_id` tetap diarahkan ke versi benar jika key OnlyOffice berisi pola `memo-{id}-v{versionId}-...`.
- Tombol DOCX/PDF menambahkan `version_id` aktual dari dropdown saat klik, dan response download tidak di-cache.

## Verifikasi
- Tambah test bahwa switch versi tidak mengubah current memo.
- Tambah test callback dengan `version_id` hanya memperbarui file versi itu.
- Tambah test callback lama tanpa query `version_id` tetap memperbarui versi dari key, bukan memo aktif.
- Jalankan test memo relevan, Pint, build, dan diff check.
