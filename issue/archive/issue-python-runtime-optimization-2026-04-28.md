# Judul

Optimasi runtime Python AI dengan pemisahan service chat dan dokumen

## Latar Belakang

Droplet production saat idle masih menunjukkan penggunaan memori yang terasa ketat. Pemeriksaan live menunjukkan bottleneck utama ada pada container `python-ai`, yang setelah fitur dokumen dipakai memuat dependency berat seperti `spacy` dan `torch` ke proses API yang sama dengan chat.

Akibatnya, jalur chat yang seharusnya ringan ikut hidup di proses yang sudah “gendut”, sehingga kapasitas konkuren dan ruang aman RAM untuk traffic nyata menjadi terlalu sempit.

## Tujuan

- Menjaga runtime chat tetap ringan dan stabil.
- Mencegah beban OCR / ingest dokumen menetap di proses chat.
- Menurunkan baseline memori production setelah fitur dokumen pernah dipakai.
- Meminimalkan perubahan perilaku user-facing pada kualitas output chat dan dokumen.

## Ruang Lingkup

- Memisahkan entrypoint FastAPI untuk chat dan dokumen.
- Menambahkan service production terpisah untuk endpoint dokumen.
- Mengarahkan Laravel chat ke service chat dan operasi dokumen ke service dokumen.
- Menjalankan ingest dokumen berat melalui subprocess agar memori berat dilepas setelah proses selesai.
- Menambahkan / memperbarui test konfigurasi dan flow yang terdampak.

## Di Luar Scope

- Mengubah model AI, provider, atau prompt aktif.
- Mengubah kualitas OCR / summarization secara sengaja.
- Menulis autoscaling infra atau orkestrasi multi-node.
- Mengubah flow UX dokumen menjadi queue terpisah di luar arsitektur yang sudah ada.

## Area / File Terkait

- `python-ai/app/main.py`
- `python-ai/app/routers/documents.py`
- `python-ai/app/services/rag_ingest.py`
- `python-ai/Dockerfile`
- `docker-compose.production.yml`
- `laravel/config/services.php`
- `laravel/app/Services/AIService.php`
- `laravel/app/Services/DocumentLifecycleService.php`
- `laravel/app/Jobs/ProcessDocument.php`
- test Laravel dan Python yang menyentuh konfigurasi URL service

## Risiko

- Salah routing URL bisa membuat upload / delete / summarize dokumen gagal walau chat tetap hidup.
- Subprocess ingest harus mengembalikan status error dengan jelas agar queue Laravel tetap memberi status `error`, bukan hang.
- Perubahan service production harus menjaga volume `chroma_data` tetap dibagi ke chat dan dokumen.

## Langkah Implementasi

1. Pisahkan app FastAPI chat dan app FastAPI dokumen.
2. Ubah service production menjadi:
   - `python-ai` untuk chat / retrieval
   - `python-ai-docs` untuk endpoint dokumen
3. Tambahkan konfigurasi URL dokumen terpisah di Laravel dengan fallback aman.
4. Ubah upload/process dokumen Python agar ingest berat berjalan via subprocess helper.
5. Sesuaikan job Laravel untuk memanggil endpoint dokumen yang baru.
6. Tambahkan atau perbarui test Python dan Laravel yang relevan.
7. Verifikasi lokal.
8. Jika verifikasi memadai, commit, push, deploy, dan ukur ulang memori live di server.

## Rencana Test

- `cd python-ai && source venv/bin/activate && pytest`
- `cd laravel && php artisan test tests/Unit/AIServiceTest.php tests/Feature/Jobs/ProcessDocumentTest.php tests/Feature/Documents/DocumentDeletionTest.php tests/Feature/Documents/DocumentIndexTest.php tests/Feature/Chat/DocumentUploadTest.php`
- Validasi build/deploy production:
  - `docker compose -f docker-compose.production.yml build python-ai python-ai-docs`
  - `docker compose -f docker-compose.production.yml up -d python-ai python-ai-docs laravel horizon`
- Ukur pasca deploy:
  - `docker stats --no-stream`
  - `free -h`

## Kriteria Selesai

- Chat dan dokumen tidak lagi berbagi proses Python API yang sama.
- Operasi dokumen tetap berjalan normal dari Laravel.
- Test Python dan Laravel yang relevan lulus.
- Production berhasil deploy.
- Penggunaan memori live menunjukkan baseline yang lebih sehat dibanding kondisi sebelum optimasi.
