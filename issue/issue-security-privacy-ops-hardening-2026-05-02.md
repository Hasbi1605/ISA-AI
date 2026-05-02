# Issue Plan: Security, Privacy, dan Production Ops Hardening ISTA AI

## Latar Belakang
Fitur dokumen, chat, RAG, memo, dan OnlyOffice sudah mulai berjalan di production. Karena aplikasi ini menangani dokumen internal Istana Kepresidenan Yogyakarta, perlu ada hardening sebelum fitur memo difinalkan berdasarkan arahan mentor.

Dari analisis codebase saat ini:

- Production masih memakai `.env.droplet` untuk secret, URL service, DB, Redis, token internal, API key provider, dan OnlyOffice.
- Konfigurasi AI utama berada di `python-ai/config/ai_config.yaml`, termasuk model chat, embedding, retrieval, rerank, prompt, dan fallback.
- Upload dokumen disimpan di Laravel storage lokal dan diindeks ke Chroma melalui Python AI.
- Jalur RAG, summarize, embedding, web search, dan memo generation dapat mengirim prompt/konten relevan ke provider eksternal sesuai konfigurasi.
- Delete vector Python saat ini memakai `filename` saja, sehingga perlu diperkuat agar scoped per `user_id`.
- Signed file memo untuk OnlyOffice memakai temporary signed route dengan durasi 12 jam.
- `onlyoffice/documentserver` di production masih memakai tag `latest`.
- GitHub sudah mendeprecate endpoint lama `https://models.inference.ai.azure.com`; konfigurasi perlu diarahkan ke endpoint GitHub Models yang baru.

Referensi eksternal yang perlu dipakai saat dokumentasi privacy:

- OpenAI Business Data Privacy: https://openai.com/business-data/
- OpenAI API Data Controls: https://platform.openai.com/docs/models/how-we-use-your-data
- Microsoft Foundry Data Privacy: https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/openai/data-privacy
- GitHub Models endpoint deprecation: https://github.blog/changelog/2025-07-17-deprecation-of-azure-endpoint-for-github-models/
- GitHub Models API docs: https://docs.github.com/en/rest/models/inference

## Tujuan
- Memperjelas alur data dokumen, chat, RAG, memo, dan provider AI eksternal.
- Mengurangi risiko kebocoran, salah cleanup, atau akses dokumen lintas user.
- Merapikan konfigurasi production agar lebih eksplisit, stabil, dan tidak bergantung pada tag atau endpoint deprecated.
- Menyiapkan dokumentasi teknis yang bisa dibaca mentor/operator sebelum keputusan format memo final.
- Menurunkan risiko operasional pada droplet melalui checklist cleanup RAM/disk yang aman.

## Ruang Lingkup
- Membuat dokumentasi data flow dan privacy untuk fitur chat, dokumen, RAG, web search, memo, dan OnlyOffice.
- Memperjelas panduan konfigurasi production:
  - nilai yang harus diatur di `.env.droplet`
  - nilai yang harus diatur di `python-ai/config/ai_config.yaml`
  - cara aman mengubah model/prompt/provider tanpa mengubah logic
- Memperbarui dokumentasi deploy agar command production memakai `docker compose --env-file .env.droplet -f docker-compose.production.yml ...`.
- Mengganti konfigurasi GitHub Models dari endpoint deprecated `https://models.inference.ai.azure.com` ke endpoint baru yang didukung.
- Memperkuat delete vector dokumen agar memakai scope `user_id` dan `filename`.
- Menjadikan durasi signed URL OnlyOffice/Memo configurable dan menurunkan default production ke durasi pendek, misalnya 15-30 menit.
- Menghilangkan default token internal yang terlalu permisif pada production path, atau minimal fail-closed ketika token wajib tidak diset.
- Pin versi image OnlyOffice agar production tidak berubah perilaku karena update `latest`.
- Menambahkan checklist operasional aman untuk RAM/disk droplet:
  - cek `docker system df`
  - prune build cache/image lama yang aman
  - cek log journal
  - larangan eksplisit menjalankan `docker volume prune` tanpa backup
