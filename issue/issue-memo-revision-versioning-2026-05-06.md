# Memo Revision Versioning Follow-up - 2026-05-06

## Tujuan
- Revisi memo via chat tetap berada dalam satu history memo, bukan membuat item sidebar baru.
- Memo memiliki beberapa versi yang bisa dipilih dari dropdown agar user bisa kembali ke hasil sebelum revisi.
- UX revisi via chat mengikuti pola tab Chat: pesan user tampil langsung dan avatar ISTA AI menampilkan animasi tiga titik saat proses.
- Instruksi revisi dibuat lebih terarah agar AI mempertahankan bagian yang tidak diminta berubah.

## Scope Implementasi
- Tambahkan penyimpanan versi memo (`memo_versions`) dan versi aktif pada `memos`.
- Ubah generator memo agar:
  - generate pertama membuat memo + versi 1,
  - revisi membuat versi baru pada memo yang sama,
  - versi aktif dipakai oleh OnlyOffice, download DOCX, dan export PDF.
- Tambahkan dropdown versi pada panel memo aktif.
- Tambahkan optimistic user message dan typing dots pada memo revision chat.
- Perketat prompt revisi di service Python agar perubahan terbatas pada instruksi eksplisit.

## Risiko
- Data produksi lama belum memiliki versi; migrasi perlu backfill versi 1 dari file memo yang sudah ada.
- OnlyOffice callback harus ikut memperbarui versi aktif agar edit manual tetap konsisten.
- Revisi konten tetap bergantung pada AI, sehingga prompt harus membatasi regenerasi penuh.

## Verifikasi
- Laravel feature test memo workspace dan OnlyOffice callback.
- Pint untuk file Laravel yang disentuh.
- Python test memo generation.
- Build asset frontend.
