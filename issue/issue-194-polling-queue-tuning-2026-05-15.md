# Issue #194 — Tuning Polling Interval Dokumen dan Preview Setelah Streaming Aktif

## Latar Belakang

Setelah streaming live aktif (#189), beberapa polling interval di UI masih menggunakan 3s yang terlalu agresif untuk kasus di mana responsivitas tinggi tidak diperlukan. PR ini mengurangi interval polling untuk dokumen sidebar dan preview viewer dari 3s ke 5s.

`wire:poll.3s="refreshPendingChatState"` di `chat-index.blade.php` **tidak diubah** karena `GenerateChatResponse` tidak dispatch event apapun setelah assistant message dipersist — polling tetap satu-satunya jalur refresh untuk chat. Mengubahnya ke 15s akan memperburuk latency yang dirasakan user hingga 15 detik. Push/event-based refresh menjadi scope PR terpisah.

## Analisis Polling

### 1. `chat-index.blade.php` — `wire:poll.3s="refreshPendingChatState"`
- **Kondisi aktif:** Hanya saat `$pendingConversationIds` tidak kosong
- **Fungsi:** Satu-satunya jalur untuk mendeteksi assistant message yang sudah selesai dipersist
- **Keputusan final:** Tetap 3s — tidak diubah di PR ini
- **Catatan:** Push/event-based refresh (dispatch dari job setelah selesai) adalah scope PR terpisah

### 2. `chat-right-sidebar.blade.php` — `wire:poll.3s/20s="loadAvailableDocuments"`
- **Kondisi aktif:** 3s saat ada dokumen processing, 20s saat idle
- **Fungsi:** Refresh status dokumen yang sedang diproses
- **Tuning:** 3s → 5s saat processing, 20s tetap saat idle

### 3. `document-viewer.blade.php` — `wire:poll.3s` (preview loading)
- **Kondisi aktif:** Saat preview dokumen sedang disiapkan
- **Fungsi:** Cek apakah preview sudah siap
- **Evaluasi:** Preview generation bisa memakan 5-15 detik. Poll 3s terlalu agresif.
- **Tuning:** 3s → 5s saat preview loading

## Scope Implementasi

### File Diubah
- `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php` — poll 3s → 5s saat processing
- `laravel/resources/views/livewire/documents/document-viewer.blade.php` — poll 3s → 5s saat preview loading

### File Tidak Diubah
- `laravel/resources/views/livewire/chat/chat-index.blade.php` — chat polling tetap 3s

### File Baru
- `issue/issue-194-polling-queue-tuning-2026-05-15.md` — issue plan ini

## Acceptance Criteria

- [ ] `wire:poll.3s="refreshPendingChatState"` di `chat-index.blade.php` tetap 3s (tidak berubah)
- [ ] Polling dokumen sidebar saat processing berubah dari 3s ke 5s
- [ ] Polling preview dokumen saat loading berubah dari 3s ke 5s
- [ ] Test Laravel tetap hijau
- [ ] `ChatUiTest` assertion tetap mencari `wire:poll.3s="refreshPendingChatState"`

## Rollback

Ubah kembali interval di template blade:
- `wire:poll.5s` → `wire:poll.3s` di `chat-right-sidebar.blade.php`
- `wire:poll.5s` → `wire:poll.3s` di `document-viewer.blade.php`

## Catatan Runtime

Issue ini juga menyebut evaluasi `php -S` vs PHP-FPM + Caddy/Nginx atau Octane. Ini adalah keputusan infrastruktur yang membutuhkan metrik production nyata dan rencana deploy terpisah. Scope PR ini hanya mencakup perubahan polling interval yang aman dan reversible.