- Menambahkan atau memperbarui test untuk perubahan security dan konfigurasi yang berdampak perilaku.

## Di Luar Scope
- Menentukan format resmi body memo, font, spacing, margin, dan struktur isi memo. Ini menunggu konsultasi mentor.
- Menghapus branding OnlyOffice dengan patch/hack CSS/JS. White-label perlu jalur lisensi resmi.
- Migrasi storage besar ke S3, object storage terenkripsi, atau KMS. Itu bisa menjadi issue terpisah.
- Membuat policy retensi final untuk chat/dokumen/memo tanpa persetujuan mentor atau pemilik data.
- Mengubah seluruh provider AI atau mematikan semua provider eksternal secara sepihak.
- Menjalankan deploy production langsung dari agent jika aturan deploy tidak sedang di-override user.

## Area / File Terkait
- Konfigurasi AI:
  - `python-ai/config/ai_config.yaml`
  - `python-ai/config/README.md`
  - `python-ai/app/config_loader.py`
  - `python-ai/app/llm_manager.py`
  - `python-ai/app/services/rag_config.py`
  - `python-ai/app/services/rag_embeddings.py`
- Dokumen dan vector lifecycle:
  - `laravel/app/Services/DocumentLifecycleService.php`
  - `laravel/app/Jobs/ProcessDocument.php`
  - `python-ai/app/routers/documents.py`
  - `python-ai/app/services/rag_ingest.py`
  - `python-ai/app/services/rag_retrieval.py`
  - `python-ai/app/services/rag_summarization.py`
- Memo dan OnlyOffice:
  - `laravel/app/Livewire/Memos/MemoCanvas.php`
  - `laravel/app/Http/Controllers/Memos/MemoFileController.php`
  - `laravel/app/Http/Controllers/OnlyOfficeCallbackController.php`
  - `laravel/config/services.php`
- Production ops:
  - `docker-compose.production.yml`
  - `deploy/Caddyfile`
  - `deploy/digitalocean.env.example`
  - `docs/deploy-digitalocean.md`
  - `docs/deploy-digitalocean-checklist-demo.md`
- Dokumentasi baru yang kemungkinan ditambah:
  - `docs/data-flow-privacy.md`
  - `docs/production-config-guide.md`
  - `docs/production-maintenance.md`

## Risiko
- Perubahan endpoint GitHub Models dapat mengubah autentikasi, response shape, rate limit, atau billing attribution.
- Delete vector yang diubah ke `user_id + filename` harus tetap kompatibel dengan dokumen lama di Chroma.
- Signed URL yang terlalu pendek dapat membuat OnlyOffice gagal membuka dokumen jika load/save lambat.
- Fail-closed token internal dapat memutus service di production bila `.env.droplet` belum lengkap.
- Pinning OnlyOffice perlu memilih versi yang tersedia dan kompatibel dengan konfigurasi saat ini.
- Dokumentasi privacy harus tegas membedakan fakta codebase, kebijakan provider, dan keputusan organisasi yang belum ditetapkan.

## Langkah Implementasi
1. Dokumentasikan data flow dan provider eksternal.
   - Petakan kapan prompt, history chat, isi dokumen, chunk RAG, embedding input, web query, dan memo context dikirim ke service internal atau provider eksternal.
   - Tandai provider yang dipakai saat ini: GitHub Models/OpenAI-compatible, Groq, Gemini, LangSearch, dan OnlyOffice self-hosted.
   - Pisahkan klaim resmi provider dari keputusan internal ISTA AI yang masih perlu persetujuan.

2. Rapikan production config documentation.
   - Jelaskan batas tanggung jawab `.env.droplet` vs `python-ai/config/ai_config.yaml`.
   - Tambahkan contoh workflow aman untuk mengganti model/prompt.
   - Update command deploy agar memakai `--env-file .env.droplet`.
   - Pastikan contoh env OnlyOffice lengkap, tanpa secret nyata.

