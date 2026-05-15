# Bug #155: Edit manual di OnlyOffice hilang saat revisi AI berikutnya

## Latar Belakang

Saat user generate memo lewat AI lalu mengedit manual di OnlyOffice (misal benerin typo, ubah paragraf), perubahan tersimpan ke file DOCX via callback. Namun kolom `searchable_text` di `MemoVersion` dan `Memo` tidak diperbarui — tetap berisi teks dari versi AI sebelumnya.

Saat user minta revisi berikutnya ke AI, `MemoWorkspace::generateRevisionFromChat` membaca `searchable_text` (bukan konten DOCX aktual) sebagai konteks revisi. Akibatnya AI mengabaikan edit manual dan mengembalikan konten ke versi lama.

## Tujuan

Setelah callback OnlyOffice status 2 (save) atau 6 (force save), ekstrak ulang teks dari DOCX yang baru disimpan dan update `searchable_text` di `MemoVersion` dan `Memo`. Dengan begitu revisi AI berikutnya selalu membaca konten terkini.

## Ruang Lingkup

- Ekstrak teks DOCX menggunakan `phpoffice/phpword` (sudah terpasang) setelah file disimpan di callback.
- Update `searchable_text` di `MemoVersion` (jika ada) dan `Memo`.
- Buat service/helper untuk ekstraksi teks DOCX agar bisa di-test dan di-reuse.
- Update/tambah test `OnlyOfficeCallbackTest`.

## Di Luar Scope

- Perubahan pada alur generate/revisi AI itu sendiri.
- Perubahan pada Python service.
- Perubahan pada UI/Livewire.
- Implementasi OCR untuk PDF scan.
- Bug lain dari audit (#156-#164).

## Area / File Terkait

- `laravel/app/Http/Controllers/OnlyOfficeCallbackController.php` — tempat utama perubahan (setelah file disimpan, ekstrak teks).
- `laravel/app/Services/OnlyOffice/DocxTextExtractor.php` — **file baru** service ekstraksi teks.
- `laravel/app/Models/Memo.php` — update `searchable_text`.
- `laravel/app/Models/MemoVersion.php` — update `searchable_text`.
- `laravel/tests/Feature/Memos/OnlyOfficeCallbackTest.php` — tambah test case.

## Risiko

- `phpoffice/phpword` bisa throw exception untuk DOCX yang corrupt/tidak valid. Harus di-wrap try/catch agar callback tetap return `{"error": 0}` (OnlyOffice mengharapkan response ini selalu sukses).
- Ekstraksi teks bisa lambat untuk DOCX besar. Acceptable karena callback sudah sinkron dan OnlyOffice menunggu response.
- Jika ekstraksi gagal, `searchable_text` tetap nilai lama — lebih baik daripada callback error yang menyebabkan OnlyOffice retry loop.

## Langkah Implementasi

1. Buat `laravel/app/Services/OnlyOffice/DocxTextExtractor.php`:
   - Method `extract(string $absolutePath): string`
   - Gunakan `PhpOffice\PhpWord\IOFactory::load()` + iterasi sections/paragraphs
   - Return string teks bersih (tanpa markup)
   - Wrap dalam try/catch, return `''` jika gagal

2. Update `OnlyOfficeCallbackController::__invoke`:
   - Setelah blok `Storage::disk('local')->put($path, $response->body())` (baris ~47)
   - Panggil `DocxTextExtractor::extract($absolutePath)`
   - Jika hasil tidak kosong, update `searchable_text` di version dan memo

3. Update test `OnlyOfficeCallbackTest`:
   - Tambah test: setelah callback status 2, `searchable_text` di `MemoVersion` dan `Memo` berubah sesuai konten DOCX baru
   - Tambah test: jika ekstraksi gagal (DOCX corrupt), callback tetap return `{"error": 0}` dan `searchable_text` tidak berubah

## Rencana Test

```
php artisan test --filter OnlyOfficeCallbackTest
```

Test baru yang perlu ditambah:
- `test_callback_status_2_updates_searchable_text`
- `test_callback_status_6_updates_searchable_text`
- `test_callback_still_succeeds_if_text_extraction_fails`

## Kriteria Selesai

- [ ] `DocxTextExtractor` service dibuat dan bisa di-test secara unit
- [ ] Callback status 2 dan 6 mengupdate `searchable_text` di `MemoVersion` dan `Memo`
- [ ] Jika ekstraksi gagal, callback tetap return `{"error": 0}`
- [ ] Semua test `OnlyOfficeCallbackTest` pass
- [ ] Tidak ada regresi di test lain
- [ ] PR dibuat dan siap review
