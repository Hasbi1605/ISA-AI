# Issue #194 — Tuning Polling, Queue, dan Server Runtime Setelah Streaming Aktif

## Latar Belakang

Setelah streaming live aktif (#189), `wire:poll.3s="refreshPendingChatState"` tidak lagi menjadi jalur utama rendering jawaban chat. Polling sekarang hanya berfungsi sebagai safety net/fallback jika stream gagal di tengah jalan. Interval 3 detik terlalu agresif untuk fallback — setiap user yang sedang menunggu jawaban akan mengirim request Livewire setiap 3 detik.

## Analisis Polling Saat Ini

### 1. `chat-index.blade.php` — `wire:poll.3s="refreshPendingChatState"`
- **Kondisi aktif:** Hanya saat `$pendingConversationIds` tidak kosong
- **Fungsi:** Cek apakah job background sudah selesai dan load pesan baru
- **Setelah streaming:** Streaming sudah mengirim `done` event dan trigger `refreshPendingChatState()` langsung. Polling 3s hanya fallback.
- **Tuning:** 3s → 15s (safety net, bukan jalur utama)

### 2. `chat-right-sidebar.blade.php` — `wire:poll.3s/20s="loadAvailableDocuments"`
- **Kondisi aktif:** 3s saat ada dokumen processing, 20s saat idle
- **Fungsi:** Refresh status dokumen yang sedang diproses
- **Evaluasi:** 3s saat processing sudah cukup responsif. Ubah ke 5s untuk mengurangi beban sedikit tanpa mengorbankan UX.
- **Tuning:** 3s → 5s saat processing, 20s tetap saat idle

### 3. `document-viewer.blade.php` — `wire:poll.3s` (preview loading)
- **Kondisi aktif:** Saat preview dokumen sedang disiapkan
- **Fungsi:** Cek apakah preview sudah siap
- **Evaluasi:** Preview generation bisa memakan 5-15 detik. Poll 3s terlalu agresif.
- **Tuning:** 3s → 5s saat preview loading

## Scope Implementasi

### File Diubah
- `laravel/resources/views/livewire/chat/chat-index.blade.php` — poll 3s → 15s
- `laravel/resources/views/livewire/chat/partials/chat-right-sidebar.blade.php` — poll 3s → 5s saat processing
- `laravel/resources/views/livewire/documents/document-viewer.blade.php` — poll 3s → 5s saat preview loading

### File Baru
- `issue/issue-194-polling-queue-tuning-2026-05-15.md` — issue plan ini

## Acceptance Criteria

- [ ] `wire:poll.3s="refreshPendingChatState"` berubah ke 15s
- [ ] Polling dokumen sidebar saat processing berubah dari 3s ke 5s
- [ ] Polling preview dokumen saat loading berubah dari 3s ke 5s
- [ ] Test Laravel tetap hijau
- [ ] `wire:poll.3s="refreshPendingChatState"` masih terlihat di test (diupdate ke 15s)

## Rollback

Ubah kembali interval di template blade:
- `wire:poll.15s` → `wire:poll.3s` di `chat-index.blade.php`
- `wire:poll.5s` → `wire:poll.3s` di `chat-right-sidebar.blade.php`
- `wire:poll.5s` → `wire:poll.3s` di `document-viewer.blade.php`

## Catatan Runtime

Issue ini juga menyebut evaluasi `php -S` vs PHP-FPM + Caddy/Nginx atau Octane. Ini adalah keputusan infrastruktur yang membutuhkan metrik production nyata dan rencana deploy terpisah. Scope PR ini hanya mencakup perubahan polling interval yang aman dan reversible.
