# Issue: Memo Batch 10 Regression Fixes

## Tujuan
Menutup regresi batch 10 agar hasil memo lebih stabil dan dekat dengan memo official, khususnya pada struktur key-value, spacing penutup, anti-halusinasi data, sinkronisasi viewer/export, dan persistensi prompt revisi chat.

## Temuan Utama
- Jadwal atau detail yang sudah masuk blok key-value masih muncul ulang sebagai item bernomor atau paragraf.
- Numbering bisa restart setelah key-value sehingga muncul pola `1, 1, 2`.
- Saat konfigurasi hanya meminta data tanpa nilai spesifik, sistem masih mengarang waktu, pengguna terdampak, atau detail operasional.
- Kalimat penutup seperti `Dimohon...` bisa menempel ke list/body dan beberapa revisi tidak punya penutup formal.
- Revisi "lebih singkat" terlalu terasa kompresi mentah dan kurang gaya official.
- Prompt revisi di panel chat bisa hilang saat cache Livewire lebih lama menimpa data DB.
- Viewer OnlyOffice bisa menampilkan versi stale sehingga DOCX/PDF dan tampilan editor tidak selalu konsisten.

## Rencana Implementasi
1. Perketat renderer/sanitizer Python:
   - Consume detail key-value secara deterministik dan hapus duplikasi setelah tabel.
   - Normalisasi numbering setelah blok dihapus.
   - Tambahkan guard anti-halusinasi untuk waktu kejadian/pengguna terdampak/PIC saat konfigurasi tidak memberi nilai.
   - Pisahkan trailing closing sentence dari akhir list/body dan tambahkan fallback closing formal bila kosong.
   - Perhalus format revisi ringkas menjadi bahasa official.
2. Perbaiki lifecycle dokumen Laravel:
   - Buat document key OnlyOffice memasukkan memo, version/current, timestamp, dan hash path.
   - Reject callback stale yang key-nya tidak cocok dengan file/version aktif.
   - Reload/destroy editor berdasarkan key unik agar viewer tidak menyimpan dokumen lama.
3. Perbaiki persistensi chat revisi:
   - Merge thread DB dan cache Livewire, jangan menulis cache stale yang lebih pendek.
   - Rekonstruksi pesan revisi dari `MemoVersion.revision_instruction` bila hilang.
4. Tambahkan regresi test:
   - Duplikasi key-value/list, numbering restart, anti-halusinasi data kosong, closing spacing/fallback, revisi ringkas.
   - Callback stale OnlyOffice, key unik, dan thread chat revisi tidak hilang.
5. Verifikasi:
   - Jalankan test Python relevan untuk generator memo.
   - Jalankan test Laravel relevan untuk memo workspace dan OnlyOffice callback.
   - Commit, push branch PR, komentar PR, deploy production tanpa merge.

## Risiko
- Perubahan sanitizer harus tetap mempertahankan fakta eksplisit konfigurasi. Test diarahkan pada kasus regresi batch 10 agar perbaikan tidak menghidupkan bug lama.
- Strict callback key bisa memutus sesi editor lama; itu disengaja supaya stale editor tidak menyimpan file yang salah.
