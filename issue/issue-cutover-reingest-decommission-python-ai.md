# Issue Plan: Cutover, Re-ingest Dokumen, dan Decommission Python AI

## Latar Belakang
Issue ini adalah tahap akhir turunan dari issue `#67`. Setelah capability chat, dokumen, summarization, dan retrieval sudah tersedia di Laravel-only dan lolos quality gate, sistem perlu dipindahkan secara penuh dari runtime hybrid ke runtime Laravel-only. Tahap ini mencakup cutover traffic, re-ingest dokumen lama, cleanup artefak lama, dan penghapusan `python-ai` dari deployment.

## Tujuan
- Memindahkan traffic produksi sepenuhnya ke runtime Laravel-only.
- Melakukan re-ingest dokumen lama ke provider-managed storage/vector path yang baru.
- Menonaktifkan dan menghapus komponen `python-ai` serta dependensi deploy yang tidak lagi dipakai.

## Ruang Lingkup
- Menentukan strategi cutover:
  - staged rollout
  - canary
  - rollback point yang jelas
- Menjalankan migrasi/re-ingest dokumen existing dari jalur lama ke jalur baru.
- Menonaktifkan feature flag runtime lama setelah quality gate terpenuhi.
- Membersihkan boundary HTTP internal ke Python.
- Menghapus service/container/dependency deploy yang tidak lagi dibutuhkan.
- Memperbarui dokumentasi operasional deployment dan recovery.

## Di Luar Scope
- Perubahan kualitas capability inti yang seharusnya sudah selesai di issue sebelumnya.
- Pengenalan provider atau arsitektur alternatif baru.
- Refactor produk tambahan yang tidak terkait penghapusan runtime lama.

## Area / File Terkait
- `docker-compose.yml`
- `laravel/app/Services/AIService.php` atau boundary penggantinya
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/app/Services/DocumentLifecycleService.php`
- `python-ai/*`
- dokumentasi deployment/testing repo

## Risiko
- Re-ingest dokumen lama memakan waktu dan biaya provider yang tidak kecil.
- Sebagian dokumen lama gagal dimigrasikan jika metadata lama tidak cukup.
- Rollback menjadi sulit jika runtime lama sudah dicabut terlalu cepat.
- Dependensi deploy lama tertinggal dan menciptakan kebingungan operasional.

## Langkah Implementasi
1. Tetapkan gate final sebelum cutover:
   - acceptance matrix lolos
   - shadow mode memadai
   - feature parity cukup
2. Buat job/command untuk re-ingest dokumen existing ke jalur baru.
3. Jalankan rollout bertahap dengan observability jelas.
4. Siapkan rollback prosedur selama runtime lama masih tersedia.
5. Setelah traffic stabil, nonaktifkan runtime lama dan lepaskan feature flag fallback ke Python.
6. Hapus service/container/config/dependency `python-ai` dari deployment.
7. Rapikan dokumentasi dan lakukan full verification akhir.

## Rencana Test
- Jalankan full test Laravel:
  - `cd laravel && php artisan test`
- Jalankan full test Python terakhir jika masih dipakai sebagai referensi sebelum decommission.
- Jalankan smoke test end-to-end setelah cutover:
  - chat umum
  - chat dengan dokumen
  - upload/process/summarize/delete dokumen
  - source rendering
- Verifikasi rollout metrics, failure path, dan rollback path terdokumentasi jelas.

## Kriteria Selesai
- Traffic produksi sudah berjalan penuh di Laravel-only.
- Dokumen lama yang perlu dipertahankan sudah di-re-ingest ke jalur baru.
- `python-ai` tidak lagi dibutuhkan untuk operasi normal.
- Konfigurasi deploy, dokumentasi, dan workflow verifikasi sudah diperbarui.
- Full verification akhir memadai sebelum runtime lama benar-benar dihapus.
