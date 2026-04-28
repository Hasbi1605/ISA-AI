# Issue: Optimasi Memory Python AI di Production

## Latar Belakang

Droplet production menunjukkan penggunaan memory tinggi walau traffic masih ringan. Hasil inspeksi awal menunjukkan kontainer `python-ai` adalah kontributor utama, sedangkan Laravel, Horizon, Redis, dan Caddy relatif kecil.

## Bukti

- `python-ai` memakai sekitar `1.55 GiB` RAM di Docker.
- Ada dua worker Python besar pada proses `uvicorn --workers 2`.
- Import `app.services.rag_policy` ringan, sekitar beberapa MB.
- Import `app.services.rag_retrieval` berat, sekitar ratusan MB.
- Import `langchain_openai` jauh lebih berat daripada `openai` client biasa.

## Dugaan Akar Masalah

1. `python-ai` menjalankan 2 worker sehingga beban import berat terduplikasi.
2. Request chat biasa mengimpor `rag_service` terlalu awal, sehingga stack RAG berat ikut termuat walau dokumen tidak aktif.
3. Adapter embedding berbasis `langchain_openai` membawa dependency chain yang jauh lebih besar dari kebutuhan aktual aplikasi.

## Tujuan

- Menurunkan baseline memory `python-ai` tanpa mengubah perilaku chat dan RAG yang ada.
- Menjaga perubahan tetap kecil dan aman untuk deploy cepat.

## Rencana

1. Pisahkan import helper policy web search dari import retrieval dokumen di `python-ai/app/main.py`.
2. Ganti implementasi embedding helper agar memakai client `openai` yang lebih ringan, tetapi tetap kompatibel dengan pemanggil LangChain/Chroma.
3. Turunkan worker production `python-ai` dari 2 menjadi 1 untuk menghindari duplikasi memory.
4. Jalankan test Python dan Laravel yang relevan.
5. Deploy ke server dan bandingkan memory sesudah perubahan.

## Risiko

- Throughput request paralel ke `python-ai` bisa sedikit turun setelah worker menjadi 1.
- Wrapper embedding baru harus tetap mengembalikan format yang kompatibel dengan Chroma/LangChain.

## Verifikasi

- `cd python-ai && source venv/bin/activate && pytest`
- `cd laravel && php artisan test --filter=AuthenticationTest::test_chat_page_can_be_rendered_for_authenticated_user`
- Cek `docker stats` dan `free -h` di server sesudah deploy
