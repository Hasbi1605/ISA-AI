# Issue #204 — Perbaikan Akurasi Web Search dan Pesan Upload Chat

## Latar Belakang

Screenshot menunjukkan jawaban web search untuk topik terbaru dapat memuat tanggal atau isu yang tidak cukup kuat didukung rujukan. Screenshot lain menunjukkan flash message upload masih memakai validasi default Bahasa Inggris: `The chat attachment field must be a file of type: pdf, docx, xlsx, csv.`

## Tujuan

- Membuat query web search untuk permintaan terbaru/isu lebih tepat dan lebih segar.
- Mencegah jawaban real-time berlanjut dari pengetahuan umum ketika web search tidak menghasilkan sumber.
- Memperkuat prompt agar jawaban tidak menambahkan tanggal, angka, atau peristiwa yang tidak ada di hasil web.
- Mengubah pesan validasi upload chat menjadi Bahasa Indonesia.

## Ruang Lingkup

- Python AI: routing realtime, query web search, konteks saat hasil kosong, dan prompt web search.
- Laravel Livewire chat: custom validation message untuk lampiran chat.
- Test regresi Python dan Laravel yang relevan.

## Di Luar Scope

- Mengganti provider web search.
- Mengubah tampilan UI besar-besaran.
- Menambah fitur citation inline baru.
- Menjalankan full PR merge/deploy.

## Area / File Terkait

- `python-ai/app/services/rag_config.py`
- `python-ai/app/services/rag_policy.py`
- `python-ai/app/services/langsearch_service.py`
- `python-ai/app/config_loader.py`
- `python-ai/config/ai_config.yaml`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `python-ai/tests/test_web_search_tuning.py`
- `python-ai/tests/test_prompt_contracts.py`
- `python-ai/tests/test_langsearch_service_cache.py`
- `laravel/tests/Feature/Chat/DocumentUploadTest.php`

## Risiko

- Freshness `oneDay` dapat menghasilkan lebih sedikit hasil untuk query tertentu; mitigasi: fallback eksplisit menyatakan sumber tidak cukup, bukan mengarang.
- Prompt yang terlalu ketat bisa membuat jawaban lebih konservatif; ini lebih aman untuk informasi terbaru.
- Validasi upload Livewire bisa memilih rule `mimes` atau `mimetypes`; mitigasi: keduanya diberi pesan Indonesia yang sama.

## Langkah Implementasi

1. Tambahkan pola realtime untuk query isu/perkembangan terbaru.
2. Bersihkan query search dari kata perintah seperti `cari`, normalisasi `issue` menjadi `isu`, dan tambahkan konteks `berita` bila query realtime belum punya konteks berita/isu.
3. Tambahkan konteks web kosong ketika search diminta tetapi tidak ada hasil.
4. Perkuat prompt web search di YAML dan fallback config.
5. Tambahkan custom validation messages untuk `chatAttachment`.
6. Tambahkan test regresi Python dan Laravel.

## Rencana Test

- `cd python-ai && source venv/bin/activate && pytest tests/test_web_search_tuning.py tests/test_prompt_contracts.py tests/test_langsearch_service_cache.py`
- `cd laravel && php artisan test --filter=DocumentUploadTest`

## Kriteria Selesai

- Query seperti `Cari issue tentang prabowo terbaru` diperlakukan sebagai realtime high dan memakai freshness `oneDay`.
- Query search yang dikirim ke provider lebih bersih dan relevan.
- Jika web search kosong, prompt tetap memaksa jawaban konservatif berbasis ketiadaan sumber.
- Pesan upload invalid tampil dalam Bahasa Indonesia.
- Test relevan lulus atau hambatan verifikasi tercatat jelas.
