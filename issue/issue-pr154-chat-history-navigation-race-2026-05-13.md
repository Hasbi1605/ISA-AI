# Issue: Stabilkan Navigasi History Chat PR 154

## Latar Belakang
Sidebar history chat PR 154 sudah memakai bucket waktu, search, indikator pending, dan tombol `Lihat semua` / `Ringkas`. Namun user melaporkan bug intermittent: sidebar terlihat pindah ke chat lain tetapi panel tengah tidak berubah, beberapa chat pending tidak selalu menampilkan loading di sidebar, klik history kadang tidak berpindah, dan label tombol buka/tutup semua kadang berubah sendiri.

## Tujuan
- Menjaga user tetap bebas pindah history atau membuat chat baru saat AI memproses request di background.
- Membuat state aktif sidebar mengikuti chat yang benar-benar berhasil dimuat di panel tengah.
- Membuat indikator pending sidebar tetap akurat meski Livewire melakukan re-render/polling.
- Menjadikan `Lihat semua` / `Ringkas` konsisten dengan kondisi section yang benar-benar terbuka.
- Menghapus total chat per kategori dari UI.

## Ruang Lingkup
1. Tambahkan guard navigasi client-side agar response Livewire lama tidak menimpa state klik terbaru.
2. Tambahkan fallback route navigation saat load conversation gagal agar klik history tetap berpindah.
3. Sinkronkan pending conversation dari server ke Alpine melalui event Livewire.
4. Satukan state buka/tutup semua section ke `openHistorySections`, termasuk persist ringan di localStorage.
5. Hapus count kategori dari label sidebar.
6. Update test feature chat untuk mengunci perilaku dan hook baru.

## Di Luar Scope
- Tidak mengubah queue/background job AI.
- Tidak memblokir user berpindah chat saat jawaban masih diproses.
- Tidak mengubah desain besar sidebar, bucket waktu, atau search history.
- Tidak deploy production kecuali diminta eksplisit setelah patch.

## Area / File Terkait
- `laravel/resources/js/chat-page.js`
- `laravel/resources/views/livewire/chat/partials/chat-left-sidebar.blade.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/tests/Feature/Chat/ChatUiTest.php`

## Risiko
- Livewire/Alpine re-render dapat membuat state client reset jika tidak dipersist dengan hati-hati.
- Pending indicator harus merge state server dan local marker tanpa membuat spinner palsu terlalu lama.
- Navigasi async perlu menjaga URL, active state, dan loading state tetap konsisten.

## Langkah Implementasi
1. Tambahkan storage key dan helper untuk section history.
2. Refactor `chatHistory` agar `showAllHistory` diganti dengan computed state dari `openHistorySections`.
3. Tambahkan `navigationToken` untuk mengabaikan response load conversation yang sudah stale.
4. Dispatch event pending state dari Livewire setelah load/send/refresh conversations.
5. Dengar event pending state di Alpine dan sinkronkan `pendingConversationIds`.
6. Hapus count kategori dari Blade.
7. Update test yang sudah ada dan tambahkan assertion untuk hook baru.

## Rencana Test
- Jalankan `php artisan test tests/Feature/Chat/ChatUiTest.php`.
- Jalankan `npm run build`.
- Jalankan `git diff --check`.

## Kriteria Selesai
- User dapat tetap berpindah history/chat baru saat AI sedang memproses jawaban.
- Sidebar dan panel tengah tidak lagi mudah berbeda state akibat request lama.
- Pending spinner di sidebar lebih konsisten.
- Tombol `Lihat semua` / `Ringkas` stabil dan sesuai section yang terbuka.
- Count kategori tidak tampil di UI.
- Test relevan dan build frontend lulus.
