# Perbaikan Memo Batch 9: Official Parity, Sanitizer, dan UI Edit Konfigurasi

## Latar Belakang

Evaluasi batch 9 menunjukkan beberapa regresi tersisa setelah perbaikan batch 8. Metadata, numbering, `pukul 09.00 WIB`, anti-halusinasi nama, bullet, dan italic istilah asing sudah membaik, tetapi hasil PDF masih belum cukup presisi terhadap memo official pada beberapa pola.

Temuan utama:
- Detail yang sudah dirender sebagai key-value block masih diulang sebagai numbered item atau paragraf.
- Artefak instruksi `Penutup manual ... apa adanya` masih bisa bocor ke body.
- Field data orang kosong dirender sebagai label kosong.
- Revisi gaya "maksimal dua paragraf" belum benar-benar dipaksa.
- Saat edit konfigurasi memo aktif, chat history masih muncul di bawah form konfigurasi.
- Spacing `Tembusan:` pada dokumen pendek masih terlalu jauh dari signature.

## Tujuan

- Membuat renderer/sanitizer memo lebih deterministik dan tidak menduplikasi data konfigurasi.
- Menjaga bug lama tetap tertutup: tidak mengubah jam menjadi `00 WIB`, tidak membocorkan `Catatan:`, tidak mengarang nama, tidak mengubah numbering konfigurasi, dan tetap memakai bullet resmi.
- Memperbaiki UI edit konfigurasi agar hanya menampilkan form konfigurasi dan tombol regenerate.
- Menambah regression test untuk temuan batch 9.

## Ruang Lingkup

- Python memo generation:
  - Dedup blok setelah configured key-value berdasarkan label dan value.
  - Sanitizer instruksi manual closing yang lebih luas.
  - Placeholder data orang kosong menjadi kalimat generik.
  - Enforcement revisi ringkas/maksimal paragraf yang lebih deterministik.
  - Spacing carbon copy pada dokumen pendek.
- Laravel memo workspace:
  - Hide chat history saat `showMemoConfiguration` aktif pada memo aktif.
  - Test Livewire untuk perilaku UI.
- Test:
  - Tambah test Python dan Laravel yang langsung menutup temuan batch 9.

## Di Luar Scope

- Tidak mengubah desain besar memo workspace.
- Tidak mengganti template header resmi secara keseluruhan.
- Tidak melakukan merge PR.
- Tidak membuat ulang batch evaluasi baru kecuali diperlukan untuk verifikasi lokal.

## Area / File Terkait

- `python-ai/app/services/memo_generation.py`
- `python-ai/tests/test_memo_generation.py`
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`

## Risiko

- Dedup berbasis value bisa terlalu agresif dan menghapus action item valid jika kalimatnya berisi value konfigurasi.
- Perubahan sanitizer manual closing tidak boleh menghapus closing manual dari field `closing`.
- Perubahan UI harus tetap mempertahankan mode chat revisi saat konfigurasi ditutup.
- Pengurangan spacing `Tembusan:` tidak boleh membuat tembusan menempel ke signature.

## Langkah Implementasi

1. Tambah test failing untuk:
   - key-value jadwal tidak diulang sebagai list/paragraf;
   - lokasi/periode tidak diulang sebagai list;
   - varian `Penutup manual ... apa adanya` tidak bocor;
   - field data orang kosong dirender generik;
   - revisi maksimal dua paragraf dipaksa;
   - edit konfigurasi menyembunyikan chat history.
2. Patch Python sanitizer dan renderer secara terarah.
3. Patch Blade condition untuk chat history saat edit konfigurasi.
4. Jalankan test Python dan Laravel relevan, lalu full test sesuai area yang disentuh.
5. Commit, push ke branch PR aktif, komentar PR, deploy production tanpa merge.

## Rencana Test

- `cd python-ai && source venv/bin/activate && pytest`
- `cd laravel && php artisan test`
- Jika perlu, jalankan test subset lebih dulu untuk iterasi cepat.

## Kriteria Selesai

- Semua test relevan lulus.
- Test regresi batch 9 ditambahkan.
- Commit dan push berhasil ke branch PR.
- PR diberi komentar update.
- Production dideploy tanpa merge dan health check berhasil.

