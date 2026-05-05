# Issue 119 - Google Drive Central OAuth

## Latar Belakang
Integrasi Google Drive saat ini memakai service account. Service account bisa membaca folder My Drive yang dibagikan, tetapi gagal upload karena service account tidak memiliki storage quota. Shared Drive tidak bisa dibuat dari akun yang sedang digunakan karena tombol Drive bersama nonaktif.

## Tujuan
Membuat jalur OAuth satu akun pusat Google Drive agar seluruh user ISTA AI bisa upload ke Drive akun yang sama tanpa login Google masing-masing.

## Ruang Lingkup
- Tambah konfigurasi OAuth client Google Drive.
- Tambah penyimpanan koneksi OAuth pusat secara terenkripsi di database.
- Tambah route connect/callback untuk admin melakukan OAuth sekali.
- Route OAuth ditempatkan di bawah namespace `/chat/google-drive/oauth/*` dan hanya redirect teknis, bukan halaman baru.
- Ubah GoogleDriveService agar memakai OAuth pusat saat tersedia.
- Ubah status upload agar aktif jika OAuth pusat sudah tersambung.
- Tambah test untuk status koneksi OAuth dan flow callback.

## Di Luar Scope
- OAuth per user.
- Domain-wide delegation Google Workspace.
- Membuat Shared Drive dari Google Admin.
- Deploy production langsung dari terminal agent.
- Halaman user-facing baru di luar `/chat`.

## Area / File Terkait
- `laravel/app/Services/CloudStorage/GoogleDriveService.php`
- `laravel/app/Http/Controllers`
- `laravel/config/services.php`
- `laravel/routes/web.php`
- `laravel/database/migrations`
- `laravel/app/Models`
- `laravel/tests/Feature/CloudStorage`

## Risiko
- Refresh token adalah secret dengan akses Drive, sehingga harus disimpan terenkripsi.
- Tanpa role admin di aplikasi, route setup perlu setup key agar user biasa tidak bisa mengganti koneksi pusat.
- Google hanya mengirim refresh token saat consent offline diberikan; callback harus menolak koneksi tanpa refresh token.

## Langkah Implementasi
1. Tambah migration dan model koneksi OAuth pusat.
2. Tambah service OAuth untuk membuat auth URL, memproses callback, dan refresh token.
3. Tambah controller dan route connect/callback di bawah `/chat/google-drive/oauth/*`.
4. Integrasikan OAuth pusat ke GoogleDriveService.
5. Update konfigurasi `.env.example`.
6. Tambah test feature untuk callback dan status upload.
7. Jalankan test Laravel relevan.

## Rencana Test
- Test callback menyimpan koneksi OAuth terenkripsi.
- Test callback menolak state invalid dan token tanpa refresh token.
- Test GoogleDriveService mengaktifkan upload jika koneksi OAuth pusat tersedia.
- Jalankan test Google Drive/CloudStorage dan chat/document upload terkait.

## Kriteria Selesai
- Koneksi OAuth pusat bisa dibuat satu kali.
- Semua user bisa upload via koneksi pusat setelah token tersedia.
- Service account tetap bisa dipakai untuk Shared Drive jika suatu saat dikonfigurasi.
- Test relevan lulus.
