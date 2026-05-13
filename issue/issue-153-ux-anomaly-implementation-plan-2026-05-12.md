# Implementasi Temuan UX Anomali Issue #153

## Latar Belakang

GitHub issue #153 mencatat audit UX anomali ISTA AI dengan 58 temuan terde-duplikasi di area Auth/Navigation, Chat, Memo, Documents, dan Google Drive. Temuan mencakup label yang tidak sesuai perilaku backend, flash message yang hilang, dead-end OTP, error internal yang tampil mentah ke user, fallback preview yang tidak tersedia, status dokumen chat yang stale/tidak jelas, aksesibilitas tombol/icon/modal, copy campuran ID/EN, dan recovery path yang belum jelas.

Permintaan terbaru user adalah mengerjakan semua temuan UX anomali pada issue #153 dan membuat PR setelah implementasi. Issue #153 adalah sumber kebenaran; file `issue/*.md` lama dianggap historis kecuali cocok eksplisit dengan scope ini.

## Tujuan

- Menyelesaikan temuan High H1-H10 dari issue #153 dengan perubahan minimal yang aman.
- Menyapu temuan Medium/Low M1-M34 dan L1-L14 yang terkait copy, aksesibilitas, loading/empty/error state, recovery CTA, dan konsistensi istilah.
- Menambahkan test untuk perilaku yang berisiko regresi, terutama error normalization, auth flash/OTP, status dokumen chat, dokumen error, memo feedback, dan route legacy memo.
- Membuat PR yang menutup issue #153 dengan bukti validasi, deploy preview, browser QA, review, QC, dan risiko residual.

## Ruang Lingkup

- Laravel UI/Livewire/Blade untuk Auth, Navigation/Profile, Chat, Memo, Documents, dan Google Drive Picker/OAuth.
- JavaScript/Alpine di `laravel/resources/js/chat-page.js` untuk sinkronisasi state chat document selector, pending response UX, dan interaksi UI terkait.
- Helper normalisasi error user-facing agar exception teknis tidak tampil mentah ke user.
- Test Laravel feature/unit yang relevan dengan perubahan perilaku.
- PR workflow lengkap: branch, commit/push, PR, deploy preview ke `https://ista-ai.app`, browser QA setelah deploy, review/QC/final review.

## Di Luar Scope

- Refactor besar auth untuk menyatukan seluruh flow verifikasi email profil dengan OTP registration jika ternyata standard Laravel signed verification link masih dipakai. Jika ditemukan mismatch OTP nyata, tambahkan recovery/input minimal agar user tidak dead-end; full unifikasi flow dapat menjadi follow-up.
- Redesign penuh mode Memo mobile. Temuan M33 ditangani dengan copy/CTA yang lebih jelas dan akses yang tidak dead-end; desain mobile khusus skala besar dapat menjadi follow-up.
- Perubahan schema database kecuali implementasi menemukan kebutuhan wajib yang tidak terduga.
- Perubahan Python AI, kecuali test akhir sebelum merge tetap mengikuti AGENTS.md.

## Area / File Terkait

- `laravel/app/Support/UserFacingError.php`: helper baru untuk pesan error aman/user-friendly.
- `laravel/app/Livewire/Forms/LoginForm.php` dan `resources/views/livewire/pages/auth/**`: login label, reset password flash, OTP modal/recovery, verify email copy, auth card.
- `laravel/resources/views/livewire/layout/navigation.blade.php`, `resources/views/profile.blade.php`, `resources/views/livewire/profile/update-profile-information-form.blade.php`, `resources/views/livewire/dashboard-nav-profile.blade.php`: ARIA/navigation/profile feedback/guest CTA.
- `laravel/app/Livewire/Chat/ChatIndex.php`, `laravel/app/Livewire/Chat/GoogleDrivePicker.php`, `laravel/app/Services/Chat/ChatDocumentStateService.php`, `laravel/app/Services/ChatOrchestrationService.php`: status dokumen, pending/error/retry/reprocess, flash dan recovery.
- `laravel/resources/views/livewire/chat/partials/*.blade.php`, `laravel/resources/views/livewire/chat/google-drive-picker.blade.php`, `laravel/resources/js/chat-page.js`: copy, ARIA, loading/empty/error states, action bar stale, pending response UX, confirmation/recovery.
- `laravel/app/Livewire/Memos/MemoWorkspace.php`, `laravel/resources/views/livewire/memos/**/*.blade.php`: raw error, feedback memo, OnlyOffice fallback, history delete, loading state, mobile/focus/copy.
- `laravel/app/Livewire/Documents/DocumentViewer.php`, `laravel/resources/views/livewire/documents/document-viewer.blade.php`, `laravel/app/Http/Controllers/Documents/DocumentPreviewController.php`: raw error dan fallback PDF preview.
- `laravel/app/Http/Controllers/CloudStorage/GoogleDriveOAuthController.php`: OAuth failure copy/recovery.
- `laravel/routes/web.php`: legacy memo redirect yang preserve target memo.
- `laravel/tests/**`: unit/feature tests untuk scope di atas.

