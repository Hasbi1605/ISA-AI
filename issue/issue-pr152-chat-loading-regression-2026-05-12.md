# PR 152 Chat Loading Regression Follow-up

## Masalah
- Navigasi history chat di PR 152 memakai full page navigation agar tidak tertahan request Livewire yang sedang streaming, tetapi global page loader ikut tampil besar di tengah halaman.
- Saat user kembali ke conversation yang masih memproses request AI, bubble loading assistant bisa hilang sampai jawaban selesai tersimpan.

## Tujuan
- Pertahankan loading navigasi history tetap minimal: spinner kecil di item history yang diklik dan cursor/progress browser, tanpa overlay loading global.
- Saat membuka kembali conversation yang request AI-nya masih berjalan, tampilkan lagi bubble loading assistant selama pesan user terakhir belum punya jawaban assistant.
- Jaga perubahan tetap kecil dan tidak mengubah arsitektur streaming/chat utama.

## Akar Masalah Dugaan
- Flag `window.__suppressGlobalPageLoaderOnce` hanya hidup di halaman asal, sehingga halaman tujuan tetap merender `x-page-loader` dalam keadaan terlihat.
- Marker pending chat hanya disimpan setelah event `user-message-acked`; bila user pindah halaman sebelum event itu diterapkan di browser, halaman yang dibuka ulang tidak punya marker untuk memulihkan bubble loading.

## Rencana Implementasi
1. Tambahkan suppress flag berbasis `sessionStorage` untuk global page loader, supaya full navigation dari history chat tetap tidak menampilkan overlay besar di halaman tujuan.
2. Simpan marker pending lebih awal untuk conversation yang sudah punya ID ketika user menekan kirim.
3. Tambahkan fallback restore bubble untuk conversation yang punya pesan user terbaru tanpa assistant dan masih dalam rentang waktu request normal.
4. Tambahkan metadata waktu pesan user terakhir pada partial chat messages.
5. Update test UI/loader yang relevan.

## Verifikasi
- Jalankan test Laravel yang dekat dengan perubahan:
  - `php artisan test --filter=PageLoaderComponentTest`
  - `php artisan test --filter=ChatUiTest`
- Jalankan `git diff --check`.

## Risiko
- Fallback berbasis waktu bisa menampilkan bubble loading pada pesan user yang gagal diproses jika masih sangat baru. TTL dibuat terbatas agar tidak menampilkan loading lama secara permanen.

## Follow-up 2: Blink Saat Pindah History

### Masalah
Setelah global loader besar ditekan, pindah antar history chat tetap terasa blink karena klik history selalu memakai full page navigation. Sebelum PR ini, perpindahan history saat idle memakai action Livewire sehingga tidak reload satu halaman penuh.

### Rencana
- Kembalikan jalur idle ke `loadConversation()` / `startNewChat()` agar perpindahan history tidak membuat layar blink.
- Simpan fallback full navigation hanya ketika ada request chat aktif (`message-send` belum `message-complete`), karena jalur itu diperlukan agar navigasi tidak tertahan request streaming.
- Tambahkan assertion UI agar perilaku hybrid ini tidak hilang.
