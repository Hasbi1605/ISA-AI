# Memo Batch 6 Official Parity Follow-up

## Tujuan
Menindaklanjuti temuan batch 6 agar hasil memo lebih presisi dengan memo official dan alur regenerate konfigurasi tidak membuat history baru.

## Temuan yang Ditangani
- Tombol regenerate dari konfigurasi pada memo aktif membuat memo baru, bukan versi baru di memo yang sama.
- Penutup otomatis dari model masih bisa menempel di body sehingga tidak mendapat jarak resmi sebelum penutup.
- Data PIC/pegawai dari konfigurasi belum selalu dipertahankan sebagai tabel label-value lengkap, terutama `keperluan`.
- Auto format masih terlalu cepat memilih folio untuk memo yang seharusnya muat di letter.
- Posisi QR/TTE pada memo folio pendek/sedang masih perlu diturunkan tanpa mengubah posisi horizontal.
- Beberapa frasa keluaran model masih kurang resmi atau berupa fragmen rusak.

## Rencana Implementasi
1. Ubah Livewire memo workspace: jika ada memo aktif, `generateConfiguredMemo()` membuat `MemoVersion` baru lewat `generateRevision()`.
2. Tambahkan test Laravel untuk memastikan regenerate konfigurasi mempertahankan satu history memo dan menambah versi.
3. Tambahkan post-processing Python:
   - pisahkan penutup yang digenerate model menjadi blok penutup resmi,
   - merge data label-value dari konfigurasi ke tabel body,
   - bersihkan frasa resmi yang rusak,
   - perbaiki estimasi auto letter/folio,
   - turunkan spacer QR/TTE untuk folio pendek/sedang.
4. Tambahkan test Python untuk setiap perilaku yang berubah.
5. Jalankan test relevan, commit, push, update PR, dan deploy branch tanpa merge.

## Risiko
- Spacer QR/TTE bersifat heuristik karena Word/OnlyOffice melakukan layout final sendiri.
- Penutup otomatis hanya diekstrak jika terlihat sebagai paragraf penutup resmi, agar kalimat konten biasa tidak hilang.
