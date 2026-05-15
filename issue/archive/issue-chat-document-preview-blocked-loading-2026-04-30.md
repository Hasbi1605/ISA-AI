# Issue Plan: Perbaiki Preview Dokumen Chat

## Masalah
- Preview PDF di chat sidebar muncul sebagai halaman yang diblokir Chrome.
- Preview DOCX/XLSX terlalu lama masuk ke state `ready`, sehingga modal preview terasa menunggu terlalu lama.

## Tujuan
- PDF bisa tampil inline tanpa diblokir browser.
- Preview Word/Excel diproses lebih cepat dan tidak menunggu antrean embedding dokumen.
- Perilaku preview tetap terjaga untuk dokumen milik user yang sedang login.

## Dugaan Akar Masalah
- PDF dirender lewat iframe yang dibungkus `sandbox`, sehingga viewer PDF Chrome tidak bisa jalan normal.
- Preview DOCX/XLSX baru dijadwalkan setelah proses embedding selesai, jadi preview ikut terlambat walaupun tidak bergantung pada embedding.

## Rencana Perbaikan
1. Hapus sandbox yang tidak perlu dari iframe PDF atau ganti ke rendering yang lebih ramah browser.
2. Jalankan preview rendering segera setelah file tersimpan, bukan menunggu job embedding selesai.
3. Pisahkan queue preview dari queue embedding agar preview tidak ikut antre di jalur yang sama.
4. Tambahkan atau perbarui test untuk:
   - PDF stream tetap inline dan bisa dibuka oleh owner.
   - Preview job dijadwalkan saat upload dokumen.
   - Viewer PDF tidak lagi memakai sandbox yang memblokir Chrome.

## Risiko
- Jika queue preview dipisah, Horizon harus tahu queue baru tersebut.
- Preview DOCX/XLSX masih bisa lama untuk file besar, tapi tidak lagi tertahan oleh job embedding.
- Perubahan preview harus tetap aman untuk authorization dan cleanup file preview lama.

