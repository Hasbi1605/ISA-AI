# Memo Dashboard Navigation dan Visual Parity

## Tujuan

Menyelaraskan akses dan pengalaman visual fitur Memo dengan tab Chat tanpa mengubah alur generate/revisi memo.

## Scope

- Tambahkan tombol `Buka Memo` di dashboard, berdampingan dengan `Buka Chat`.
- Untuk guest, sediakan redirect login yang menyimpan intended URL ke `/chat?tab=memo`.
- Jadikan logo `ISTA AI` di header tab Memo sebagai tombol `New Memo`.
- Samakan latar panel Memo dengan tekstur dan warna dasar tab Chat.

## Non-Scope

- Tidak mengubah logic generate memo, revisi memo, versioning, export, atau OnlyOffice.
- Tidak melakukan redesign besar pada bubble chat, form konfigurasi, atau sidebar.

## Risiko

- Perubahan Blade bisa memengaruhi snapshot/render Livewire.
- Route guest baru harus tidak mengganggu flow `/guest-chat` yang sudah ada.

## Verifikasi

- Jalankan test dashboard untuk route dan tombol baru.
- Jalankan test render Memo Workspace untuk logo dan background parity.
- Jalankan formatter/check yang relevan untuk file PHP/Blade.
