# Bug #156: Jawaban AI bisa hilang diam-diam saat Auth::loginUsingId gagal di queue

## Latar Belakang

`GenerateChatResponse` adalah queue job yang memproses respons AI di background. Di awal `handle()`, job memanggil `Auth::loginUsingId($this->userId)` untuk set konteks auth. Namun method ini return `false` (tidak throw) jika user tidak ditemukan.

Setelah itu, `ChatOrchestrationService::saveAssistantMessage` memanggil `conversationExists()` yang menggunakan `Auth::id()` — bukan `$this->userId`. Jika `loginUsingId` gagal, `Auth::id()` menjadi `null`, `conversationExists` return false, dan jawaban AI tidak tersimpan. User tidak mendapat balasan dan tidak ada pesan error.

Inkonsistensi ini juga terlihat di `conversationStillExists()` di job yang sama — method itu sudah benar menggunakan `$this->userId`, tapi `saveAssistantMessage` tidak.

## Tujuan

Hilangkan ketergantungan `ChatOrchestrationService::saveAssistantMessage` dan `conversationExists` pada `Auth::id()`. Propagate `userId` eksplisit dari job ke service sehingga jawaban selalu tersimpan ke conversation yang benar, terlepas dari state auth di queue worker.

## Ruang Lingkup

- Update signature `ChatOrchestrationService::saveAssistantMessage(int $conversationId, string $content, int $userId)`.
- Update `conversationExists` menjadi `protected` dengan parameter `userId` eksplisit.
- Update `createAssistantMessage` untuk tidak bergantung pada `Auth::id()`.
- Update `GenerateChatResponse::handle` untuk pass `$this->userId`.
- Update semua caller `saveAssistantMessage` yang ada.
- Tambah/update test.

## Di Luar Scope

- Perubahan pada alur chat UI/Livewire.
- Perubahan pada Python service.
- Bug lain dari audit (#155, #157-#164).
- Refactor `Auth::loginUsingId` di job (tetap dipertahankan untuk keperluan lain yang mungkin butuh auth context).

## Area / File Terkait

- `laravel/app/Services/ChatOrchestrationService.php` — perubahan utama: `saveAssistantMessage`, `conversationExists`, `createAssistantMessage`.
- `laravel/app/Jobs/GenerateChatResponse.php` — update call ke `saveAssistantMessage`.
- `laravel/tests/Feature/Chat/ChatUiTest.php` — update/tambah test.
- `laravel/tests/Unit/Services/ChatOrchestrationServiceTest.php` — update/tambah test.

## Risiko

- Perubahan signature `saveAssistantMessage` bisa break caller lain. Perlu audit semua pemanggil.
- `conversationExists` saat ini juga dipakai di `createConversationIfNeeded` via `Auth::id()` — perlu dicek apakah perlu diubah juga atau cukup scope ke `saveAssistantMessage`.
- Perubahan minimal: hanya ubah path yang bermasalah, jangan refactor seluruh service.

## Langkah Implementasi

1. Audit semua caller `saveAssistantMessage` di codebase.
2. Update `ChatOrchestrationService`:
   - `saveAssistantMessage(int $conversationId, string $content, int $userId)` — tambah param `$userId`.
   - `conversationExists(int $conversationId, int $userId)` — tambah param `$userId`, hapus `Auth::id()`.
   - `createAssistantMessage` tidak perlu diubah (tidak pakai Auth).
3. Update `GenerateChatResponse::handle` — pass `$this->userId` ke `saveAssistantMessage`.
4. Update test yang memanggil `saveAssistantMessage` secara langsung.

## Rencana Test

```
php artisan test --filter ChatUiTest
php artisan test --filter ChatOrchestrationServiceTest
```

Test baru/update:
- `test_save_assistant_message_returns_null_for_conversation_not_owned_by_caller` — update untuk pass userId eksplisit.
- `test_generate_chat_response_persists_assistant_message_to_origin_conversation` — verifikasi dengan userId eksplisit.
- Tambah: `test_save_assistant_message_uses_explicit_user_id_not_auth_facade`.

## Kriteria Selesai

- [ ] `saveAssistantMessage` tidak bergantung `Auth::id()` lagi
- [ ] `conversationExists` menerima `userId` eksplisit
- [ ] `GenerateChatResponse` pass `$this->userId` ke service
- [ ] Semua test pass tanpa regresi
- [ ] PR dibuat dan siap review
