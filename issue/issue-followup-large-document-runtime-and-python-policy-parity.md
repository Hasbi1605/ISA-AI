# Issue: Follow-up Large Document Runtime Error dan Parity Policy Python

## Latar Belakang
Setelah migrasi Laravel-only berjalan, masih ada dua gap nyata dari perilaku produksi:

1. Upload dokumen besar masih kadang gagal di worker dengan error:
   `LaravelDocumentService: process failed ... 401 Invalid authorization header`
2. Orkestrasi chat Laravel belum sepenuhnya mengikuti policy lama di `python-ai`, terutama:
   - smart web search untuk query realtime vs query stabil
   - explicit web request
   - dokumen aktif mematikan auto web search
   - explicit/forced web saat dokumen aktif
   - web rerank dan RAG rerank yang hanya aktif pada flow relevan

Log lokal juga menunjukkan worker `php artisan queue:work` yang aktif dibuat sebelum patch follow-up terakhir, sehingga masih mungkin menjalankan kode lama sampai di-restart.

## Tujuan
- Menghilangkan error upload dokumen besar yang masih jatuh ke provider upload lama.
- Memastikan worker Laravel memakai kode terbaru setelah fix.
- Mengembalikan parity aturan orkestrasi chat dari Python ke Laravel.
- Menambah test regresi untuk policy dan retrieval yang disentuh.

## Ruang Lingkup
- Audit jalur `ProcessDocument` pada worker queue.
- Audit apakah restart worker diperlukan agar code path baru aktif.
- Audit `python-ai/app/main.py`, `rag_policy.py`, dan `rag_retrieval.py` sebagai source of truth policy.
- Patch Laravel chat policy agar regular chat dan chat dokumen konsisten dengan Python.
- Patch retrieval Laravel agar rerank dokumen aktif pada flow RAG yang relevan.

## Di Luar Scope
- Refactor besar UI chat.
- Penggantian provider embedding.
- Pembersihan penuh seluruh warning test lama yang tidak terkait scope ini.

## Fakta Awal
- Log `local.ERROR LaravelDocumentService: process failed ... 401` masih muncul dari `artisan queue:work`.
- Branch kerja sudah berisi patch skip provider upload pada `LaravelDocumentService`, sehingga error yang tetap muncul sangat mungkin berasal dari worker lama yang belum reload kode.
- `DocumentPolicyService` Laravel sudah meniru inti `should_use_web_search()` Python, tetapi `LaravelChatService` untuk chat biasa masih memakai rule sederhana berbasis `source_policy`.
- `LaravelChatService` mode dokumen belum menyertakan `web_context` ke jawaban RAG saat policy mengizinkan explicit/forced web.
- `HybridRetrievalService` Laravel belum menjalankan LangSearch rerank pada kandidat chunk RAG seperti Python.

## File / Area Terkait
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/app/Services/Document/LaravelDocumentService.php`
- `laravel/app/Services/Chat/LaravelChatService.php`
- `laravel/app/Services/Document/DocumentPolicyService.php`
- `laravel/app/Services/Document/HybridRetrievalService.php`
- `python-ai/app/main.py`
- `python-ai/app/services/rag_policy.py`
- `python-ai/app/services/rag_retrieval.py`

## Langkah Implementasi
1. Pastikan worker queue memuat kode terbaru dan tidak lagi memakai jalur provider upload lama.
2. Satukan regular chat Laravel ke policy service yang setara dengan Python.
3. Tambahkan parity pada mode dokumen: explicit/forced web dapat menambah `web_context` ke prompt RAG.
4. Tambahkan document rerank pada hybrid retrieval Laravel dengan konfigurasi yang setara Python.
5. Tambahkan / perbarui test untuk regular chat policy, dokumen + explicit web, dan RAG rerank.
6. Jalankan verifikasi Laravel yang relevan.

## Rencana Test
- `php artisan test --filter=DocumentPolicyServiceTest`
- `php artisan test --filter=LaravelChatServiceTest`
- `php artisan test --filter=HybridRetrievalServiceTest`
- `php artisan test --filter=ProcessDocumentTest`
- Bila perlu, smoke check upload dokumen + chat di browser lokal setelah restart queue worker.

## Kriteria Selesai
- Upload dokumen tidak lagi gagal `401` karena worker memakai code path lama.
- Query pendek/stabil tidak otomatis memicu web search.
- Query realtime atau explicit web kembali memicu web search seperti Python.
- Dokumen aktif mematikan auto web search, kecuali explicit/forced web.
- RAG retrieval Laravel kembali menjalankan rerank kandidat chunk pada flow yang relevan.
