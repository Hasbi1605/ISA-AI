# Issue Plan: Migrasi Lifecycle Dokumen dan Summarization ke Laravel AI SDK

## Latar Belakang
Issue ini adalah turunan dari issue `#67` untuk memindahkan lifecycle dokumen dasar dari Python ke Laravel. Fokus tahap ini adalah upload, penyimpanan file provider-managed, status proses dokumen, summarization, dan cleanup file. Tahap ini sengaja belum mencakup retrieval/RAG dokumen penuh agar scope tetap terkendali.

Saat ini alurnya tersebar di:

- `laravel/app/Services/DocumentLifecycleService.php`
- `laravel/app/Jobs/ProcessDocument.php`
- `python-ai/app/routers/documents.py`
- `python-ai/app/services/rag_ingest.py`
- `python-ai/app/services/rag_summarization.py`

## Tujuan
- Memindahkan lifecycle upload/process/summarize/delete dokumen ke Laravel-only.
- Menyimpan file dokumen ke provider-managed file store jika dibutuhkan oleh agent/vector store.
- Menjaga status dokumen (`pending`, `processing`, `ready`, `error`) tetap jelas dan konsisten.

## Ruang Lingkup
- Refactor upload/process agar tidak lagi mengirim file ke Python `/api/documents/process`.
- Menetapkan metadata dokumen yang harus disimpan di database untuk file provider-managed.
- Migrasi summarization dokumen ke Laravel AI SDK dengan attachment/provider file.
- Menjaga delete cleanup agar file provider dan metadata lokal konsisten.
- Menentukan jalur fallback bila provider belum siap/ready saat dokumen baru diunggah.

## Di Luar Scope
- Retrieval/RAG dokumen saat chat.
- File search/vector store filtering di jalur chat.
- Penghapusan akhir `python-ai`.
- Re-ingest dokumen lama untuk cutover final.

## Area / File Terkait
- `laravel/app/Services/DocumentLifecycleService.php`
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/app/Livewire/Documents/DocumentIndex.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/tests/Feature/Chat/DocumentUploadTest.php`
- `laravel/tests/Feature/Documents/DocumentDeletionTest.php`
- `laravel/tests/Feature/Jobs/ProcessDocumentTest.php`
- `python-ai/app/routers/documents.py`
- `python-ai/app/services/rag_summarization.py`

## Risiko
- Shape status dokumen berubah dan membingungkan UI.
- Summarization provider-managed tidak setara untuk dokumen besar jika batching/hierarchical flow tidak dirancang.
- Metadata file provider tidak cukup untuk mendukung issue retrieval berikutnya.
- Delete flow bisa meninggalkan orphan file/provider artifact.

## Langkah Implementasi
1. Definisikan metadata dokumen baru yang perlu disimpan untuk provider file storage.
2. Ubah job/process flow agar file di-upload/disiapkan oleh Laravel runtime baru.
3. Pindahkan summarization ke Laravel AI SDK dengan attachment file atau batching yang setara.
4. Pertahankan guard summarization hanya untuk dokumen yang sudah `ready`.
5. Rapikan delete flow agar cleanup lokal dan provider konsisten.
6. Integrasikan capability ini lewat boundary dan feature flag.
7. Pastikan issue retrieval berikutnya bisa memakai metadata yang dihasilkan tahap ini.

## Rencana Test
- Perluas test Laravel untuk:
  - upload dokumen via chat
  - upload dokumen via document index
  - process status transition
  - summarize dokumen ready
  - summarize reject non-ready
  - delete dokumen membersihkan artefak terkait
- Tambahkan skenario dokumen besar pada summarization jika batching diperlukan.

## Kriteria Selesai
- Upload/process/summarize/delete dokumen dapat berjalan tanpa Python untuk capability dasar.
- Status dokumen tetap konsisten dan aman bagi UI existing.
- Metadata file provider-managed sudah cukup untuk dipakai issue retrieval berikutnya.
- Full test Laravel pada area lifecycle dokumen tetap memadai.
