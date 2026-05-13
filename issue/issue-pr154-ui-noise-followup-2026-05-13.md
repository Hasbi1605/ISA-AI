# Issue: Follow-up UI Noise PR 154

## Konteks
PR #154 memperbaiki sejumlah anomali UX, tetapi beberapa teks status dan helper yang baru tampil dalam bahasa Indonesia membuat area kerja chat, memo, dan dokumen terasa terlalu ramai.

## Tujuan
- Menghapus toast sukses "Memo ... dimuat" yang muncul di atas header saat membuka memo.
- Membuat toolbar dokumen di sidebar lebih ringkas agar tombol tidak melebar setelah lokalisasi bahasa Indonesia.
- Menghapus teks instruksi permanen di bawah input chat utama.
- Menghapus hint visual `Enter untuk kirim, Shift+Enter...` pada composer revisi memo.
- Mengganti loading "Memuat chat..." di tengah panel dengan indikator halus yang tidak mengganggu.

## Scope Implementasi
1. Ubah state sukses load memo menjadi non-visual; error tetap terlihat.
2. Ringkas kontrol sidebar dokumen menjadi label pendek dan tombol icon-first dengan `aria-label`/`title`.
3. Hilangkan helper text attachment/disclaimer yang persisten dari composer chat utama.
4. Pertahankan affordance keyboard memo melalui `aria-label`/`sr-only`, tanpa teks visual.
5. Ubah loading chat menjadi skeleton/typing dots kecil atau status screen-reader saja.

## Risiko
- Test lama mungkin masih mengharapkan teks disclaimer atau pesan load memo.
- Perubahan copy perlu tetap aksesibel walau visual dipangkas.

## Verifikasi
- Jalankan test Laravel yang mencakup Memo dan Chat/Dokumen terkait.
- Jalankan build frontend Laravel.
