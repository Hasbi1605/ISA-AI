# PR 152 - Background Chat Requests

## Latar Belakang
Saat user mengirim pesan lalu berpindah ke history chat lain atau New Chat, tampilan chat aktif masih dapat tertahan di loading bubble request sebelumnya. Ini terjadi karena proses AI masih dijalankan sebagai request Livewire panjang pada komponen chat yang sama.

## Tujuan
- User dapat berpindah antar history chat atau New Chat saat request AI masih berjalan.
- Request yang berjalan tetap terlihat sebagai loading kecil di sidebar kiri pada conversation asal.
- New Chat yang dikirim langsung membuat item history baru sebelum jawaban AI selesai.
- Conversation yang sedang diproses tetap menampilkan loading bubble saat dibuka kembali sampai jawaban selesai.

## Ruang Lingkup
- Ubah pengiriman pesan chat agar request AI diproses sebagai pekerjaan background.
- Simpan user message dan conversation secara cepat sebelum proses AI dimulai.
- Tambahkan pending state per conversation untuk sidebar dan polling ringan.
- Refresh conversation aktif ketika jawaban background sudah tersimpan.

## Di Luar Scope
- Streaming token realtime lintas tab/history.
- Cancel request AI.
- Multi-request paralel dalam conversation yang sama.
- Perubahan besar desain UI chat.

## Area / File Terkait
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/app/Jobs/GenerateChatResponse.php`
- `laravel/resources/js/chat-page.js`
- `laravel/resources/views/livewire/chat/partials/chat-left-sidebar.blade.php`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`
- `laravel/resources/views/livewire/chat/chat-index.blade.php`
- `laravel/tests/Feature/Chat/ChatUiTest.php`

## Risiko
- Perlu memastikan queue production memproses job chat cukup lama untuk AI timeout.
- Polling harus ringan dan hanya aktif saat ada conversation pending.
- Pending lama akibat job gagal harus diselesaikan dengan assistant error message agar spinner tidak menggantung.

## Langkah Implementasi
1. Tambahkan job background untuk memanggil AI service dan menyimpan assistant message ke conversation asal.
2. Ubah `sendMessage` agar hanya membuat conversation/user message, dispatch job, reload history, dan selesai cepat.
3. Tambahkan pending conversation ids berdasarkan latest message role user.
4. Tambahkan polling saat ada pending conversation untuk refresh sidebar dan conversation aktif.
5. Sesuaikan JS sidebar agar spinner pending mengikuti event per conversation.
6. Pastikan bubble loading dipulihkan saat membuka conversation pending.

## Rencana Test
- Test `sendMessage` membuat conversation/user message, menandai pending, dan dispatch job.
- Test user tetap bisa `loadConversation` lain setelah `sendMessage`.
- Test refresh pending memuat assistant message ketika job selesai.
- Test render sidebar memiliki binding pending spinner.
- Jalankan build frontend dan test Laravel terkait chat.

## Kriteria Selesai
- Chat aktif bisa berpindah tanpa menunggu response AI.
- Sidebar menampilkan spinner pada conversation yang masih menunggu jawaban.
- New Chat langsung muncul di history saat prompt dikirim.
- Conversation pending menampilkan loading bubble saat dibuka.
- Verifikasi relevan berhasil.
