# Issue #201 — Hotfix Retrieval Dokumen Gagal Tidak Muncul Sebagai Error UI

## Gejala

Setelah rangkaian perubahan issue #188, chat dengan dokumen dapat mengembalikan pesan fallback normal:

> Saya belum bisa membaca konteks dari dokumen yang dipilih saat ini...

Namun UI tidak menandainya sebagai error/gagal. Dari log production, retrieval sempat gagal dengan error Chroma:

```text
combined document_id filter failed (Error executing plan: Internal error: Error finding id)
Error searching chunks: Error executing plan: Internal error: Error finding id
```

## Fakta

- Dokumen aktif sudah dipilih dan statusnya terlihat dapat dipakai di UI.
- Python `chat_api.py` menganggap `success=False` sebagai `doc_error`, tetapi mengirim teks fallback biasa.
- Laravel hanya menandai bubble sebagai `is_error=true` jika stream diawali sentinel `AIService::ERROR_SENTINEL`.
- `ChatStreamController` saat ini dapat mengirim sentinel sebagai chunk biasa sebelum mendeteksi error di akhir stream.
- Error Chroma terjadi pada vector similarity search, sementara metadata/text corpus bisa tersedia setelah reprocess selesai.

## Tujuan

1. Retrieval failure dokumen tampil sebagai error terstruktur di UI, bukan jawaban normal.
2. Streaming tidak menampilkan sentinel mentah ke user.
3. Retrieval lebih tahan terhadap error vector search Chroma dengan fallback BM25 dari corpus bila memungkinkan.
4. Perubahan minimal dan tidak mengubah kualitas jawaban saat retrieval normal berhasil.

## Scope

- `python-ai/app/chat_api.py`
- `python-ai/app/services/rag_retrieval.py`
- `laravel/app/Http/Controllers/Chat/ChatStreamController.php`
- Test Python dan Laravel terkait.

## Verifikasi

- Python targeted tests untuk chat API dan retrieval fallback.
- Laravel targeted tests untuk sentinel streaming.
- Full test relevan jika targeted test hijau.