3. Migrasi endpoint GitHub Models.
   - Ubah base URL di `python-ai/config/ai_config.yaml` dari endpoint deprecated ke `https://models.github.ai/inference`.
   - Verifikasi apakah model chat dan embedding masih memakai ID yang benar.
   - Tambahkan atau update test agar config model tidak kembali ke endpoint deprecated.

4. Perkuat delete vector dokumen.
   - Ubah kontrak delete vector Python agar menerima `user_id` bersama `filename`.
   - Laravel mengirim `user_id` dokumen ketika cleanup vector.
   - Python menghapus vector dengan filter `filename` dan `user_id`.
   - Tambahkan fallback terukur untuk data lama bila diperlukan, tetapi jangan menghapus lintas user.

5. Hardening signed URL OnlyOffice/Memo.
   - Tambahkan config/env untuk TTL signed file memo, misalnya `ONLYOFFICE_SIGNED_URL_TTL_MINUTES`.
   - Turunkan default production ke 15-30 menit.
   - Tambahkan test untuk memastikan route signed tetap valid dalam TTL dan expired setelah melewati TTL.

6. Hardening token internal service.
   - Audit fallback token seperti `your_internal_api_secret`.
   - Buat service menolak berjalan atau menolak request pada production bila token masih kosong/default.
   - Tambahkan test untuk helper normalisasi/fail-closed token jika memungkinkan.

7. Stabilkan OnlyOffice image.
   - Pilih tag OnlyOffice yang kompatibel dengan versi production saat ini.
   - Pin tag di `docker-compose.production.yml` dan dokumentasikan cara update versi.
   - Verifikasi healthcheck dan route aset tetap sesuai.

8. Tambahkan operational maintenance checklist.
   - Dokumentasikan command inspeksi RAM/disk/log yang aman.
   - Dokumentasikan command cleanup non-destruktif.
   - Tambahkan peringatan eksplisit untuk backup sebelum menyentuh volume.

9. Review akhir dan pecah sub-issue jika scope terlalu besar.
   - Jika implementasi mulai melebar, pecah menjadi PR kecil:
     - docs privacy/config
     - endpoint migration
     - vector delete hardening
     - OnlyOffice TTL/image pin
     - ops maintenance docs

## Rencana Test
- Laravel:
  - `cd laravel && php artisan test`
  - Tambah/ubah test untuk:
    - `DocumentLifecycleService` mengirim `user_id` saat cleanup vector
    - signed memo file TTL configurable
    - callback OnlyOffice tetap memvalidasi JWT dan trusted URL
- Python:
  - `cd python-ai && source venv/bin/activate && pytest`
  - Tambah/ubah test untuk:
    - delete vector memakai filter `filename + user_id`
    - konfigurasi model tidak memakai endpoint deprecated
    - token internal production tidak menerima default kosong/permisif
- Ops smoke test setelah deploy:
  - `curl -I https://ista-ai.app/up`
  - buka `/chat`
  - upload dokumen kecil
  - chat dengan dokumen
  - generate memo sederhana
  - buka memo di OnlyOffice
  - cek `docker compose ps`
  - cek `docker system df` tanpa menjalankan cleanup destruktif

## Kriteria Selesai
- Ada dokumen data flow dan privacy yang menjelaskan data apa dikirim ke service/provider mana.
- Ada panduan konfigurasi production yang membedakan `.env.droplet` dan `ai_config.yaml`.
- Dokumentasi deploy memakai command `docker compose --env-file .env.droplet -f docker-compose.production.yml ...`.
- Tidak ada konfigurasi GitHub Models yang masih memakai endpoint deprecated.
- Delete vector dokumen tidak dapat menghapus data user lain dengan nama file yang sama.
- Signed URL memo/OnlyOffice punya TTL configurable dan default production lebih pendek.
- Token internal service tidak memakai default permisif pada production.
- Image OnlyOffice production tidak lagi memakai `latest`.
- Checklist maintenance RAM/disk/log tersedia dan membedakan command aman vs command berisiko.
- Test Laravel dan Python yang relevan sudah ditambahkan/diperbarui dan hijau.
- Risiko residual terkait kebijakan retensi dan dokumen sangat rahasia sudah dicatat sebagai keputusan mentor/pemilik data.
