# Judul

Perbaikan regresi retrieval dokumen chat akibat state Chroma stale di worker `python-ai`

## Latar Belakang

Chat dengan dokumen terpilih kembali menampilkan fallback "Saya belum bisa membaca konteks..." meskipun dokumen berstatus `ready` dan vektor dokumen masih ada di Chroma.

Investigasi live di production menunjukkan:

- Request dari Laravel ke endpoint `/api/chat` jatuh ke fallback dokumen.
- Log `python-ai` mencatat error Chroma internal: `Error executing plan: Internal error: Error finding id`.
- Pemanggilan `search_relevant_chunks()` dari proses Python baru di container yang sama berhasil menemukan chunk dokumen.
- Restart container `python-ai` membuat request yang sama kembali berhasil.

Ini menunjukkan akar masalah berada pada runtime retrieval dokumen di worker chat yang hidup lama, bukan pada UI polling.

## Tujuan

- Menghilangkan ketergantungan retrieval dokumen chat pada state Chroma yang bisa stale di proses long-lived.
- Menjaga perilaku user-facing tetap sama selain bug hilang.
- Meminimalkan perubahan pada jalur Laravel dan jalur ingest dokumen yang sudah berjalan.

## Ruang Lingkup

- Menjalankan retrieval dokumen chat via subprocess segar di service `python-ai`.
- Menyesuaikan endpoint chat agar memakai wrapper retrieval baru.
- Menambahkan test Python untuk parsing payload subprocess retrieval.
- Deploy dan verifikasi ulang request chat dokumen di production.

## Di Luar Scope

- Mengubah alur upload dokumen.
- Mengubah model, prompt, atau kualitas jawaban secara sengaja.
- Mengganti Chroma dengan vector store lain.
- Mengubah UI chat atau polling lagi.

## Area / File Terkait

- `python-ai/app/chat_api.py`
- `python-ai/app/services/rag_retrieval.py`
- `python-ai/app/retrieval_runner.py`
- `python-ai/app/retrieval_tasks.py`
- `python-ai/tests/`

## Risiko

- Retrieval via subprocess menambah overhead kecil per request dokumen.
- Payload JSON retrieval harus tetap serializable dan robust saat subprocess gagal.
- Jika timeout terlalu pendek, query dokumen besar bisa gagal lebih cepat dari sebelumnya.

## Langkah Implementasi

1. Tambahkan task CLI untuk menjalankan `search_relevant_chunks()` di subprocess.
2. Tambahkan runner Python untuk memanggil task tersebut dan mem-parse payload JSON.
3. Ubah `chat_api` agar retrieval dokumen memakai runner baru.
4. Tambahkan test parsing dan fallback yang relevan.
5. Jalankan test Python yang terdampak.
6. Deploy ke production dan verifikasi request chat dokumen via Laravel container.

## Rencana Test

- `cd python-ai && source venv/bin/activate && pytest python-ai/tests/test_document_runner.py python-ai/tests/test_retrieval_runner.py python-ai/tests/test_app_routing.py`
- `cd python-ai && source venv/bin/activate && pytest`
- Validasi live:
  - request `AIService->sendChat()` dari container Laravel untuk dokumen milik user yang terdampak
  - cek log `python-ai`

## Kriteria Selesai

- Request chat dengan dokumen aktif tidak lagi jatuh ke fallback akibat error internal Chroma stale.
- Test Python relevan lulus.
- Production berhasil deploy.
- Verifikasi live menunjukkan dokumen yang sebelumnya gagal kini terbaca kembali.