## Risiko

- **Livewire + Alpine stale state:** H9 berisiko karena `readyDocumentIds` diinisialisasi sebagai snapshot Alpine. Fix harus diuji manual/browser setelah deploy: upload dokumen, tunggu ready, pilih tanpa refresh, action bar harus muncul.
- **Status dokumen vs preview status:** `documents.status` menentukan konteks AI, sedangkan `preview_status` menentukan preview. Copy harus tidak overpromise bahwa dokumen bisa dipakai AI jika status belum `ready`.
- **Error normalization:** helper harus menyembunyikan detail teknis tanpa menghapus pesan validasi user-facing yang memang berguna.
- **Auth/verification:** H7 perlu inspeksi implementasi aktual `User::sendEmailVerificationNotification()`. Jika flow menggunakan OTP custom, perlu UI input/recovery; jika link standar, cukup perjelas copy agar tidak dead-end.
- **Destructive confirmation:** mengganti `wire:confirm` dengan modal custom harus menjaga keyboard access dan tidak mengganggu aksi Livewire.
- **Google Drive/OAuth:** recovery CTA jangan mengekspos setup/admin details ke user biasa.

## Langkah Implementasi

1. Delegasikan branch setup/konfirmasi branch ke `parallel-git-publisher-deep` tanpa menghapus perubahan pre-existing user.
2. Implementasikan fondasi error normalization dan test unitnya.
3. Implementasikan shared UX pattern bila dibutuhkan, seperti confirm dialog dan page loader accessibility.
4. Implementasikan Chat document lifecycle fixes: pending/processing warning, error state/reprocess, action bar sync tanpa refresh, Google Drive picker recovery.
5. Implementasikan Memo fixes: error normalization, missing memo/version feedback, delete feedback, OnlyOffice fallback, copy/ARIA/loading/mobile polish.
6. Implementasikan Documents viewer fixes: PDF fallback dan error upload Drive friendly.
7. Implementasikan Auth/Profile/Navigation fixes: login label, reset flash key, OTP recovery/accessibility, verify email copy/recovery, navigation/profile ARIA/feedback, guest CTA.
8. Implementasikan Chat UI/copy/accessibility sweep: icon-only labels, submit loading, sidebar copy, hover-only focus state, pending response stale indicator, terminology consistency.
9. Tambah/update tests untuk perilaku penting yang berubah.
10. Jalankan validasi lokal relevan dan `git diff --check`.
11. Delegasikan commit/push dan PR creation ke `parallel-git-publisher-deep` dengan PR body sesuai standar.
12. Delegasikan deploy preview ke `parallel-production-deployer-deep`.
13. Setelah deploy, jalankan browser QA deployed dengan `parallel-qa-browser-deep` untuk auth smoke, chat document sidebar, memo, documents preview, dan accessibility-relevant flows.
14. Jalankan deep PR review/QC/final reviewer. Post satu komentar status PR sebelum memperbaiki blocker.
15. Jika ada blocker, delegasikan fix ke `parallel-pr-fixer-premium`, validasi, push, redeploy, rerun QA/review/QC/final, dan post follow-up.
16. Saat clean, jalankan full final verification sesuai AGENTS.md sebelum meminta approval merge user.

## Rencana Test

- `cd laravel && php artisan test --filter=UserFacingError`
- `cd laravel && php artisan test tests/Feature/Auth`
- `cd laravel && php artisan test tests/Feature/Chat tests/Unit/Services/Chat`
- `cd laravel && php artisan test tests/Feature/Memos`
- `cd laravel && php artisan test tests/Feature/Documents`
- `cd laravel && npm run build`
- `git diff --check`
- Setelah PR deploy: browser QA di `https://ista-ai.app` untuk flow Chat document upload/ready/error, Google Drive picker recovery, Memo preview fallback, Documents PDF fallback, Auth/OTP/reset smoke, navigation/profile accessibility smoke.
- Verifikasi akhir penuh sebelum merge: `cd laravel && php artisan test` dan `cd python-ai && source venv/bin/activate && pytest`.

## Kriteria Selesai

- Semua temuan issue #153 ditangani atau diberi justifikasi minimal-fix/deferred yang eksplisit di PR body.
- Error teknis tidak lagi tampil mentah pada area yang disebut issue #153.
- Dokumen pending/error di Chat punya state yang jelas dan tidak diam-diam dianggap siap konteks AI.
- Action bar Chat document sidebar muncul tanpa refresh setelah dokumen baru menjadi ready.
- Auth, Memo, Documents, Google Drive, Navigation/Profile memiliki copy/ARIA/loading/empty/error/recovery state yang lebih konsisten sesuai issue #153.
- Test relevan ditambahkan/diupdate dan validation command lulus.
- PR dibuat, branch dipush, preview dideploy ke `https://ista-ai.app`, QA/review/QC/final-reviewer bersih.
- Tidak auto-merge; berhenti dan meminta approval merge eksplisit dari user setelah status `APPROVAL_READY`.
