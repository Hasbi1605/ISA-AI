# Bug #158: Indikator loading chat hilang sendiri setelah 30 menit tanpa feedback (dead job)

## Latar Belakang

Saat worker queue crash atau Python AI hang di tengah job, `GenerateChatResponse` tidak pernah selesai maupun masuk `failed()`. UI menampilkan loading selama 30 menit (cutoff heuristic di `conversationHasPendingResponse`), lalu loading hilang tanpa pesan error atau balasan. User tidak tahu harus apa.

## Tujuan

Tambah scheduled command `chat:resolve-stale-responses` yang berjalan tiap menit, mendeteksi conversation dengan pesan user terakhir yang sudah lebih dari N menit tanpa balasan assistant, lalu menulis `Message(is_error=true)` sebagai sinyal ke user bahwa respon gagal dan perlu retry.

## Ruang Lingkup

- Buat `App\Console\Commands\ResolveStaleChats` command.
- Daftarkan ke scheduler di `routes/console.php` tiap 1 menit.
- Gunakan `ChatOrchestrationService::saveErrorMessage` (sudah ada dari fix #157).
- Tambah test `Tests\Feature\Console\ResolveStaleChatsTest`.
- Timeout default: 10 menit (configurable via `--minutes` option).

## Di Luar Scope

- Perubahan UI/frontend untuk tombol retry (scope UX terpisah).
- Perubahan pada `GenerateChatResponse` job itu sendiri.
- Opsi B (Horizon/supervisor retry) dan Opsi C (frontend cancel button).
- Bug lain dari audit.

## Area / File Terkait

- `laravel/app/Console/Commands/ResolveStaleChats.php` — **file baru**
- `laravel/routes/console.php` — daftarkan ke scheduler
- `laravel/tests/Feature/Console/ResolveStaleChatsTest.php` — **file baru**

## Risiko

- Command harus idempotent: jika conversation sudah punya assistant message, skip.
- Harus scoped per user (tidak menulis ke conversation orang lain).
- Jangan menulis error message jika conversation sudah dihapus.
- Timeout N menit harus lebih besar dari `GenerateChatResponse::$timeout` (180 detik = 3 menit) untuk menghindari false positive. Default 10 menit aman.

## Langkah Implementasi

1. Buat `ResolveStaleChats` command:
   - Query: conversation yang latest message role=user, created_at < now()-N menit, tidak ada message role=assistant setelahnya.
   - Untuk setiap conversation stale: tulis `Message(is_error=true)` via `saveErrorMessage`.
   - Touch conversation `updated_at` agar polling Livewire mendeteksi perubahan.
   - Log jumlah conversation yang di-resolve.

2. Daftarkan ke scheduler: `->everyMinute()->withoutOverlapping()`.

3. Tambah test:
   - Conversation stale → error message ditulis.
   - Conversation dengan assistant message → tidak disentuh.
   - Conversation baru (< N menit) → tidak disentuh.
   - Command idempotent: run dua kali tidak duplikasi error message.

## Rencana Test

```
php artisan test --filter ResolveStaleChatsTest
```

## Kriteria Selesai

- [ ] Command `chat:resolve-stale-responses` berjalan dan menulis error message untuk conversation stale
- [ ] Scheduler mendaftarkan command tiap 1 menit
- [ ] Command idempotent
- [ ] Test pass tanpa regresi
- [ ] PR dibuat dan siap review
