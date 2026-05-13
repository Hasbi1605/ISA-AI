# UX Anomaly Audit — ISTA AI

**Tanggal:** 2026-05-12
**Branch:** `fix/ux-anomaly-audit-2026-05-12`
**Status:** Open
**Tipe:** UX Improvement

---

## Ringkasan

Audit UX mendalam berbasis inspeksi kode statis menemukan **48 anomali** di 4 area utama: Auth/Navigation, Chat, Memo, dan Documents/Google Drive. Tidak ada Blocker, tetapi ada 6 temuan High yang berdampak langsung ke pengalaman user inti.

---

## Temuan High (6)

### H1. Login label mismatch — "Email atau username" tapi backend hanya terima email

- **File:** `laravel/resources/views/livewire/pages/auth/partials/login-form.blade.php:10`
- **Backend:** `laravel/app/Livewire/Forms/LoginForm.php:15` — rule `required|string|email`
- **Impact:** User coba username, gagal tanpa penjelasan yang jelas
- **Fix:** Ubah placeholder/label jadi "Email" saja, atau tambah dukungan username di backend

### H2. Reset password success tidak terlihat setelah redirect

- **File:** `laravel/resources/views/livewire/pages/auth/reset-password.blade.php:53-55` — flash key `message`
- **File:** `laravel/resources/views/livewire/pages/auth/partials/auth-card.blade.php:28` — baca `session('status')`
- **Impact:** User tidak tahu reset berhasil karena key flash tidak cocok
- **Fix:** Samakan flash key ke `status`

### H3. OTP modal dead-end saat sesi pendaftaran expired

- **File:** `laravel/resources/views/livewire/pages/auth/login.blade.php:170-173`
- **Impact:** User terjebak di modal tanpa CTA recovery yang jelas
- **Fix:** Tambah state khusus untuk token expired dengan CTA "Daftar ulang" atau "Kembali ke login"

### H4. Error internal `$e->getMessage()` ditampilkan mentah ke user

- **File:** `laravel/app/Livewire/Memos/MemoWorkspace.php:229-230, 277-278, 319-320`
- **File:** `laravel/app/Livewire/Documents/DocumentViewer.php:104-106`
- **File:** `laravel/app/Livewire/Chat/GoogleDrivePicker.php:138-140`
- **Impact:** Pesan teknis membingungkan, bocorkan detail implementasi
- **Fix:** Buat helper normalisasi error yang translate exception ke pesan user-friendly

### H5. OnlyOffice gagal load tanpa fallback/error state

- **File:** `laravel/resources/views/livewire/memos/partials/memo-preview-panel.blade.php:84-109`
- **File:** `laravel/resources/views/livewire/memos/memo-canvas.blade.php:62-79`
- **Impact:** Panel dokumen blank total — dead-end di fitur inti memo
- **Fix:** Tambah error handler saat `api.js` gagal load atau `DocEditor` throw error

### H6. PDF preview iframe kosong saat file 404

- **File:** `laravel/resources/views/livewire/documents/document-viewer.blade.php:184-187`
- **File:** `laravel/app/Http/Controllers/Documents/DocumentPreviewController.php:33-52`
- **Impact:** User lihat area blank tanpa penjelasan atau retry
- **Fix:** Tambah fallback state di iframe (detect 404, tampilkan pesan + retry button)

---

## Temuan Medium (26)

### Auth & Navigation

| # | Anomali | File | Fix |
|---|---------|------|-----|
| M1 | OTP resend tidak jelaskan kode lama invalid | `login.blade.php:131-140` | Tambah copy penjelasan |
| M2 | OTP modal tanpa focus trap dan ARIA dialog | `otp-verification-modal.blade.php:1-100` | Tambah `role="dialog"`, focus trap |
| M3 | Verify email page copy full English | `verify-email.blade.php:39-56` | Terjemahkan ke ID |
| M4 | Mobile hamburger tanpa `aria-label`/`aria-expanded` | `navigation.blade.php:70-76` | Tambah ARIA attributes |
| M5 | Profile dropdown tanpa label aksesibel | `navigation.blade.php:43-51` | Tambah `aria-label="Menu akun"` |
| M6 | Profile tabs tidak semantik | `profile.blade.php:73-120` | Implementasi `role="tablist"` |
| M7 | Profile save feedback terlalu kecil | `update-profile-information-form.blade.php:125-135` | Toast atau scroll ke pesan |

### Chat

| # | Anomali | File | Fix |
|---|---------|------|-----|
| M8 | Icon-only buttons header tanpa `aria-label` | `chat-index.blade.php:46-49, 63-75` | Tambah aria-label |
| M9 | Tidak ada loading state saat pindah percakapan | `chat-index.blade.php:8-9` | Tambah skeleton/spinner |
| M10 | Composer tidak beri feedback saat kirim | `chat-composer.blade.php:117-122` | Loading indicator di tombol |
| M11 | Copy campuran ID/EN di sidebar percakapan | `chat-left-sidebar.blade.php:17,31,42,76` | Konsistenkan ke ID |
| M12 | Delete chat hover-only, sulit di keyboard/mobile | `chat-left-sidebar.blade.php:56-63` | Tambah `focus:opacity-100` |
| M13 | Copy campuran di sidebar dokumen kanan | `chat-right-sidebar.blade.php:56-83` | Konsistenkan ke ID |
| M14 | Tab toggle tanpa `aria-label` di mobile | `chat-memo-tab-toggle.blade.php:3-30` | Tambah label |
| M15 | Pending AI response stale (TTL 10 menit) | `chat-page.js:237-261` | Tambah timeout/retry indicator |

