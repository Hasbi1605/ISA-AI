# Perbaikan Memo Official Formatting Batch 5

## Tujuan
Merapikan generator memo agar mengikuti pola official terbaru: key-value block tanpa border untuk detail kegiatan, preserve numbering dari konfigurasi, anti-halusinasi nama/data, sanitizer artefak instruksi, dan spacing penutup/list yang lebih resmi.

## Scope
- `python-ai/app/services/memo_generation.py`
- `python-ai/tests/test_memo_generation.py`

## Rencana Implementasi
1. Tambahkan parser key-value detail kegiatan (`hari/tanggal`, `pukul`, `tempat`, `agenda`) dan render sebagai aligned key-value block tanpa border.
2. Preserve numbered items dari konfigurasi ketika sumber content sudah berupa list bernomor eksplisit.
3. Tambahkan sanitizer untuk menghapus artefak `Catatan:` dan instruksi internal yang bocor.
4. Tambahkan anti-halusinasi untuk nama honorifik (`Bapak/Ibu/Sdr.`) yang tidak ada di konfigurasi.
5. Deteksi generated closing seperti `Dimohon...` di akhir body dan pindahkan/beri spacing sebagai penutup.
6. Rapikan nested bullet agar memakai bullet official dan tidak muncul dari data yang dikarang.
7. Tambahkan test regresi untuk lima temuan batch terbaru dan pola official baru.

## Risiko
- Sanitizer terlalu agresif dapat menghapus kata `Catatan` yang memang diinginkan user, jadi aturan dibatasi pada konteks instruksi internal.
- Preserve numbering harus tetap membiarkan revisi mengubah poin jika instruksi memang meminta perubahan eksplisit.

## Verifikasi
- Jalankan test Python relevan: `pytest tests/test_memo_generation.py`.
- Jika waktu memungkinkan, jalankan full pytest Python.
