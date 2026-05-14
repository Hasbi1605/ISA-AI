# Percepat Respon ISTA AI Tanpa Menurunkan Kualitas

## Latar Belakang
Respon ISTA AI saat ini masih terasa lambat pada tiga jalur utama: chat biasa, membaca dokumen/RAG, dan web search. Kelambatan pada jalur dokumen sebagian wajar karena proses chunking/retrieval perlu menjaga ketepatan, tetapi chat biasa dan web search juga perlu terasa lebih responsif tanpa mengurangi kualitas jawaban yang sudah ada.

Analisis awal menunjukkan beberapa bottleneck berbasis kode:
- Jalur dokumen masih memakai retrieval subprocess per request melalui `python-ai/app/retrieval_runner.py`, sehingga ada overhead spawn process dan import dependency berat sebelum token pertama bisa dikirim.
- Jalur dokumen + web search melakukan retrieval dokumen lalu web search secara serial, padahal keduanya independen.
- Jalur web search memakai request HTTP sinkron dan cache in-memory yang masih sederhana.
- Jalur LLM streaming memakai cascade model sinkron; timeout/fallback yang terlalu longgar dapat memperpanjang waktu tunggu saat provider lambat.

## Tujuan
- Mempercepat time-to-first-byte dan total response time untuk chat biasa, dokumen/RAG, dan web search.
- Menjaga kualitas jawaban dokumen dengan tetap mempertahankan rerank/HyDE/fallback penting secara selektif, bukan mematikannya secara agresif.
- Membuat optimasi bertahap yang aman, terukur, dan mudah direview.
- Menambahkan/menyesuaikan test untuk memastikan jalur kualitas dan fallback tetap berjalan.

## Ruang Lingkup
- Optimasi jalur Python AI yang memengaruhi `/api/chat`.
- Optimasi retrieval dokumen agar mengurangi overhead blocking/subprocess bila aman.
- Parallelisasi pekerjaan independen seperti retrieval dokumen dan web context ketika keduanya dibutuhkan.
- Perbaikan cache/timeout yang aman untuk web search dan model cascade.
- Test Python relevan untuk retrieval runner, chat routing, web search/cache, dan streaming/fallback.
- Validasi Laravel/Python yang relevan karena Laravel memanggil service Python untuk chat streaming.

## Di Luar Scope
- Mengubah kualitas prompt utama, persona ISTA AI, atau gaya jawaban secara besar.
- Mematikan total rerank, HyDE, retrieval, atau web search demi kecepatan.
- Mengganti provider/model utama secara besar tanpa evaluasi kualitas.
- Refactor arsitektur besar lintas Laravel/Python yang tidak diperlukan untuk optimasi awal.
- Perubahan database/migration produksi.

## Area / File Terkait
- `python-ai/app/chat_api.py`: entry point `/api/chat`, policy web search, jalur dokumen, dan streaming response.
- `python-ai/app/retrieval_runner.py`: wrapper retrieval dokumen yang saat ini memakai subprocess.
- `python-ai/app/retrieval_tasks.py`: target CLI retrieval yang dapat menjadi kandidat reuse in-process.
- `python-ai/app/services/rag_retrieval.py`: search chunk dokumen, vector search, hybrid/rerank/HYDE.
- `python-ai/app/services/rag_policy.py`: policy web search dan `get_context_for_query`.
- `python-ai/app/services/langsearch_service.py`: web search, rerank, timeout, cache.
- `python-ai/app/services/llm_streaming.py`: LLM streaming, model cascade, timeout/fallback.
- `python-ai/app/llm_manager.py`: penggabungan konteks web/RAG dan pemanggilan streaming.
- `python-ai/tests/*`: test Python untuk routing, retrieval, policy, streaming, dan cache.
- `laravel/app/Services/AIService.php`: Laravel client untuk Python AI; kemungkinan hanya validasi integrasi/timeout, bukan target utama.

