# Bug #157: Error dari AI Service disimpan sebagai jawaban assistant normal (is_error tidak ditandai)

## Latar Belakang

Saat Python AI service down/timeout, `AIService::sendChat` meng-yield string error seperti `"❌ Kesalahan sistem saat menghubungi otak AI. Silakan coba lagi nanti."` sebagai chunk biasa. `GenerateChatResponse::handle` mengakumulasi chunk ini ke `$fullResponse` dan menyimpannya via `saveAssistantMessage` tanpa flag `is_error`.

Di database, `Message.is_error = 0` padahal isinya adalah pesan error. UI memperlakukannya sebagai balasan AI sukses — tidak ada bubble merah, tidak ada tombol retry.

Jalur `failed()` di job hanya jalan kalau job benar-benar throw exception. Karena generator return normal (yield string error), job tidak masuk `failed()`.

## Tujuan

Deteksi apakah respons AI adalah fallback error, dan jika ya, simpan `Message` dengan `is_error = true`. Ini memungkinkan UI menampilkan bubble error yang jelas dan tombol retry.

## Ruang Lingkup

- Tambah sentinel/konstanta di `AIService` untuk menandai chunk error.
- Update `GenerateChatResponse::handle` untuk mendeteksi sentinel dan menyimpan `Message(is_error=true)`.
- Tambah `saveErrorMessage` di `ChatOrchestrationService`.
- Tambah cast `boolean` untuk `Message.is_error` (sekalian fix bug #163).
- Tambah/update test.

## Di Luar Scope

- Perubahan UI/frontend untuk menampilkan bubble error (itu scope UX terpisah).
- Perubahan Python service.
- Bug lain dari audit.
- Implementasi tombol retry di frontend.

## Area / File Terkait

- `laravel/app/Services/AIService.php` — tambah konstanta sentinel error.
- `laravel/app/Jobs/GenerateChatResponse.php` — deteksi sentinel, simpan dengan `is_error=true`.
- `laravel/app/Services/ChatOrchestrationService.php` — tambah `saveErrorMessage`.
- `laravel/app/Models/Message.php` — tambah cast `is_error => boolean`.
- `laravel/tests/Feature/Chat/ChatUiTest.php` — tambah test.
- `laravel/tests/Unit/AIServiceTest.php` — tambah test sentinel.

## Risiko

- Sentinel string harus unik dan tidak mungkin muncul di respons AI normal.
- Jika sentinel tidak terdeteksi (misal chunk terpotong di boundary), error tetap tersimpan sebagai pesan normal — acceptable, lebih baik dari crash.
- Perubahan `Message.is_error` cast tidak breaking karena nilai DB tetap 0/1.

## Langkah Implementasi

1. Tambah `AIService::ERROR_SENTINEL = '[ISTA_AI_ERROR]'`
2. Prefix semua error yield dengan sentinel
3. `GenerateChatResponse::handle` deteksi sentinel → `saveErrorMessage(is_error=true)`
4. Tambah `ChatOrchestrationService::saveErrorMessage()`
5. Tambah cast `'is_error' => 'boolean'` di `Message`
6. Tambah test

## Rencana Test

```
php artisan test --filter "AIServiceTest|ChatUiTest"
```

Test baru:
- `test_generate_chat_response_saves_error_message_when_ai_service_returns_sentinel`
- `test_ai_service_yields_sentinel_prefix_on_network_error`
- `test_ai_service_error_sentinel_constant_is_unique`
- `test_message_is_error_is_cast_to_boolean`

## Kriteria Selesai

- [x] Error dari AIService tersimpan dengan `is_error=true`
- [x] `Message.is_error` di-cast ke boolean
- [x] Semua test pass tanpa regresi
- [x] PR dibuat dan siap review
