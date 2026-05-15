# Audit dependency Python AI

## Tujuan
- Mengurangi ukuran image `python-ai-docs` dan konsumsi disk server.
- Menghapus dependency ML/CUDA yang tidak diperlukan oleh runtime produksi saat ini.
- Menjaga fitur inti tetap berjalan: chat/RAG, ingest dokumen, preview content, ekstraksi tabel, dan export HTML ke PDF/DOCX/XLSX/CSV.

## Temuan awal
- `python-ai/requirements.txt` berisi banyak package transitive hasil freeze, bukan dependency langsung aplikasi.
- `unstructured[all-docs]` dan `UnstructuredFileLoader` menarik stack layout/vision berat seperti `torch`, `torchvision`, `transformers`, `onnx`, `opencv`, dan paket `nvidia-*`.
- Kode runtime langsung memakai parser ringan: `pdfplumber`, `python-docx`, `openpyxl`, `beautifulsoup4`, `weasyprint`, `langchain-core`, `langchain-chroma`, `litellm`, `openai`, `tiktoken`, dan `rank-bm25`.

## Rencana
1. Ganti loader ingest dokumen dari `UnstructuredFileLoader` ke parser lokal ringan untuk PDF, DOCX, XLSX, CSV, dan TXT/MD.
2. Sederhanakan `requirements.txt` menjadi dependency langsung yang dibutuhkan runtime.
3. Hapus dependency sistem yang hanya relevan untuk stack OCR/Unstructured berat.
4. Tambahkan/ubah test agar memastikan dependency berat tidak kembali masuk.
5. Jalankan test Python relevan dan build Docker Python untuk memvalidasi dependency resolve.

## Risiko
- PDF scan/gambar tanpa text layer tidak lagi diproses lewat OCR otomatis di pipeline ingest ringan ini.
- Jika OCR scan PDF wajib, lebih baik dibuat fitur terpisah dengan worker/queue khusus agar tidak membebani image utama.

## Hasil implementasi
- `rag_ingest` sekarang memakai loader ringan lokal untuk PDF, DOCX, XLSX, CSV, TXT, dan Markdown.
- `requirements.txt` dipangkas dari daftar freeze 206 package menjadi 21 dependency langsung.
- Dependency langsung berat (`torch`, `torchvision`, `transformers`, `unstructured`, `unstructured_inference`, `spacy`, `timm`, `opencv-python`, `onnx`) tidak lagi ada di requirements.
- Docker runtime tidak lagi menginstal `libmagic`, `poppler-utils`, dan `tesseract-ocr` karena OCR/Unstructured tidak lagi menjadi bagian image utama.

## Catatan verifikasi
- Test Python relevan wajib dijalankan.
- Docker build lokal tidak bisa dijalankan bila Docker daemon/Colima belum aktif; validasi fallback adalah `pip install --dry-run -r python-ai/requirements.txt`.
