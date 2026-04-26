# Issue: Follow-up Runtime Error LangSearch Rerank dan Document Process Auth

## Latar Belakang
Setelah chat Laravel-only dan flow dokumen dipindahkan ke `main`, masih muncul dua error runtime nyata saat dipakai dari UI:

1. `LangSearch Rerank: API error code=500, msg=Runtime Exception`
2. `LaravelDocumentService: process failed ... 401 Invalid authorization header`
3. PDF scan/OCR path gagal menjalankan `pdftoppm`, sehingga dokumen image-based jatuh ke jalur fallback yang salah.

Error pertama terjadi setelah web search awal berhasil, sehingga indikasinya ada mismatch payload saat hasil search dikirim ke endpoint rerank. Error kedua terjadi di worker dokumen walaupun mode local RAG sudah aktif dan file provider-managed seharusnya tidak wajib dipakai.

## Tujuan
- Menghilangkan error rerank LangSearch tanpa merusak fallback ke hasil search biasa.
- Menghilangkan error 401 pada proses dokumen di mode local RAG.
- Menjaga perubahan tetap minimal dan menambah test pada perilaku yang diperbaiki.

## Ruang Lingkup
- Audit shape payload `LangSearchService::rerank()`.
- Audit jalur upload file provider di `LaravelDocumentService::processDocument()`.
- Audit command rendering PDF ke image pada OCR fallback.
- Patch service yang relevan.
- Tambah/ubah test Laravel yang langsung menutup regresi ini.

## Di Luar Scope
- Refactor besar arsitektur web search atau dokumen.
- Perubahan provider/model cascade di luar yang dibutuhkan untuk bugfix ini.
- Pembersihan log atau observability di area lain.

## Area / File Terkait
- `laravel/app/Services/LangSearchService.php`
- `laravel/app/Services/Chat/LaravelChatService.php`
- `laravel/app/Services/Document/LaravelDocumentService.php`
- `laravel/app/Services/Document/Parsing/PdfToImageRenderer.php`
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/tests/Unit/Services/LangSearchServiceTest.php`
- `laravel/tests/Unit/Services/Document/LaravelDocumentServiceTest.php`
- `laravel/tests/Unit/Services/Document/Parsing/OcrServicesTest.php`
- `laravel/tests/Feature/Jobs/ProcessDocumentTest.php`

## Akar Masalah Awal
- `rerank()` menerima hasil `search()` mentah (`title`, `snippet`, `url`), lalu mengirimkannya langsung sebagai `documents`, padahal endpoint rerank mengharapkan shape dokumen yang lebih terstruktur untuk ranking.
- `processDocument()` selalu memanggil `Files::put(...)` sebelum parsing lokal, padahal `use_provider_file_search=false` dan env Laravel tidak memakai `OPENAI_API_KEY`, sehingga request upload file ke provider gagal `401`.
- `PdfToImageRenderer` membangun command `pdftoppm` dengan quoting flag yang salah (`'-r200'`, `-'png'`), sehingga OCR fallback gagal pada PDF scanned/image-based.
- Saat parse lokal gagal dan runtime `document_process` tetap `laravel`, job masih mencoba fallback capability yang tidak benar-benar alternatif, sehingga error auth provider terlihat menutupi akar masalah OCR.

## Langkah Implementasi
1. Normalisasi dokumen rerank dari hasil search menjadi payload yang valid.
2. Pertahankan fallback aman bila rerank gagal.
3. Benahi command `pdftoppm` untuk OCR fallback PDF.
4. Hindari fallback runtime dokumen yang semu saat local RAG Laravel-only aktif.
5. Lewati upload file provider saat mode local RAG aktif pada path yang memang langsung memanggil service dokumen.
6. Tambahkan test untuk perilaku di atas.
7. Jalankan test Laravel yang relevan.

## Rencana Test
- `php artisan test --filter=LangSearchServiceTest`
- `php artisan test --filter=LaravelDocumentServiceTest`
- `php artisan test --filter=OcrServicesTest`
- `php artisan test --filter=ProcessDocumentTest`
- Jika perlu, jalankan subset chat yang memakai LangSearch.

## Kriteria Selesai
- Query chat tidak lagi memunculkan error rerank karena payload invalid.
- Queue process dokumen tidak lagi gagal `401` saat local RAG aktif.
- PDF scanned/image-based bisa melewati OCR fallback dengan command `pdftoppm` yang benar.
- Test regresi untuk dua bug ini sudah ada dan lulus.
