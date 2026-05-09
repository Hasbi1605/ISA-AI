# Issue: Follow-up UX dan Revisi Memo Workspace

## Latar Belakang
Workspace memo masih menyisakan beberapa gesekan setelah penyederhanaan konfigurasi:
- Sidebar riwayat tetap menampilkan badge "Memo Internal" padahal jenis dokumen sudah disembunyikan dari alur utama.
- Revisi via chat terlihat berhasil, tetapi hasil dokumen tidak selalu mengikuti instruksi user.
- Tombol download DOCX/PDF memicu loader layar penuh seperti navigasi halaman.
- Tombol Generate Memo belum memberi animasi loading yang jelas pada tombolnya.

## Dugaan Akar Masalah
- Badge sidebar masih mengambil `type_label` dari model memo.
- `sendMemoChat()` mengirim instruksi revisi sebagai `context`, tetapi konfigurasi lama masih punya `content`, sehingga service Python memilih isi lama dan mengabaikan instruksi revisi.
- Revisi yang menyentuh metadata/struktur dokumen, seperti tembusan, harus memperbarui konfigurasi sebelum generate ulang.
- Link download biasa memicu event `beforeunload`, sehingga global page loader tampil saat browser memulai download.

## Tujuan
- Hapus label jenis memo dari riwayat.
- Pastikan instruksi revisi dikirim sebagai instruksi eksplisit ke generator.
- Terapkan revisi tembusan sederhana ke konfigurasi sebelum generate.
- Ganti aksi download header menjadi fetch + blob agar hanya tombol yang loading.
- Tambahkan spinner pada tombol Generate Memo.

## Scope
- `laravel/app/Livewire/Memos/MemoWorkspace.php`
- `laravel/app/Services/Memo/MemoGenerationService.php`
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-history-sidebar.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-preview-panel.blade.php`
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`
- `python-ai/app/services/memo_generation.py`
- `python-ai/tests/test_memo_generation.py`

## Risiko
- Parser revisi tembusan hanya menangani instruksi langsung seperti "tambahkan tembusan nomor 4, ...".
- Fetch download perlu tetap memakai session auth dan membaca filename dari header `Content-Disposition`.
- Revisi tetap menghasilkan memo baru sebagai versi revisi, sesuai pola existing.

## Rencana Verifikasi
- `cd laravel && php artisan test tests/Feature/Memos/MemoWorkspaceTest.php`
- `cd laravel && ./vendor/bin/pint --test ...`
- `cd laravel && npm run build`
- `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py`
