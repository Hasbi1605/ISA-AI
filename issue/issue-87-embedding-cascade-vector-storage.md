# Issue Plan: Embedding Cascade GitHub Models dan Storage Vector Laravel

## Deskripsi
Issue ini bertujuan untuk memindahkan tanggung jawab embedding dan pencarian vektor dari Python ke Laravel sepenuhnya (Laravel-only). Ini melibatkan implementasi cascade embedding menggunakan GitHub Models sebagai fallback jika satu token/model gagal, serta mekanisme penyimpanan vektor yang kompatibel dengan Laravel untuk menggantikan fungsionalitas Chroma.

Parent Issue: #84

## Status Rework
- PR `#97` sempat ter-merge ke `main`, lalu direvert melalui commit `d4dcdbb7f307a48616fb08e9987938fbd9181a62`.
- Branch recovery ini memulihkan implementasi issue `#87` di atas `main` terbaru tanpa mengubah history `main`.
- Fokus rework setelah revert:
  - menormalkan `meta->model` provider ke model canonical node cascade
  - mencegah fallback diam-diam ke provider default saat `targetModel` tidak bisa dipetakan
  - menambah regression test untuk scoped re-embedding per dokumen
  - menambah regression test untuk transisi dimensi `large` / `small`
  - menjaga lexical fallback tetap mengembalikan chunk saat query embedding gagal

## Tujuan
1.  **Embedding Cascade**: Implementasi 4 level fallback untuk embedding menggunakan GitHub Models:
    -   `text-embedding-3-large` + `GITHUB_TOKEN`
    -   `text-embedding-3-large` + `GITHUB_TOKEN_2`
    -   `text-embedding-3-small` + `GITHUB_TOKEN`
    -   `text-embedding-3-small` + `GITHUB_TOKEN_2`
2.  **Storage Vector**: Menyimpan embedding dalam database Laravel agar tidak perlu dihitung ulang setiap kali pencarian dilakukan.
3.  **Dimensi Eksplisit**: Menangani perbedaan dimensi (3072 untuk large, 1536 untuk small) secara aman.
4.  **Usage Logging**: Mencatat penggunaan token dan model untuk observabilitas.

## Ruang Lingkup
1.  **Konfigurasi**: Menambahkan konfigurasi cascade embedding di `config/ai.php`.
2.  **Service Baru**: Membuat `App\Services\AI\EmbeddingCascadeService` untuk menangani logika fallback.
3.  **Database**: 
    - Membuat model `App\Models\DocumentChunk`.
    - Menambah kolom `embedding`, `embedding_model`, and `embedding_dimensions` pada tabel `document_chunks`.
4.  **Refactor Retrieval**: Mengubah `LaravelDocumentRetrievalService` agar menggunakan cache embedding di database dan memanggil service cascade jika belum ada.

## Risiko
- **Kualitas Pencarian**: Perubahan dimensi embedding (large ke small) dalam satu dokumen dapat merusak hasil pencarian jika tidak ditangani dengan benar (misal: menghapus embedding lama jika model berubah).
- **Performa Database**: Mencari kemiripan kosinus (cosine similarity) di PHP/MySQL pada ribuan chunk mungkin lambat jika tidak dioptimalkan.
- **Rate Limit**: Cascade membantu, tetapi tetap ada risiko rate limit jika traffic tinggi.

## Langkah Implementasi

### 1. Fondasi dan Model
- [x] Buat migration untuk tabel `document_chunks` (jika belum lengkap) atau update untuk menambah kolom embedding.
- [x] Buat model `DocumentChunk` dan relasi di model `Document`.
- [x] Tambahkan konfigurasi `ai.embedding_cascade` di `config/ai.php`.

### 2. Embedding Cascade Service
- [x] Implementasikan `EmbeddingCascadeService` dengan metode `embed(array $inputs)`.
- [x] Pastikan error handling yang tepat untuk memicu fallback ke node berikutnya dalam cascade.
- [x] Tambahkan logging untuk setiap percobaan dalam cascade.
- [x] Tambahkan normalisasi model response ke model canonical node cascade.

### 3. Vector Storage & Retrieval Refactor
- [x] Update `LaravelDocumentRetrievalService` untuk melakukan "lazy ingestion":
    - Cek apakah chunk sudah ada di DB dan punya embedding dengan model/dimensi yang sesuai.
    - Jika belum, lakukan chunking (jika belum) dan hitung embedding via `EmbeddingCascadeService`.
    - Simpan ke DB.
- [x] Implementasikan pencarian vektor di database menggunakan `cosineSimilarity` (tetap di PHP untuk awal, sesuai arsitektur Laravel-only tanpa plugin DB khusus).
- [x] Pastikan re-embedding tetap scoped ke dokumen aktif.
- [x] Pastikan transisi dimensi `large` / `small` memicu re-embedding yang konsisten.

### 4. Verifikasi
- [x] Test unit untuk `EmbeddingCascadeService`.
- [x] Test integrasi untuk `LaravelDocumentRetrievalService` dengan database.
- [x] Pastikan tidak ada regresi pada flow chat yang sudah ada.

## Rencana Test
1.  **Test Fallback**: Mock error pada `GITHUB_TOKEN` dan pastikan sistem beralih ke `GITHUB_TOKEN_2`.
2.  **Test Canonical Model**: Pastikan response model provider dinormalkan ke model node yang disepakati.
3.  **Test Dimensi**: Pastikan jika model berubah dari large ke small, sistem tidak mencoba membandingkan vektor dengan panjang berbeda dan melakukan re-embedding yang benar.
4.  **Test Scoped Re-Embedding**: Pastikan komputasi embedding hanya menyentuh chunk dokumen aktif.
5.  **Test Lexical Fallback**: Pastikan pencarian tetap mengembalikan chunk ketika query embedding gagal total.

## Kriteria Selesai
- [x] Embedding cascade berjalan sesuai spesifikasi (4 level).
- [x] Embedding disimpan di database dan digunakan kembali (tidak re-compute on-the-fly setiap request).
- [x] Pencarian tetap akurat dan menangani dimensi 3072/1536.
- [x] Lolos test `php artisan test`.
