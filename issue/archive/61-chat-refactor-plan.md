# Issue #61: Refactor Orkestrasi dan UI Chat Laravel

## Tujuan
Memecah orkestrasi chat yang monolithic menjadi komponen yang lebih fokus untuk:
1. Ekstraksi logika backend chat ke service/action
2. Pemecahan view chat menjadi partial
3. Isolasi state Alpine yang lebih jelas

## Analisis Komponen Sekarang

### ChatIndex.php - Tanggung Jawab Berlebih
- `sendMessage()` - ~140 baris orkestrasi kompleks (stream, metadata, sanitization)
- `extractStreamMetadata()` - parsing stream
- `sanitizeAssistantOutput()` - text processing
- Manajemen conversation (load, create, delete)
- Manajemen document (upload, select, delete)
- State UI yang banyak

### chat-index.blade.php - x-data Monolitik
- 178 baris x-data dalam satu blok besar
- Menghandle: dark mode, mobile, sidebars, drag-drop, form submit, scroll, mutation observer
- view 834 baris untuk 3 area (left sidebar, main, right sidebar)

## Plan Implementasi

### Tahap 1: Backend Extraction

#### 1.1 Buat ChatOrchestrationService
Buat service baru untuk mengekstrak logika orkestrasi dari `sendMessage()`:
- Method: `sendMessageWithStream()` - handle full message flow
- Method: `createConversationIfNeeded()` - create conversation
- Method: `buildHistory()` - build message history for AI
- Method: `processStreamChunk()` - process stream metadata
- Method: `sanitizeAndFinalize()` - sanitize output + append sources

#### 1.2 Refactor ChatIndex.php
- Ganti `sendMessage()` untuk menggunakan service (thin proxy)
- Pindahkan `extractStreamMetadata()` ke service
- Pindahkan `sanitizeAssistantOutput()` ke service

### Tahap 2: View Breakdown

#### 2.1 Buat Partial Views
Buat folder `laravel/resources/views/livewire/chat/partials/`:
- `chat-left-sidebar.blade.php` - conversation history sidebar (lines 204-320)
- `chat-messages.blade.php` - messages list (lines 368-580)
- `chat-composer.blade.php` - input form (lines 582-696)
- `chat-right-sidebar.blade.php` - document selector (lines 699-818)

#### 2.2 Update chat-index.blade.php
- Ganti include dengan partials
- Pertahankan struktur yang ada agar behavior tetap sama

### Tahap 3: Alpine Isolation

#### 3.1 Pecah x-data
Gunakan strategi:
- x-data utama hanya untuk root state (darkMode, isMobile, sidebars)
- Inline x-data untuk logic terkait (streaming, typewriter)
- Pertahankan backward compatibility untuk event handling

#### 3.2 Extract Functions
- Buat helper functions sederhana di x-data untuk:
  - `scrollToBottom()`
  - `handleDragAndDrop()`
  - `submitPrompt()`
  - Tetap inline atau minimasi scope

### Tahap 4: Testing

#### 4.1 Test Coverage
- Jalankan test yang ada: `php artisan test`
- Pastikan tidak ada regresi pada DocumentUploadTest dan ChatStreamMetadataTest

## File yang Diedit

### Backend
- `laravel/app/Livewire/Chat/ChatIndex.php` - refactor method calls
- `laravel/app/Services/ChatOrchestrationService.php` - NEW

### Views
- `laravel/resources/views/livewire/chat/chat-index.blade.php` - refactor include partials
- `laravel/resources/views/livewire/chat/partials/chat-left-sidebar.blade.php` - NEW
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php` - NEW
- `laravel/resources/views/livewire/chat/partials/chat-composer.blade.php` - NEW
- `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php` - NEW

## Risiko dan Mitigasi

### Risiko
1. Breaking kontrak method yang dipakai view lain
2. Test failure jika perilaku berubah
3. JS error jika Alpine state berubah signifikan

### Mitigasi
1. Pertahankan public method names dan signatures di ChatIndex
2. Jalankan test setelah setiap tahap
3. Test Alpine behavior secara manual di browser

## Verifikasi

- [x] Laravel test penuh berhasil (55 passed)
- [ ] Chat page berfungsi normal di browser
- [ ] Streaming masih berjalan
- [ ] Document upload/select masih berjalan
- [ ] Responsive behavior tidak berubah

## Status

### Selesai: Backend Extraction
- ChatOrchestrationService.php berhasil dibuat
- ChatIndex.php sekarang menggunakan service untuk sendMessage()
- Private methods delegate ke service untuk backward compatibility
- Semua 55 test Laravel PASSED

### Tidak Selesai: View Breakdown
-尝试 memecah Blade menjadi partials GAGAL karena:
  - Kompleksitas Alpine x-data monolitik yang tidak bisa dipisahkan dengan sederhana
  - Kondisi @if/@else tanpa @endif menyebabkan parse error
  - View harus dipertahankan utuh agar behavior tidak berubah
- Perlu pendekatan berbeda untuk Alpine isolation

###_ACTION_ITEM_
Prioritas refactor view Chat harus menggunakan pendekatan berbeda:
- Jangan pecah Blade dengan @include (menyebabkan masalah x-data scope)
- Fokus pada refactor Alpine state secara in-place tanpa拆份 file