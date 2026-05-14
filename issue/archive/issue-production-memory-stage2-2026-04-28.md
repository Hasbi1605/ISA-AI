# Issue: Optimasi Memory Production Tahap 2

## Latar Belakang

Tahap pertama sudah menurunkan baseline `python-ai` untuk chat biasa. Namun inspeksi proses server menunjukkan worker `python-ai` masih bisa membesar sampai sekitar 1 GiB ketika jalur dokumen ikut termuat, karena `rag_ingest` masih membawa `langchain_text_splitters`, `spacy`, dan `torch`.

## Fakta

- `app.llm_manager` sendiri tidak memuat `torch` atau `spacy`.
- Import `app.services.rag_ingest` memuat ratusan modul `torch` dan `spacy`.
- Worker `python-ai` di server teramati memetakan modul `spacy` dan `torch`.
- `mysql` production masih memakai setting lebih besar daripada compose lokal.
- Server belum memiliki swap.

## Tujuan

- Menurunkan memory spike jalur dokumen.
- Mengurangi footprint service pendukung yang aman untuk trafik sekitar 50 pegawai per hari.
- Menambah bantalan agar spike sesaat tidak langsung berujung OOM.

## Rencana

1. Ganti `RecursiveCharacterTextSplitter` dengan splitter lokal yang ringan di `python-ai`.
2. Tambahkan test untuk memastikan `rag_ingest` tidak lagi memuat `torch/spacy/langchain_text_splitters` saat import.
3. Tuning `mysql` production agar mendekati profil kebutuhan aplikasi saat ini.
4. Batasi auto-scale Horizon production ke level yang lebih masuk akal.
5. Tambahkan swap file di server sebagai safety net.
6. Verifikasi test Python/Laravel, deploy, lalu ukur ulang memory.

## Risiko

- Splitter lokal harus tetap menjaga chunking cukup stabil untuk kualitas retrieval.
- Tuning MySQL/Horizon terlalu agresif bisa menurunkan performa saat burst singkat.

## Verifikasi

- `cd python-ai && source venv/bin/activate && pytest`
- `cd laravel && php artisan test --filter=AuthenticationTest::test_chat_page_can_be_rendered_for_authenticated_user`
- `docker stats --no-stream`
- `free -h`
- `swapon --show`
