# Issue: Memo Batch 2 Regression Fixes

## Latar Belakang

Pengujian ulang memo batch 2 menghasilkan 52 PDF. Beberapa perbaikan sebelumnya sudah terlihat berhasil, terutama PDF versi revisi yang tidak lagi stale, hilangnya kata evaluasi lama, dan layout memo panjang yang tidak lagi pecah halaman. Namun masih ada regresi dan gap resmi yang perlu ditutup sebelum hasil generated mendekati memo official.

## Temuan yang Harus Diperbaiki

1. Posisi kotak QR/TTE masih terlalu kanan dibanding memo official.
2. Blok `Tembusan:` masih dapat muncul di body memo pada revisi tembusan.
3. Penutup masih dapat muncul dua kali saat AI menulis penutup dan konfigurasi juga merender penutup.
4. Artefak sumber/web seperti `[SOURCES: ...]`, URL, JSON, dan snippet dapat bocor ke body memo.
5. Markdown literal seperti `**Judul**` masih muncul di PDF.
6. Data PIC/pegawai masih dapat dirender sebagai numbered list, belum konsisten sebagai blok label sejajar seperti official.
7. Penandatangan kosong masih fallback menjadi `Deni Mulyana`.
8. Konteks revisi masih mengirim searchable text penuh, sehingga AI bisa menyalin ulang metadata, tembusan, QR, dan footer ke body.

## Scope Implementasi

- Perkuat sanitizer final di Python sebelum DOCX dibuat.
- Jadikan renderer sebagai pemilik tunggal struktur resmi: metadata, closing, signature, QR/TTE, tembusan, dan footer.
- Geser layout QR placeholder lebih masuk ke tengah mengikuti posisi official, tetap dalam bentuk kotak placeholder.
- Format data PIC/pegawai sebagai tabel key-value bila pola data terdeteksi.
- Jangan fallback signatory kosong ke nama default di service Python.
- Bersihkan konteks revisi Laravel agar hanya mengirim isi utama memo saat ini, bukan keseluruhan searchable text.
- Tambahkan test regresi untuk semua pola temuan batch 2 yang bisa diverifikasi secara otomatis.

## Risiko

- Sanitizer yang terlalu agresif dapat menghapus teks legitimate bila user memang meminta URL atau kata `Tembusan:` sebagai materi pembahasan. Untuk memo resmi, risiko ini diterima karena struktur resmi harus renderer-only.
- Mengubah default signatory di Python dapat membuat request programatik tanpa signatory menghasilkan blok tanda tangan tanpa nama. UI Laravel tetap memiliki default `Deni Mulyana`, sehingga workflow normal tidak berubah.
- QR position diuji di level DOCX XML/indent. Verifikasi visual PDF tetap perlu dilakukan setelah deploy karena OnlyOffice dapat berbeda dari Word rendering.

## Rencana Verifikasi

- Jalankan test Python spesifik `tests/test_memo_generation.py`.
- Jalankan test Laravel spesifik `tests/Feature/Memos/MemoWorkspaceTest.php`.
- Jalankan `git diff --check`.
- Jika waktu memadai, jalankan full `python-ai` pytest karena perubahan ada di service runtime Python.

## Hasil Verifikasi

- `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py -q` -> 15 passed.
- `cd python-ai && source venv/bin/activate && pytest` -> 124 passed.
- `cd python-ai && source venv/bin/activate && python -m compileall app/services/memo_generation.py` -> passed.
- `cd laravel && php artisan test tests/Feature/Memos` -> 33 passed.
- `cd laravel && vendor/bin/pint --test app/Services/Memo/MemoGenerationService.php app/Livewire/Memos/MemoWorkspace.php tests/Feature/Memos/MemoWorkspaceTest.php` -> passed.
- `git diff --check` -> passed.
- `ruff` tidak tersedia di virtualenv lokal, sehingga lint Python divalidasi melalui compileall dan pytest penuh.