## Risiko
- In-process retrieval dapat mengubah karakteristik memory/concurrency dibanding subprocess. Jika tidak aman, perlu fallback ke subprocess.
- Parallelisasi dapat memunculkan race condition pada cache/service singleton bila tidak diberi guard.
- Timeout yang terlalu agresif dapat membuat fallback ke model lebih lemah dan menurunkan kualitas jawaban kompleks.
- Mengurangi kandidat rerank atau skip HyDE terlalu agresif dapat menurunkan akurasi dokumen.
- Optimasi latency tanpa benchmark/logging bisa sulit dibuktikan; minimal perlu test perilaku dan ringkasan area yang dipercepat.

## Langkah Implementasi
1. Petakan jalur aktual chat biasa, dokumen, dan web search dari kode serta pilih slice optimasi dengan dampak tinggi dan risiko terkendali.
2. Tambahkan jalur retrieval yang mengurangi overhead subprocess secara aman, dengan fallback config/env ke subprocess jika in-process bermasalah.
3. Parallelkan retrieval dokumen dan web context pada `chat_api.py` hanya saat keduanya dibutuhkan, sambil menjaga output prompt/sources tetap sama.
4. Perkuat cache/thread-safety web search dan hindari network call yang tidak perlu untuk query yang sudah cache hit.
5. Sesuaikan timeout/cascade secara konservatif melalui config/env bila tersedia, tanpa memaksa fallback terlalu cepat.
6. Tambahkan atau update test untuk jalur dokumen, web search, cache, dan fallback agar kualitas tidak turun.
7. Jalankan validasi Python dan Laravel relevan, lalu lakukan PR review loop dan deploy preview sesuai workflow.

## Rencana Test
- `cd python-ai && source venv/bin/activate && pytest` atau subset relevan lebih dulu seperti:
  - `pytest tests/test_retrieval_runner.py`
  - `pytest tests/test_app_routing.py`
  - `pytest tests/test_llm_streaming.py`
  - test baru/terkait untuk `langsearch_service` atau `rag_policy` bila ditambahkan.
- `cd laravel && php artisan test` atau subset Laravel relevan bila file Laravel tersentuh.
- `git diff --check` sebelum commit.
- Setelah PR dibuat dan branch dideploy ke `https://ista-ai.app`, jalankan browser QA pada chat biasa, chat dengan dokumen, dan web search.

## Kriteria Selesai
- Jalur chat biasa, dokumen/RAG, dan web search memiliki optimasi latency yang aman tanpa menghapus fitur kualitas utama.
- Test relevan ditambahkan/diupdate dan lulus.
- PR dibuat, dideploy ke `https://ista-ai.app`, dan browser QA pasca-deploy selesai.
- Review/QC tidak menemukan blocker.
- Full final verification Laravel dan Python dijalankan sebelum meminta approval merge.

## Follow-up Sebelum Merge: Navigasi Saat Chat Loading

### Masalah
User masih tidak bisa membuat chat baru atau pindah ke history chat lain ketika request chat sedang loading, baik chat biasa, RAG dokumen, maupun web search. Perilaku yang diinginkan adalah navigasi history/new chat tetap responsif walaupun proses AI untuk percakapan sebelumnya masih berjalan.

### Scope Tambahan
- Perbaiki mekanisme UI/Livewire chat history agar tombol New Chat dan history tidak terkunci oleh proses streaming chat yang lama.
- Pastikan perpindahan history tidak merusak penyimpanan jawaban AI untuk percakapan asal.
- Tambahkan test Laravel/JS-relevan bila memungkinkan untuk mencegah regresi.

### Risiko Tambahan
- Livewire request panjang dapat mengantre action lain pada komponen yang sama; fix harus menghindari kehilangan pesan atau menyimpan assistant response ke conversation yang salah.
- Jika navigasi dilakukan via full page reload, proses streaming yang sedang berjalan bisa dibatalkan di browser; perlu memastikan data server tetap konsisten atau UX-nya jelas.
