# Queue OTP Email Saat Registrasi

## Latar Belakang
Alur registrasi saat ini menunggu pengiriman email OTP selesai sebelum modal verifikasi muncul. Karena pengiriman masih sinkron ke SMTP Gmail, request dapat terasa lama ketika koneksi SMTP lambat atau gagal.

## Tujuan
Membuat proses registrasi terasa cepat dengan memindahkan pengiriman email OTP ke proses queued, sehingga response Livewire tidak lagi menunggu SMTP.

## Ruang Lingkup
- Ubah `VerificationCodeMail` agar diproses lewat queue.
- Sesuaikan alur registrasi dan resend OTP agar tetap memakai mailable yang sama.
- Update test yang sebelumnya mengasumsikan email dikirim sinkron.

## Di Luar Scope
- Mengganti provider email.
- Mengubah desain UI registrasi.
- Merombak seluruh sistem verifikasi email atau OTP.

## Area / File Terkait
- `laravel/app/Mail/VerificationCodeMail.php`
- `laravel/app/Models/User.php`
- `laravel/resources/views/livewire/pages/auth/login.blade.php`
- `laravel/tests/Feature/Auth/RegistrationTest.php`
- `laravel/tests/Feature/Auth/EmailVerificationTest.php`

## Risiko
- Jika queue worker bermasalah, email OTP bisa tertunda meski request registrasi sudah selesai.
- Test yang mengandalkan `Mail::assertSent()` perlu diperbarui agar tidak false negative.
- Perlu memastikan pengiriman queued tetap kompatibel dengan Horizon/Redis yang sudah dipakai di production.

## Langkah Implementasi
1. Jadikan `VerificationCodeMail` queued mail.
2. Biarkan pemanggil existing tetap memakai `Mail::to(...)->send(...)` agar Laravel mengantre mail secara otomatis.
3. Perbarui test registrasi dan verifikasi email untuk mengecek mail masuk ke queue, bukan sent langsung.
4. Jalankan verifikasi Laravel terkait auth.

## Rencana Test
- Jalankan `php artisan test` di folder `laravel`.
- Pastikan test registrasi dan email verification masih lulus setelah ekspektasi diubah ke queued mail.

## Kriteria Selesai
- Registrasi tidak lagi menunggu SMTP Gmail secara sinkron.
- OTP email tetap terkirim lewat mekanisme queue.
- Test Laravel relevan lulus.
- Tidak ada regresi pada resend OTP dan verifikasi email.
