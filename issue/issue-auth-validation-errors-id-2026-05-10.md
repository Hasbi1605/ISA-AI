# Konsistensi Pesan Validasi Auth dan Profile Bahasa Indonesia

## Latar Belakang

Halaman login, register, reset password, dan profile masih memiliki pesan error validasi yang belum konsisten. Sebagian pesan masih berasal dari default Laravel berbahasa Inggris, sebagian sudah hardcoded bahasa Indonesia, dan beberapa skenario penting seperti password salah, email tidak terdaftar, reset token tidak valid/kedaluwarsa, atau password lama salah perlu dipastikan tampil dalam bahasa Indonesia yang konsisten.

Eksplorasi awal menemukan `APP_LOCALE` mengarah ke `id`, tetapi folder `laravel/lang/id/` belum tersedia sehingga Laravel masih fallback ke pesan `en`.

## Tujuan

- Menyediakan lokalisasi bahasa Indonesia untuk pesan auth, password broker, dan validasi Laravel.
- Mengonsistenkan pesan error pada halaman login, register, reset password, dan profile.
- Menambahkan atau memperbarui test agar skenario error penting terverifikasi dalam bahasa Indonesia.

## Ruang Lingkup

- Login: input kosong/tidak valid, kredensial salah, akun belum terverifikasi, throttling jika relevan.
- Register: nama/email/password, email sudah terdaftar, konfirmasi password, aturan password default Laravel.
- Forgot/reset password: email tidak valid/tidak terdaftar, token tidak valid atau kedaluwarsa, password/konfirmasi password.
- Profile: update nama/email, update password, hapus akun dengan password, termasuk password lama salah.
- File bahasa Laravel untuk `auth`, `passwords`, dan `validation` dalam locale `id`.

## Di Luar Scope

- Perubahan desain UI besar pada halaman auth/profile.
- Perubahan alur bisnis OTP, verifikasi email, atau reset password di luar kebutuhan pesan error.
- Refactor besar struktur Livewire/Volt.
- Perubahan model database atau migration.

## Area / File Terkait

- `laravel/lang/id/auth.php`
- `laravel/lang/id/passwords.php`
- `laravel/lang/id/validation.php`
- `laravel/app/Livewire/Forms/LoginForm.php`
- `laravel/resources/views/livewire/pages/auth/login.blade.php`
- `laravel/resources/views/livewire/pages/auth/forgot-password.blade.php`
- `laravel/resources/views/livewire/pages/auth/reset-password.blade.php`
- `laravel/resources/views/livewire/profile/update-profile-information-form.blade.php`
- `laravel/resources/views/livewire/profile/update-password-form.blade.php`
- `laravel/resources/views/livewire/profile/delete-user-form.blade.php`
- `laravel/tests/Feature/Auth/*`
- `laravel/tests/Feature/ProfileTest.php`

## Risiko

- Mengubah pesan global `validation.php` dapat memengaruhi test atau tampilan error lain di aplikasi.
- Pesan auth yang terlalu spesifik dapat membocorkan informasi akun; untuk login gagal sebaiknya tetap memakai pesan umum untuk email/password salah.
- Livewire/Volt component validation perlu diuji lewat test agar pesan yang tampil sesuai.
- Reset password memakai password broker Laravel, sehingga key di `passwords.php` harus sesuai agar `__($status)` terjemah dengan benar.

## Langkah Implementasi

1. Buat folder `laravel/lang/id/` dan file `auth.php`, `passwords.php`, `validation.php` dengan terjemahan bahasa Indonesia yang konsisten.
2. Isi `validation.attributes` untuk field teknis auth/profile seperti `form.email`, `register_email`, `current_password`, `password_confirmation`, dan field lain yang muncul di UI.
3. Rapikan custom validation messages di komponen Livewire/Volt agar tidak kembali ke bahasa Inggris dan tidak menduplikasi pesan yang bisa ditangani oleh lang file.
4. Pastikan pesan login gagal dan password lama salah memakai key terjemahan yang konsisten.
5. Tambahkan/perbarui test feature untuk skenario error utama pada login, register, reset password, update profile, update password, dan delete profile.
6. Jalankan test Laravel relevan dan full Laravel test sebelum PR siap review.

## Rencana Test

- Jalankan test auth/profile relevan:
  - `php artisan test tests/Feature/Auth/AuthenticationTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/PasswordResetTest.php tests/Feature/Auth/PasswordUpdateTest.php tests/Feature/ProfileTest.php`
- Jalankan `php artisan test` untuk full Laravel verification.
- Setelah PR dibuat dan branch dideploy ke `https://ista-ai.app`, lakukan browser QA pada alur login/profile/reset password yang memungkinkan tanpa mengekspos password di output.

## Kriteria Selesai

- Pesan validasi pada halaman login, register, reset password, dan profile tampil dalam bahasa Indonesia yang konsisten.
- Locale `id` memiliki file bahasa yang cukup untuk auth/password/validation tanpa fallback Inggris untuk skenario terkait.
- Test baru atau test yang diperbarui memverifikasi pesan error penting.
- Validasi Laravel relevan dan full test Laravel lulus.
- PR dibuat, branch terdeploy ke `https://ista-ai.app`, review/QC tidak menemukan blocker, dan merge menunggu approval eksplisit dari user.