### Memo

| # | Anomali | File | Fix |
|---|---------|------|-----|
| M16 | Silent failure saat memo/versi tidak ditemukan | `MemoWorkspace.php:77-79, 385-387` | Tambah flash/toast error |
| M17 | Tidak ada loading state saat switch memo/versi | `MemoWorkspace.php:68-109` | Tambah loading indicator |
| M18 | Delete memo hover-only, keyboard invisible | `memo-history-sidebar.blade.php:78-86` | Tambah `focus:opacity-100` |
| M19 | Tombol kirim revisi tanpa `aria-label` | `memo-chat.blade.php:341-348` | Tambah label |
| M20 | Form konfigurasi hilang setelah generate, affordance kecil | `memo-chat.blade.php:91-207` | Perbesar/perjelas tombol edit |
| M21 | Loading phase terlalu generik | `memo-chat.blade.php:265-294` | Tampilkan nama tahap |
| M22 | Preview "Dokumen belum tersedia" terlalu generik | `memo-preview-panel.blade.php:79-142` | Bedakan per kondisi |
| M23 | Mobile panel tanpa focus management | `memo-workspace.blade.php:17-23` | Tambah focus trap/restore |
| M24 | Hapus memo tidak beri feedback sukses | `MemoWorkspace.php:379-406` | Tambah toast/notice |
| M25 | State loading tidak konsisten antar panel | `MemoWorkspace.php:125-147` | Sinkronkan disabled state |

### Documents & Google Drive

| # | Anomali | File | Fix |
|---|---------|------|-----|
| M26 | Success import Drive hilang karena modal tutup | `GoogleDrivePicker.php:131-137` | Tampilkan toast di luar modal |
| M27 | Empty state dan error state picker bercampur | `GoogleDrivePicker.php:203-222` | Bedakan UI per kondisi |
| M28 | Picker tidak beri recovery saat load gagal | `GoogleDrivePicker.php:194-223` | Tambah retry button |
| M29 | OAuth failure tanpa recovery CTA di picker | `GoogleDriveOAuthController.php:42-45` | Tambah reconnect CTA |
| M30 | Error upload/picker mentah | `DocumentViewer.php:104-106` | Normalisasi pesan |

---

## Temuan Low (12)

| # | Anomali | File |
|---|---------|------|
| L1 | Auth card `cursor-pointer` pada elemen non-interaktif | `auth-card.blade.php:5,17` |
| L2 | Capitalization tidak konsisten di auth forms | `login-form.blade.php`, `register-form.blade.php` |
| L3 | Back link ke beranda tidak konsisten antar auth states | `reset-password.blade.php:158-164` |
| L4 | Page loader bisa terasa "freeze" di koneksi lambat | `page-loader.blade.php:17-30` |
| L5 | Empty state dokumen sidebar masih English | `chat-right-sidebar.blade.php:153-155` |
| L6 | Drag-and-drop error feedback kurang discoverable | `chat-page.js:144-173` |
| L7 | Sidebar tidak punya error state saat fetch gagal | `chat-left-sidebar.blade.php:35-106` |
| L8 | History sidebar empty state tanpa CTA | `memo-history-sidebar.blade.php:48-51` |
| L9 | Konfirmasi hapus masih English "Delete this memo?" | `memo-history-sidebar.blade.php:80` |
| L10 | Label navigasi campur "New Memo" / "Buat memo" | `memo-index.blade.php:8-10` |
| L11 | Composer revisi tanpa hint keyboard | `memo-chat.blade.php:329-338` |
| L12 | Terminologi tidak konsisten: document/file/memo/dokumen rujukan | multiple files |

---

## Pola Sistemik

1. **Bahasa campuran ID/EN** — perlu satu pass konsistensi bahasa di seluruh UI
2. **Error mentah ke user** — perlu helper normalisasi error terpusat
3. **Hover-only controls** — perlu pattern `group-hover:opacity-100 focus:opacity-100`
4. **Icon-only buttons tanpa label** — perlu audit semua `<button>` yang hanya ikon
5. **Satu state generik untuk loading/empty/error** — perlu diferensiasi per kondisi

---

## Rencana Implementasi

### Fase 1 — High Priority (H1-H6)
- Fix login label mismatch
- Fix reset password flash key
- Tambah OTP expired recovery state
- Buat error normalization helper
- Tambah OnlyOffice fallback state
- Tambah PDF preview fallback

### Fase 2 — Accessibility Sweep (M2, M4, M5, M8, M14, M19)
- Tambah ARIA attributes ke semua icon-only buttons
- Tambah focus trap ke OTP modal
- Tambah semantic tabs ke profile

### Fase 3 — Bahasa & Copy Consistency (M3, M11, M13, L2, L5, L9, L10, L12)
- Konsistenkan seluruh UI ke Bahasa Indonesia
- Standarkan terminologi

### Fase 4 — State & Feedback (M9, M10, M15-M17, M21-M28)
- Bedakan loading/empty/error states
- Tambah feedback untuk aksi sukses/gagal
- Perbaiki recovery paths

---

## Catatan

- Audit dilakukan secara **code-only** (static pass), tanpa runtime/browser verification.
- Severity berdasarkan dampak ke user, bukan kompleksitas implementasi.
- Tidak ada Blocker yang ditemukan — aplikasi fungsional, tetapi banyak friction yang bisa dikurangi.
