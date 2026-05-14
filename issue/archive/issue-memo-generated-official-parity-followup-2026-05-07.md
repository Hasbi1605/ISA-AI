# Perbaikan Paritas Memo Generated terhadap Memo Official

## Latar Belakang
Evaluasi ulang terhadap PDF official, PDF generated, dan PDF versi revisi menunjukkan tombol download versi revisi sudah membaik, tetapi masih ada gap isi dan layout yang membuat memo generated belum cukup menyerupai memo official. Masalah utama yang tersisa adalah kebocoran konteks evaluasi ke isi memo, duplikasi penutup, tembusan masuk ke badan memo, posisi QR/TTE placeholder terlalu ke kanan, formatting data PIC belum mengikuti pola official, dan pemisahan blok tembusan pada memo panjang.

## Tujuan
- Mencegah konteks evaluasi seperti baseline/uji/skenario masuk ke isi memo resmi.
- Menjaga tembusan hanya berada pada blok tembusan bawah dokumen.
- Mencegah penutup manual muncul dua kali.
- Menggeser QR/TTE placeholder lebih masuk ke tengah seperti pola official, tetapi tetap placeholder kotak.
- Merender data PIC/pegawai sebagai blok label-colon-value yang lebih rapi.
- Mengurangi risiko heading tembusan terpisah dari isi tembusan pada page break.

## Ruang Lingkup
- Perubahan generator DOCX memo di `python-ai/app/services/memo_generation.py`.
- Penambahan/perluasan test di `python-ai/tests/test_memo_generation.py`.
- Update PR aktif dengan ringkasan hasil setelah commit, push, dan deploy branch.

## Di Luar Scope
- Menghasilkan QR elektronik asli.
- Merge PR ke `main`.
- Refactor besar alur memo Laravel/OnlyOffice.
- Mengubah artefak evaluasi PDF yang sudah diekspor secara manual.

## Area / File Terkait
- `python-ai/app/services/memo_generation.py`
- `python-ai/tests/test_memo_generation.py`
- PR #132 pada branch `codex/memo-official-template-config`

## Risiko
- Sanitizer isi memo bisa terlalu agresif jika kata tertentu memang valid dalam memo nyata.
- Layout DOCX bergantung pada renderer OnlyOffice, sehingga keep-together perlu diuji secara praktis.
- Deploy branch tanpa merge berarti production sementara tidak persis sama dengan `main`.

## Langkah Implementasi
1. Tambahkan sanitasi body setelah output AI dihasilkan.
2. Strip blok `Tembusan:` dari body dan pertahankan tembusan dari konfigurasi.
3. Hapus kalimat penutup dari body bila penutup sudah disediakan konfigurasi.
4. Tambahkan prompt guard agar instruksi evaluasi/revisi tidak disalin sebagai isi memo.
5. Ubah posisi signature placeholder agar QR/TTE dan nama penandatangan lebih masuk ke tengah.
6. Tambahkan renderer key-value untuk data PIC/pegawai.
7. Tambahkan properti keep-with-next/keep-together untuk blok signature dan tembusan.
8. Tambahkan test untuk sanitizer, penutup, tembusan, posisi signature, dan data PIC.

## Rencana Test
- Jalankan `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py -q`.
- Jalankan full Python test bila test terarah sudah lulus: `cd python-ai && source venv/bin/activate && pytest`.
- Jalankan `git diff --check`.

## Kriteria Selesai
- Test memo generation lulus.
- Full Python test lulus atau kegagalan dijelaskan jelas bila di luar scope.
- Commit dibuat di branch PR aktif.
- Branch dipush ke remote.
- Production dideploy dari branch tanpa merge.
- PR #132 dikomentari atau body diperbarui dengan ringkasan perubahan dan verifikasi.
