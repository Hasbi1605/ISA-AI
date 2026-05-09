# Issue: Sederhanakan Konfigurasi Memo

## Latar Belakang
Panel konfigurasi memo terasa terlalu padat untuk alur pembuatan draft cepat. Header dan helper text terlalu banyak, pilihan jenis dokumen belum perlu ditampilkan, beberapa placeholder kurang membantu, dan field penutup terisi otomatis padahal lebih aman dibiarkan sebagai input opsional.

## Tujuan
- Ringkas header konfigurasi agar hanya memberi konteks inti.
- Tetapkan jenis dokumen ke `memo_internal` tanpa menampilkan dropdown pada workspace memo.
- Jadikan format dokumen default `Otomatis`, lalu resolve ke `letter` atau `folio` saat generate.
- Perjelas placeholder field yang perlu diisi user.
- Kosongkan penutup secara default dan pastikan service Python tidak menambahkan penutup otomatis.

## Scope
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
- `laravel/app/Livewire/Memos/MemoWorkspace.php`
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`
- `python-ai/app/services/memo_generation.py`
- `python-ai/tests/test_memo_generation.py`

## Risiko
- Memo lama yang sudah menyimpan `folio` atau `letter` tetap harus bisa dibuka.
- Opsi `Otomatis` hanya preferensi UI; payload ke Python tetap harus berupa ukuran aktual yang didukung.
- Perubahan default penutup dapat mengubah hasil dokumen ketika user memang tidak mengisi field penutup.

## Langkah Implementasi
1. Sederhanakan copy dan struktur form memo.
2. Hapus dropdown jenis dari UI workspace dan simpan nilai default `memo_internal`.
3. Tambahkan opsi page size `auto` di workspace, dengan resolver konten pendek ke `letter` dan selain itu ke `folio`.
4. Perbarui placeholder penutup dan field lain yang masih terlalu generik.
5. Ubah default penutup Python menjadi kosong.
6. Tambahkan atau perbarui test relevan untuk default penutup dan format otomatis.

## Rencana Test
- `cd laravel && php artisan test tests/Feature/Memos/MemoWorkspaceTest.php`
- `cd laravel && npm run build`
- `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py`

