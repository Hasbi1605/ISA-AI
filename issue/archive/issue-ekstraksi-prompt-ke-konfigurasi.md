# Issue Planning: Ekstraksi Prompt ke File Konfigurasi Terpisah

## 1) Latar Belakang
Prompt system saat ini tersebar di berbagai file Python (.py) secara hardcoded, sehingga user sulit mengganti prompt tanpa menyentuh logika aplikasi. Setiap perubahan prompt memerlukan edit kode, meningkatkan risiko kesalahan dan menyulitkan versioning. 

Berdasarkan exploration sebelumnya, terdapat **8 prompt** yang perlu diekstraksi:
1. Default System Prompt - identitas AI "ISTA AI"
2. RAG Document Prompt - untuk chat dengan dokumen
3. Web Search Context - untuk informasi real-time dari web
4. Assertive Instruction - petunjuk tambahan saat web search aktif
5. Single Summary - ringkasan dokumen tunggal
6. Partial Summary - ringkasan bagian untuk dokumen besar
7. Final Summary - ringkasan final gabungan
8. Copilot Instructions - aturan implementasi Figma

Tujuan issue ini adalah mengekstraksi semua prompt ke file konfigurasi terpisah agar mudah diedit, dikelola, dan di-track perubahannya.

## 2) Tujuan High-Level
- Memindahkan semua prompt system ke file konfigurasi terpisah yang terpusat.
- Memudahkan user/junior programmer mengganti prompt tanpa edit kode aplikasi.
- Memastikan setiap prompt dapat dikustomisasi secara independen.
- Menyediakan fallback default bila prompt tidak ditemukan di konfigurasi.
- Mendukung versioning dan dokumentasi perubahan prompt.

## 3) Scope High-Level
### Dikerjakan
- Inventarisasi seluruh prompt yang ada saat ini (default, RAG, web search, summary, dll).
- Membuat struktur file konfigurasi prompt yang bersih dan terorganisir.
- Refactor komponen agar membaca prompt dari file konfigurasi, bukan hardcoded di kode.
- Menyediakan mekanisme fallback ke nilai default bila konfigurasi prompt kosong.
- Dokumentasi cara mengedit dan menambahkan prompt baru.

### Tidak Dikerjakan
- Mengubah logika bisnis atau perilaku AI yang dihasilkan oleh prompt.
- Menambahkan fitur prompt engineering baru (hanya ekstraksi dari yang sudah ada).
- Integrasi dengan sistem template eksternal.
- Membuat UI pengelolaan prompt (hanya file konfigurasi, bukan dashboard).

## 4) Rencana Implementasi Bertahap (Fase)
### Fase 1 - Inventarisasi Prompt
- Identifikasi semua prompt di codebase (default, RAG, web search, summarization, dll).
- Catat lokasi saat ini dan fungsi masing-masing prompt.
- Kelompokkan prompt berdasarkan kategori (system utama, RAG, web, summary, dll).

### Fase 2 - Desain Struktur Konfigurasi
- Tentukan format file konfigurasi (JSON, YAML, atau sesuai pola repo).
- Desain struktur keys yang intuitive dan mudah dipahami.
- Siapkan template default yang sesuai dengan prompt saat ini.

### Fase 3 - Ekstraksi ke File Konfigurasi
- Buat file konfigurasi prompt dengan struktur yang dirancang.
- Pindahkan semua prompt dari kode ke file konfigurasi.
- Pastikan tidak ada perubahan perilaku (prompt tetap sama).

### Fase 4 - Refactor Kode
- Ubah komponen agar membaca prompt dari file konfigurasi.
- Implementasi fallback mechanism ke nilai default bila key tidak ada.
- Verifikasi semua alur (chat, RAG, web search, summarization) tetap berfungsi.

### Fase 5 - Validasi & Dokumentasi
- Uji semua skenario untuk memastikan tidak ada regresi.
- Tulis dokumentasi cara mengedit prompt untuk user/junior programmer.
- Sediakan contoh cara menambahkan prompt baru.

## 5) Kriteria Keberhasilan (Acceptance Criteria)
- Semua prompt yang sebelumnya hardcoded dapat dibaca dari file konfigurasi.
- User dapat mengubah prompt dengan mengedit file konfigurasi tanpa menyentuh kode.
- Aplikasi tetap berjalan normal dengan fallback ke default bila prompt kosong.
- Dokumentasi dapat dipahami dan dieksekusi oleh junior programmer.
- Tidak ada perubahan perilaku AI sebelum dan sesudah refactor.
-versi konfigurasi dapat di-track (metadata/tanggal perubahan).

## 6) Risiko & Mitigasi (High-Level)
- Risiko: Prompt berubah无意 karena salah format saat edit.
  Mitigasi: Sediakan validasi format dasar dan template yang jelas.
- Risiko: Aplikasi error karena key prompt tidak ditemukan.
  Mitigasi: Implementasi fallback yang robust ke nilai default.
- Risiko: Prompt penting terlewat saat migrasi.
  Mitigasi: Validasi menyeluruh sebelum cutover dengan checklist semua prompt.
- Risiko: User salah edit struktur kunci.
  Mitigasi: Dokumentasi jelas dan contoh penggunaan yang lengkap.

## 7) Deliverables
- File konfigurasi terpisah untuk semua prompt (dengan struktur yang terorganisir).
- Refactor komponen terkait agar membaca dari konfigurasi.
- Mekanisme fallback default yang solid.
- Dokumen panduan edit prompt untuk user/junior programmer.
- Checklist validasi migrasi prompt (before/after).

## 8) Saran Struktur File Konfigurasi (High-Level)
Contoh struktur logis (mengacu pada 8 prompt yang sudah diinventarisasi):

```
prompts:
  system:
    default: "Prompt identitas AI 'ISTA AI' - system utama"
    copilot: "Aturan implementasi Figma"
  
  rag:
    document: "Prompt untuk chat dengan dokumen"
  
  web_search:
    context: "Prompt untuk informasi real-time dari web"
    assertive_instruction: "Petunjuk tambahan saat web search aktif"
  
  summarization:
    single: "Ringkasan dokumen tunggal"
    partial: "Ringkasan bagian untuk dokumen besar (multi-batch)"
    final: "Ringkasan final gabungan"

metadata:
  versi: "1.0"
  tanggal: "2026-04-12"
  catatan: "8 prompt awal yang diekstraksi dari kode"
```

Disarankan disimpan di folder konfigurasi aplikasi (mis. `config/prompts.yaml` atau `config/prompts.json` di folder python-ai).

## 9) Checklist Eksekusi Ringkas
- [ ] Identifikasi dan catat semua prompt yang ada di codebase saat ini.
- [ ] Tentukan format dan struktur file konfigurasi prompt.
- [ ] Buat file konfigurasi dengan semua prompt default.
- [ ] Refactor komponen agar membaca prompt dari file konfigurasi.
- [ ] Implementasi fallback mechanism ke default.
- [ ] Uji semua alur (chat, RAG, web, summarization).
- [ ] Tulis dokumentasi cara edit prompt untuk user/junior programmer.
- [ ] Review akhir: pastikan semua prompt ter-cover dan tidak ada regresi.

(End of file - total 92 lines)