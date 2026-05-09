# Memo Final Batch 10 Audit and Renderer Tightening

## Tujuan
- Menutup temuan batch terbaru sebelum PR final: detail jadwal yang sudah masuk key-value block tidak boleh muncul lagi sebagai list/paragraf.
- Membuat paragraf body dan penutup tampil rata kanan-kiri seperti memo official.
- Menambah regresi test supaya bug batch lama tidak muncul kembali saat sanitizer diperketat.

## Temuan
- EVAL-12 masih menampilkan detail jadwal dua kali: `Hari/Tanggal` dan `Waktu` muncul sebagai numbered list, lalu muncul lagi sebagai key-value block.
- Beberapa paragraf body pendek masih left aligned karena renderer hanya justify untuk blok dengan panjang minimal tertentu.

## Rencana Implementasi
1. Petakan label aktivitas sinonim dari output model, terutama `Waktu`, ke label resmi `pukul`.
2. Perluas deteksi redundant activity detail agar list seperti `1. Hari/Tanggal...` dan `2. Waktu...` dihapus jika data yang sama sudah masuk key-value block.
3. Ubah alignment paragraf body biasa menjadi justify, tanpa mengubah metadata, key-value table, signature, footer, atau tembusan.
4. Tambahkan test regresi EVAL-12 dan alignment paragraf body.
5. Jalankan test Python relevan dan, bila aman, audit batch hasil generate.

## Risiko
- Deteksi redundant yang terlalu agresif bisa menghapus poin substantif yang kebetulan menyebut tanggal/jam. Mitigasi: hanya aktif saat ada configured key-value activity block dan item list cocok label/nilai aktivitas.
- Justify pada paragraf sangat pendek bisa tampak renggang jika paragraf satu baris. Di Word/OnlyOffice, justify baru terlihat pada baris yang wrap; paragraf satu baris tetap aman.
