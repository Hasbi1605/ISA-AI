# Issue #148 — Harden Chat Ownership, Document Scoping, and Processing Retries

## Latar Belakang

GitHub issue #148 melaporkan tiga temuan audit pada alur chat dan pemrosesan dokumen:

1. `sendMessage()` dapat memakai `currentConversationId` dari public Livewire state tanpa verifikasi ownership conversation.
2. `conversationDocuments` dari client diteruskan ke service dan query dokumen tidak di-scope ulang berdasarkan user/status server-side.
3. `ProcessDocument` mendefinisikan retry queue tetapi `handle()` menangkap exception dan tidak melempar ulang sehingga retry tidak berjalan.

Audit ulang oleh sub-agent read-only dan security reviewer mengonfirmasi ketiga temuan valid.

## Tujuan

- Mencegah user menulis pesan ke conversation milik user lain.
- Memastikan dokumen chat yang dikirim ke AI/RAG hanya dokumen milik user aktif dan berstatus `ready`.
- Mengaktifkan retry queue untuk kegagalan transient pada `ProcessDocument`, serta tetap menandai dokumen `error` setelah retry habis.
- Menambahkan/menyesuaikan test Laravel agar perilaku security dan retry tertutup regresi.

## Ruang Lingkup

- Perbaikan Laravel pada service chat dan job pemrosesan dokumen.
- Test feature/unit relevan untuk chat ownership, document scoping, dan retry `ProcessDocument`.
- Validasi Laravel test dan build frontend bila diperlukan.
- Membuat branch kerja dan PR yang mereferensikan GitHub issue #148.

## Di Luar Scope

- Perubahan UI besar pada halaman chat.
- Perubahan protokol API Python/RAG di luar kebutuhan payload dokumen yang sudah tersaring.
- Perubahan database/migration baru.
- Merge otomatis PR tanpa persetujuan eksplisit user.

## Area / File Terkait

- `laravel/app/Services/ChatOrchestrationService.php`
- `laravel/app/Livewire/Chat/ChatIndex.php` bila perlu penyesuaian error handling Livewire.
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/tests/Feature/Chat/ChatUiTest.php` atau test chat terkait.
- `laravel/tests/Feature/Jobs/ProcessDocumentTest.php`

## Risiko

- `abort(403)` / `AuthorizationException` di Livewire action perlu diverifikasi agar tidak membuat UX chat rusak untuk owner valid.
- Perubahan retry job bisa membuat status dokumen tetap `processing` selama retry berjalan; ini intentional agar tidak menjadi `error` prematur.
- Kegagalan permanen seperti file hilang sebaiknya tidak retry tanpa batas; perlu tetap ditangani sebagai `error` langsung atau failure final yang jelas.
- Test lama yang mengharapkan HTTP 500 langsung menjadi `error` harus diperbarui karena perilaku tersebut adalah bug yang diperbaiki.

## Langkah Implementasi

1. Buat branch kerja `codex/issue-148-chat-security-retry` dari `main`.
2. Di `ChatOrchestrationService`, verifikasi conversation existing dengan `whereKey($id)->where('user_id', Auth::id())`; jika tidak valid, fail closed dengan authorization error.
3. Di query dokumen chat, filter ulang `user_id = Auth::id()` dan `status = ready` sebelum mengambil filename.
4. Di `ProcessDocument`, jangan telan exception transient; rethrow agar queue retry berjalan, dan gunakan `failed()` untuk status `error` setelah retry habis. Pertahankan handling failure permanen bila file tidak ditemukan.
5. Tambah/update test untuk:
   - user B tidak bisa mengirim pesan ke conversation user A;
   - dokumen user lain atau belum `ready` tidak masuk payload dokumen;
   - HTTP/Python service failure melempar exception agar retry queue aktif;
   - `failed()` menandai dokumen `error`.
6. Jalankan test relevan dan readiness checks sebelum commit/PR.

## Rencana Test

- `cd laravel && php artisan test --filter=ChatUiTest`
- `cd laravel && php artisan test --filter=ChatOrchestrationServiceTest` bila file test ada/ditambahkan.
- `cd laravel && php artisan test --filter=ProcessDocumentTest`
- `cd laravel && php artisan test`
- `cd laravel && npm run build` bila perubahan menyentuh asset/frontend atau sebagai readiness build.
- Final verification sesuai `AGENTS.md` setelah review bersih:
  - `cd laravel && php artisan test`
  - `cd python-ai && source venv/bin/activate && pytest`

## Kriteria Selesai

- Ketiga finding issue #148 telah diperbaiki atau didokumentasikan bila ada bagian yang tidak valid.
- Test baru/terupdate membuktikan ownership, document scoping, dan retry behavior.
- Validasi relevan lulus.
- Branch kerja sudah dipush dan PR dibuat dengan referensi `Closes #148` atau link issue #148.
- Preview `https://ista-ai.app` dideploy dari commit PR terbaru.
- PR review loop dan QC final tidak menemukan blocker.
- Tidak ada merge tanpa approval eksplisit user.
