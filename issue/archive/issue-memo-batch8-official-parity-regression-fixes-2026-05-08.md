# Perbaikan Memo Batch 8 Official Parity dan Regresi

## Latar Belakang
Evaluasi batch 8 menunjukkan beberapa bug tersisa pada memo hasil generate/revisi: nilai waktu di key-value block terpotong menjadi `00 WIB`, detail kegiatan dari konfigurasi masih bisa muncul ganda sebagai daftar bernomor, arahan tambahan seperti `Penutup manual apa adanya` dapat bocor ke body, dan revisi chat sempit masih berisiko meregenerasi isi terlalu luas.

## Tujuan
- Menjadikan data eksplisit konfigurasi sebagai sumber kebenaran untuk key-value block.
- Menjaga format official untuk detail kegiatan tanpa duplikasi yang mengganggu.
- Memperketat sanitizer agar instruksi internal tidak masuk ke naskah.
- Mengurangi risiko revisi sempit menulis ulang body memo secara liar.
- Menjaga perbaikan lama: anti-halusinasi PIC/nama, penomoran konfigurasi, closing block, bullet resmi, dan italic istilah asing.

## Ruang Lingkup
- Renderer/sanitizer memo di `python-ai/app/services/memo_generation.py`.
- Test regresi memo di `python-ai/tests/test_memo_generation.py`.
- Routing revisi sempit di `laravel/app/Livewire/Memos/MemoWorkspace.php`.
- Test Livewire yang memastikan revisi metadata/penutup/typo format sempit memakai body saat ini.

## Di Luar Scope
- Mengubah template kop/header resmi secara besar.
- Mengubah skenario batch evaluation atau data PDF yang sudah diekspor.
- Deploy production sebelum verifikasi lokal memadai dan arahan lanjutan jelas.

## Area / File Terkait
- `python-ai/app/services/memo_generation.py`: parsing key-value, activity extraction, dedupe body, sanitizer artifact.
- `python-ai/tests/test_memo_generation.py`: test jam, lokasi/periode, duplikasi detail kegiatan, bocor instruksi.
- `laravel/app/Livewire/Memos/MemoWorkspace.php`: klasifikasi revisi yang boleh memakai `body_override`.
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`: test request payload untuk revisi sempit.

## Risiko
- Terlalu agresif menghapus blok duplikat dapat menghapus isi wajib yang bukan sekadar detail kegiatan.
- Revisi typo/format harus tetap bisa mengubah bagian isi yang diminta tanpa mengubah bagian lain.
- Normalisasi nilai key-value tidak boleh merusak NIP, nomor kontak, nomor surat, atau jam dengan titik.

## Langkah Implementasi
1. Perbaiki cleaner nilai key-value agar angka jam seperti `09.00 WIB` tidak dianggap nomor list.
2. Gunakan cleaner khusus untuk label kegiatan seperti `pukul`, `hari/tanggal`, `tempat`, `agenda`, `lokasi asal`, `lokasi tujuan`, dan `periode`.
3. Perluas ekstraksi detail kegiatan dari konfigurasi untuk pola tanggal/pukul/tempat berbasis koma dan kalimat tanpa kata `pada`.
4. Hapus blok body yang menduplikasi key-value konfigurasi setelah renderer menyisipkan blok official.
5. Tambahkan pola sanitizer untuk arahan internal penutup manual yang bocor.
6. Perbaiki klasifikasi revisi Laravel agar revisi penutup/tembusan/metadata tetap memakai body saat ini, dan revisi typo/format sempit tidak melebar ketika bisa dirutekan aman.
7. Tambahkan test regresi Python dan Laravel.

## Rencana Test
- Jalankan subset Python: `cd python-ai && source venv/bin/activate && pytest tests/test_memo_generation.py`.
- Jalankan full Python jika subset sudah hijau: `cd python-ai && source venv/bin/activate && pytest`.
- Jalankan subset Laravel: `cd laravel && php artisan test tests/Feature/Memos/MemoWorkspaceTest.php`.
- Jika perubahan Laravel aman, jalankan test Laravel relevan tambahan bila diperlukan.

## Kriteria Selesai
- Jam `09.00 WIB` dan `10.00 WIB` tetap utuh di table key-value.
- Detail lokasi/periode dari konfigurasi tidak terduplikasi sebagai list body.
- Instruksi tambahan/manual closing tidak bocor ke body.
- Penomoran konfigurasi, anti-halusinasi PIC, closing block, dan italic istilah asing tetap lolos test.
- Revisi sempit mengirim `body_override` saat aman agar body lama tidak ditulis ulang liar.
- Test relevan Python dan Laravel lulus.
