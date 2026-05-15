# Issue #189 — Streaming Live End-to-End untuk Chat, Web Search, dan Chat Dokumen

## Latar Belakang

Python AI sudah mengirim `StreamingResponse` SSE. Laravel `AIService` sudah membaca chunk via generator. Namun `GenerateChatResponse` job mengakumulasi seluruh chunk ke `$fullResponse` sebelum menyimpan satu pesan final ke DB. UI mengetahui jawaban selesai hanya lewat `wire:poll.3s`, sehingga user melihat layar kosong/placeholder sampai seluruh jawaban selesai.

`chat-page.js` sudah memiliki listener `assistant-output`, `model-name`, `assistant-sources` via `$wire.on()`, tapi tidak ada yang mendispatch event tersebut dari Laravel.

## Tujuan

Aktifkan streaming live sampai UI untuk tiga mode:
- Chat biasa
- Web search
- Chat dengan dokumen

Target: token/chunk mulai terlihat segera setelah diterima dari Python, sementara persistensi final assistant message ke DB tetap dipertahankan.

## Arsitektur Solusi

### Pendekatan: SSE Endpoint Laravel + EventSource di JS

Alur baru:
1. User kirim pesan → `ChatIndex::sendMessage()` dispatch `GenerateChatResponse` job (tetap sama)
2. JS membuka `EventSource` ke endpoint SSE baru: `GET /chat/stream/{conversationId}`
3. Endpoint SSE memanggil `AIService::sendChat()` langsung (bukan via job), stream chunk ke browser sebagai SSE events
4. Setelah stream selesai, endpoint menyimpan final message ke DB
5. Polling `wire:poll.3s` tetap sebagai fallback/recovery

### Mengapa SSE endpoint, bukan Livewire broadcast?

- Livewire tidak mendukung server-push native tanpa WebSocket/Reverb
- SSE adalah HTTP standar, tidak butuh infrastruktur tambahan
- `DocumentPreviewController` sudah pakai `StreamedResponse` sebagai preseden
- JS sudah punya `registerWireListener('assistant-output', ...)` — tinggal feed dari SSE

### Alur Detail

```
Browser                    Laravel                      Python AI
  |                           |                              |
  |-- POST /livewire/... ---> |                              |
  |   (sendMessage)           |-- dispatch job (queue) ----> |
  |<-- user-message-acked --- |                              |
  |                           |                              |
  |-- GET /chat/stream/{id} ->|                              |
  |   (EventSource)           |-- POST /api/chat ----------->|
  |                           |<-- SSE chunks ---------------| 
  |<-- SSE: chunk ----------- |                              |
  |<-- SSE: chunk ----------- |                              |
  |<-- SSE: done ------------ |                              |
  |   (message saved to DB)   |                              |
  |                           |                              |
  | (wire:poll detects done)  |                              |
  |<-- refreshPendingChatState|                              |
```

## Scope Implementasi

### File Baru
- `laravel/app/Http/Controllers/Chat/ChatStreamController.php` — SSE endpoint
- `laravel/tests/Feature/Chat/ChatStreamTest.php` — test untuk endpoint streaming

### File Diubah
- `laravel/routes/web.php` — tambah route SSE
- `laravel/resources/js/chat-page.js` — buka EventSource, feed chunk ke `streamingText`
- `laravel/app/Jobs/GenerateChatResponse.php` — tidak perlu diubah (job tetap sebagai fallback/persistensi)

### Out of Scope
- Mengubah Python AI
- Mengubah kualitas RAG/retrieval
- Refactor besar UI chat

## Rencana Implementasi

### Step 1: ChatStreamController
- Route: `GET /chat/stream/{conversationId}` (auth + verified)
- Validasi: conversation harus milik user yang login
- Baca parameter dari query string: `history` (JSON), `documentIds` (JSON array), `webSearchMode` (bool)
- Panggil `AIService::sendChat()` langsung
- Stream chunk sebagai SSE: `data: {chunk}\n\n`
- Kirim event khusus untuk sources dan model name
- Setelah stream selesai, simpan final message ke DB via `ChatOrchestrationService`
- Handle error: kirim SSE error event, jangan duplicate message

### Step 2: Route
```php
Route::get('chat/stream/{conversationId}', [ChatStreamController::class, 'stream'])
    ->middleware(['auth', 'verified'])
    ->whereNumber('conversationId')
    ->name('chat.stream');
```

### Step 3: JS — EventSource
- Di `chatMessages` Alpine component, setelah `user-message-acked`, buka `EventSource`
- Listen event `chunk` → append ke `streamingText`
- Listen event `done` → tutup EventSource, biarkan polling handle final state
- Listen event `error` → tutup EventSource, tampilkan error
- Tutup EventSource saat conversation berganti atau component destroy

## Idempotensi & Error Handling

- `ChatStreamController` cek apakah sudah ada assistant message setelah user message terakhir → jika ya, skip (job sudah selesai duluan)
- Jika stream gagal di tengah jalan, job queue tetap berjalan dan akan menyimpan final message
- Polling `wire:poll.3s` tetap sebagai recovery path
- Tidak ada duplicate: controller hanya simpan jika belum ada assistant message untuk conversation tersebut setelah user message terakhir

## Acceptance Criteria

- [ ] Chat biasa menampilkan output bertahap sebelum job/response selesai total
- [ ] Web search tetap menampilkan jawaban dan sources dengan benar
- [ ] Chat dokumen tetap menampilkan jawaban dan referensi dokumen dengan benar
- [ ] Jika stream gagal di tengah jalan, UI bisa recover melalui polling/final DB state
- [ ] Tidak ada duplicate assistant message pada retry/refresh
- [ ] Test relevan Laravel ditambahkan/diupdate

## Risiko

- SSE endpoint memanggil Python AI langsung (bukan via job), artinya ada dua jalur ke Python: SSE endpoint dan job queue. Perlu idempotensi ketat agar tidak double-save.
- Timeout PHP: SSE endpoint butuh `set_time_limit(0)` atau nilai besar
- Koneksi browser putus: PHP `connection_aborted()` check di loop streaming
