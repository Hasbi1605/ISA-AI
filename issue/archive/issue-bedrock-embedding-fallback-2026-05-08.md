# Bedrock Embedding Fallback dan Penghapusan Gemini

## Latar Belakang

GitHub Models untuk chat sedang terkena 429/rate protection, sementara endpoint embedding GitHub masih hidup. Risiko berikutnya adalah RAG ikut terganggu bila embedding GitHub juga kena limit karena konfigurasi embedding saat ini hanya memakai GitHub Models.

Gemini Flash berada sebagai last-resort chat fallback, tetapi model preview ini dianggap kurang stabil untuk produksi dan sudah tidak diperlukan karena Bedrock fallback tersedia.

## Tujuan

- Menambahkan fallback embedding non-GitHub melalui Amazon Bedrock.
- Memastikan vector yang dihasilkan tetap kompatibel dengan ChromaDB yang memakai dimensi maksimum internal.
- Menghapus Gemini dari rantai fallback chat.
- Menambah test kontrak agar perubahan tidak regresi.

## Scope

- `python-ai/config/ai_config.yaml`
- `python-ai/app/services/rag_embeddings.py`
- Test Python terkait konfigurasi dan wrapper embedding.

## Rencana Implementasi

1. Tambahkan konfigurasi embedding Bedrock `amazon.titan-embed-text-v2:0` sebagai fallback setelah embedding GitHub.
2. Implementasikan wrapper `BedrockTitanEmbeddings` dengan Bedrock Runtime `invoke` dan bearer token `AWS_BEARER_TOKEN_BEDROCK`.
3. Pertahankan padding/truncation ke `MAX_EMBEDDING_DIM` agar koleksi ChromaDB tetap konsisten.
4. Hapus model Gemini dari `lanes.chat.models`.
5. Tambahkan test untuk wrapper Bedrock, fallback provider, dan kontrak chat tanpa Gemini.
6. Jalankan verifikasi Python relevan dan full pytest.

## Risiko

- Titan Embeddings V2 berdimensi native 1024, sehingga vector dipadding ke dimensi maksimum internal. Ini aman untuk fallback darurat, tetapi dokumen baru yang diingest saat fallback aktif akan memakai ruang embedding berbeda dari OpenAI. Untuk kualitas retrieval ideal, fallback ini sebaiknya dipakai saat GitHub embedding tidak tersedia.
- Endpoint Bedrock membutuhkan `AWS_BEARER_TOKEN_BEDROCK` di lokal dan production.
