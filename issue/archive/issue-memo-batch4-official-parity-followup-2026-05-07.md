# Memo Batch 4 Official Parity Follow-up

## Latar Belakang
Evaluasi batch 4 menunjukkan beberapa perbaikan batch sebelumnya sudah berhasil: PDF revisi tidak stale, memo panjang tidak pecah halaman, QR/TTE sudah tepat secara horizontal, data PIC lebih rapi, dan instruksi evaluasi umum tidak bocor. Gap yang tersisa lebih presisi: posisi vertikal QR/TTE masih terlalu tinggi pada memo pendek/sedang, compact folio masih terlalu memadatkan ritme atas dokumen, instruksi tambahan/revisi masih bisa bocor ke body, dan footer TTE masih muncul saat penandatangan kosong.

## Tujuan
- Menghapus kebocoran `additional_instruction` dan `revision_instruction` dari isi memo.
- Menjaga posisi horizontal QR/TTE yang sudah sesuai, sambil menurunkan blok signature pada memo pendek/sedang agar lebih mirip official.
- Memastikan compact folio tidak memangkas spacing atas dokumen.
- Menyembunyikan footer TTE saat penandatangan kosong.
- Memperketat prompt agar gaya memo lebih hemat, formal, dan tidak generik.
- Menambah regresi test untuk semua perilaku di atas.

## Non-goals
- Tidak membuat QR elektronik asli; QR tetap placeholder kotak.
- Tidak mengubah alur Laravel/OnlyOffice di luar kebutuhan deploy service Python.
- Tidak melakukan merge PR.

## Rencana Implementasi
1. Perkuat prompt memo: instruksi revisi/arahan tambahan adalah kontrol kerja, bukan konten naskah.
2. Tambahkan sanitizer final untuk membuang kalimat/klausa kontrol seperti `jangan diubah`, `perbaiki typo`, `pertahankan seluruh data`, `tanpa perubahan`, dan `metadata jangan berubah`.
3. Tambahkan kalkulasi spacing signature berbasis panjang body/page size agar QR/TTE memo pendek/sedang turun ke zona tanda tangan official.
4. Ubah compact layout agar separator/body top spacing tidak ikut dipadatkan.
5. Skip footer TTE ketika signatory kosong, termasuk searchable text.
6. Tambahkan test Python untuk prompt, sanitizer leakage, footer blank signatory, dan signature spacing.
7. Jalankan test Python relevan, commit, push branch PR aktif, update PR, lalu deploy production tanpa merge.

## Risiko
- Spacing signature terlalu besar dapat membuat memo letter tertentu menjadi lebih dekat ke batas bawah. Mitigasi: spacing dibuat dinamis berdasarkan panjang body dan compact layout.
- Sanitizer frasa kontrol dapat menghapus kalimat yang kebetulan memakai kata serupa. Mitigasi: pola difokuskan pada frasa instruksi revisi/prompt, bukan kata umum.
