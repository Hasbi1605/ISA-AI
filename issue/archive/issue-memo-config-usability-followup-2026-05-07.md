# Memo Config Usability Follow-up - 2026-05-07

## Tujuan
- Panel Dokumen menampilkan loading "sedang membuat memo" saat generate awal berjalan.
- Mode format dokumen `Otomatis` memilih Letter/Folio berdasarkan isi memo final, bukan hanya input konfigurasi awal.
- Placeholder konfigurasi memo dibuat lebih general agar tidak mengarahkan user ke kasus PIC/aplikasi tertentu.
- Field catatan tambahan diperjelas menjadi arahan tambahan/gaya agar user tahu fungsinya.
- Sidebar history memo punya tombol hapus yang konsisten dengan tab Chat.

## Scope Implementasi
- Update Livewire memo workspace dan partial Blade terkait panel dokumen, konfigurasi, dan sidebar history.
- Tambahkan aksi hapus memo pada workspace.
- Update service generator Laravel agar menerima final page size dari Python.
- Update generator Python agar resolve `auto` setelah body memo selesai dibuat.
- Tambahkan/ubah test Laravel dan Python untuk perilaku utama.

## Risiko
- Perubahan auto page size menyentuh kontrak Laravel-Python via header response baru.
- Memo lama tetap bisa dibaca karena `page_size_mode` lama tetap dipertahankan.
- Delete history memakai soft delete `memos`, sehingga file fisik tetap aman dan tidak langsung hilang permanen.

## Verifikasi
- Laravel feature tests untuk MemoWorkspace dan area memo terkait.
- Python test `test_memo_generation.py`.
- Pint dan Vite build.
