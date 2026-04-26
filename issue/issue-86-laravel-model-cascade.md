# Implementation Plan - Laravel AI Multi-provider Model Cascade

Tujuan: Memindahkan logika model cascade dari Python ke Laravel-only untuk mencapai AI Parity dan menghilangkan ketergantungan pada `OPENAI_API_KEY`.

## User Review Required

> [!IMPORTANT]
> Urutan model cascade akan mengikuti pola Python:
> 1. GPT-4.1 (Primary) via GitHub Models + GITHUB_TOKEN
> 2. GPT-4.1 (Backup) via GitHub Models + GITHUB_TOKEN_2
> 3. GPT-4o (Primary) via GitHub Models + GITHUB_TOKEN
> 4. GPT-4o (Backup) via GitHub Models + GITHUB_TOKEN_2
> 5. Llama 3.3 70B via Groq + GROQ_API_KEY
> 6. Gemini 3 Flash via Google AI Studio + GEMINI_API_KEY

## Proposed Changes

### 1. Configuration (`laravel/config/ai.php`)
- Tambahkan section `cascade` yang berisi daftar node model.
- Setiap node berisi: `label`, `provider`, `model`, `api_key`, `base_url` (opsional).

### 2. AIService / ChatService (`laravel/app/Services/Chat/LaravelChatService.php`)
- Implementasikan logic `cascade` di dalam method `chat` dan `chatWithDocuments`.
- Gunakan loop untuk mencoba setiap node model.
- Tangani error 413 (Context Too Large) dan 429 (Rate Limit) untuk memicu fallback ke node berikutnya.
- Injeksi marker `[MODEL:...]` di awal stream.
- Pastikan metadata `[SOURCES:...]` tetap terkirim di akhir stream.

### 3. Error Handling
- Buat helper atau logic untuk mendeteksi tipe error dari Laravel AI SDK (413, 429, timeout).

### 4. Verification
- Implementasikan test case di `tests/Feature/Parity/AIParityMatrixTest.php`.
- Test: success case, rate-limit fallback, context-too-large fallback, dan all-failed case.

## Detailed Steps

### Step 1: Update Configuration
Update `laravel/config/ai.php` untuk mendukung cascade nodes.

### Step 2: Implement Cascade Logic in `LaravelChatService`
Modify `chat` and `chatWithDocuments` to iterate through cascade nodes.

### Step 3: Handle Streaming Marker
Ensure `[MODEL:Label]` is yielded at the beginning.

### Step 4: Verification
Enable and implement the tests in `AIParityMatrixTest.php`.

## Risks & Considerations
- **Performance**: Cascade bisa menambah latency jika model awal gagal.
- **SDK Compatibility**: Memastikan `Laravel AI SDK` bisa menerima konfigurasi provider secara dinamis (terutama base_url dan API key).
