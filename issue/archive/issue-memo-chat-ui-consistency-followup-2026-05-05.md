# Memo Chat UI Consistency Follow-up

## Tujuan
Menyamakan pengalaman tab Memo dengan tab Chat setelah deploy uji PR #130, tanpa mengubah alur bisnis pembuatan memo.

## Scope
- Sidebar memo mengikuti perilaku sidebar chat: animasi buka/tutup, hover/active state, grouping riwayat, dan footer pengaturan akun.
- Tombol pembuatan memo memakai label `New Memo`.
- Header dan kolom chat memo mengikuti warna, ukuran brand, ikon collapse, tab toggle, dan dark mode control tab chat.
- Composer memo mengikuti bentuk floating composer tab chat tanpa hover tambahan.
- Tab toggle Chat/Memo kembali berada di header kolom chat memo, bukan di header preview.

## Risiko
- Komponen Memo adalah Livewire child di dalam layout Chat, jadi kontrol `activeTab` dan `darkMode` harus tetap membaca state Alpine parent.
- Perubahan sidebar harus tetap aman di desktop dan mobile.
- Perubahan markup harus mempertahankan test persistence chat memo dan switching preview/editor yang sudah ada.

## Verifikasi
- Jalankan test fitur memo/chat yang relevan.
- Jalankan build frontend.
- Jalankan format/lint targeted untuk file yang disentuh.
