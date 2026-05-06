# Penyelarasan Generator Memo dengan Format Manual

GitHub Issue: https://github.com/Hasbi1605/ISTA-AI/issues/131

## Latar Belakang
Generator memo saat ini masih memakai input prompt bebas dan menghasilkan DOCX sederhana yang belum mengikuti contoh memorandum manual. Dua contoh manual menunjukkan pola konsisten: kop instansi tanpa logo, judul `MEMORANDUM`, nomor, metadata `Yth./Dari/Hal/Tanggal`, garis pemisah, isi formal Arial dengan indentasi paragraf, blok tanda tangan di kanan, tembusan, dan footer TTE.

## Tujuan
- Mengubah alur tab Memo menjadi konfigurasi terstruktur lebih dulu, lalu chat revisi setelah draft tersedia.
- Menjaga layout tab Memo yang sama, hanya isi panel tengah dibuat dinamis.
- Membuat DOCX hasil AI lebih dekat dengan contoh manual dari sisi font, spacing, margin, gaya penulisan, dan struktur memorandum.

## Ruang Lingkup
- Livewire Memo Workspace: field konfigurasi memo, mode konfigurasi/revisi, ringkasan konfigurasi, dan payload generation.
- Kontrak Laravel ke Python: kirim konfigurasi memo terstruktur.
- Python memo generation: prompt body memo berbasis konfigurasi dan renderer DOCX resmi.
- Persist konfigurasi memo agar memo lama bisa dibuka dengan ringkasan konfigurasi.
- Test relevan untuk Livewire, service Laravel, router Python, dan generator DOCX.

## Di Luar Scope
- Validasi nomor memo terhadap sistem persuratan asli.
- Tanda tangan elektronik/QR valid dan integrasi BSrE.
- Export PDF dari DOCX final.
- OCR atau auto-learning template dari PDF secara runtime.

## Area / File Terkait
- `laravel/app/Livewire/Memos/MemoWorkspace.php`
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
- `laravel/resources/views/livewire/memos/partials/memo-preview-panel.blade.php`
- `laravel/app/Services/Memo/MemoGenerationService.php`
- `laravel/app/Models/Memo.php`
- `laravel/database/migrations/*memos*`
- `python-ai/app/services/memo_generation.py`
- `python-ai/app/routers/memos.py`
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`
- `python-ai/tests/test_memo_generation.py`

## Risiko
- DOCX dibuat dengan `python-docx`, sehingga hasil akhir di OnlyOffice/Word bisa sedikit berbeda dari PDF manual.
- Contoh manual memakai dua tinggi halaman berbeda; konfigurasi perlu menyediakan pilihan format halaman agar tidak memaksa satu ukuran.
- QR/TTE manual tidak boleh dipalsukan; generator hanya menyediakan placeholder visual sampai proses tanda tangan elektronik asli tersedia.

## Langkah Implementasi
1. Tambahkan konfigurasi memo terstruktur dan simpan ke database.
2. Ubah panel Memo agar awalnya menampilkan konfigurasi, lalu setelah draft dibuat menampilkan chat revisi di panel yang sama.
3. Kirim konfigurasi dari Laravel ke service Python.
4. Ubah prompt Python agar AI hanya menghasilkan isi memo sesuai data konfigurasi dan contoh gaya manual.
5. Render DOCX dengan kop, metadata, garis pemisah, isi Arial justified, blok TTE placeholder, tembusan, dan footer.
6. Perbarui preview/empty copy seperlunya agar sesuai alur baru.
7. Tambahkan test untuk payload konfigurasi, persist konfigurasi, dan struktur DOCX.

## Rencana Test
- Laravel: jalankan test fitur memo workspace yang terdampak.
- Python: jalankan `pytest python-ai/tests/test_memo_generation.py` dari virtualenv.
- Jika perubahan menyentuh build UI, jalankan minimal `npm run build` di `laravel`.

## Follow-up 2026-05-06: Preview dan Export
- Preview HTML di panel kanan tidak cukup akurat untuk memo resmi dan perlu diganti dengan satu panel `Dokumen` berbasis OnlyOffice.
- Tombol `DOCX` harus mengunduh file DOCX dari `memos.file_path` yang sama dengan dokumen OnlyOffice.
- Tombol `PDF` harus mengonversi DOCX final melalui OnlyOffice Conversion API, bukan membuat PDF dari HTML/searchable text.
- Tambahkan test agar export PDF tidak lagi memakai preview HTML sebagai sumber dokumen.

## Kriteria Selesai
- User mengisi konfigurasi di panel Memo yang sama sebelum draft dibuat.
- Chat revisi muncul setelah draft memo tersedia.
- Payload konfigurasi terkirim dan tersimpan.
- DOCX hasil generator memakai struktur memorandum resmi yang dekat dengan contoh manual.
- Test relevan berhasil atau kegagalan dijelaskan dengan jelas.
