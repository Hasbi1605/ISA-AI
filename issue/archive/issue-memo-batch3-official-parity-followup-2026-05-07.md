# Issue: Memo Batch 3 Official Parity Follow-up

## Latar Belakang

Evaluasi batch 3 menunjukkan beberapa perbaikan batch sebelumnya sudah berhasil, terutama PDF revisi tidak lagi stale, instruksi evaluasi tidak bocor, duplikasi tembusan/penutup sudah terkendali, dan data PIC sudah mulai memakai format key-value resmi.

Gap tersisa paling besar berada pada layout renderer DOCX/PDF:

- QR/TTE placeholder sekarang terlalu masuk ke tengah/kiri dibanding screenshot official.
- Memo folio panjang masih dipaksa pecah halaman sehingga signature muncul di bagian atas halaman 2.
- Spacing blok data orang masih perlu dibuat lebih mirip official.
- Revisi "lebih singkat maksimal dua paragraf" masih bisa menghasilkan output lebih panjang.
- Saat penandatangan kosong, QR/TTE tanpa nama membuat draft terlihat tidak official.

## Tujuan

1. Kalibrasi posisi QR/TTE ke kolom tanda tangan kanan seperti screenshot official.
2. Hindari page break manual untuk long folio dan gunakan mode compact resmi agar blok akhir punya peluang tetap rapi di halaman yang sama.
3. Rapikan spacing dan continuation line blok `nama/NIP/jabatan`.
4. Tambahkan guardrail programatik untuk revisi yang meminta memo lebih singkat atau membatasi jumlah paragraf.
5. Sembunyikan blok QR/TTE bila penandatangan kosong agar tidak ada tanda tangan tanpa nama.

## Scope Implementasi

- Ubah `python-ai/app/services/memo_generation.py`.
- Tambah/update test di `python-ai/tests/test_memo_generation.py`.
- Jalankan verifikasi Python relevan dan, bila memungkinkan, suite Python penuh.
- Commit, push branch PR aktif, update komentar/body PR, dan deploy tanpa merge.

## Risiko

- Perubahan layout DOCX hanya bisa diuji unit pada struktur DOCX; hasil PDF final tetap dipengaruhi OnlyOffice.
- Kalibrasi QR berbasis rasio official dari screenshot, sehingga mungkin masih perlu fine-tuning minor setelah batch berikutnya.
- Guardrail revisi singkat memakai fallback deterministik dari konfigurasi agar tidak tergantung jawaban AI kedua.
