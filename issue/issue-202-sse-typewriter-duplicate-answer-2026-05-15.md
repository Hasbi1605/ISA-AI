# Fix SSE Typewriter Duplicate Answer

## Latar Belakang
Setelah live SSE aktif, jawaban bisa tampil dua kali: teks hasil stream muncul lebih dulu, lalu setelah pesan tersimpan dan Livewire refresh, pesan database yang sama dianimasikan ulang lewat typewriter final. Selain itu bubble streaming memakai tampilan plain text yang berbeda dari bubble jawaban final.

## Tujuan
- Pertahankan efek typewriter untuk jawaban baru.
- Hilangkan replay ganda setelah pesan SSE sudah tersimpan.
- Samakan tampilan bubble streaming dengan bubble jawaban final saat ini.
- Render markdown streaming secara aman agar tidak tampil sebagai markdown mentah sebelum pesan final tersimpan.
- Sembunyikan badge nama model pada bubble streaming agar konsisten dengan pesan final.
- Percepat rasa typewriter agar streaming tetap terasa responsif.

## Ruang Lingkup
- Jalur UI chat Laravel/Livewire/Alpine untuk streaming jawaban.
- Kontrak `refreshPendingChatState` agar bisa tahu message id yang sudah ditampilkan lewat SSE.
- Test regresi untuk memastikan polling fallback tetap memakai typewriter, tetapi SSE tidak replay.

## Di Luar Scope
- Perubahan retrieval, ranking, prompt, atau kualitas jawaban AI.
- Refactor besar komponen chat.
- Penambahan library markdown client-side baru.

## Area / File Terkait
- `laravel/resources/js/chat-page.js`
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`
- `laravel/app/Http/Controllers/Chat/ChatStreamController.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/tests/Feature/Chat/ChatUiTest.php`
- `laravel/package.json`
- `laravel/package-lock.json`

## Risiko
- Jika suppress `newMessageId` terlalu agresif, fallback polling/background job bisa kehilangan typewriter.
- Jika streaming reset terlalu cepat setelah `done`, typewriter SSE bisa terpotong sebelum semua chunk tampil.
- Renderer markdown client-side harus tetap disanitasi agar tidak membuka XSS dari output AI.

## Langkah Implementasi
1. Tambahkan state message id hasil SSE di Alpine.
2. Ubah append chunk menjadi antrean typewriter cepat.
3. Tunda refresh Livewire sampai antrean typewriter selesai.
4. Kirim id pesan yang sudah distream ke `refreshPendingChatState`.
5. Suppress `newMessageId` hanya ketika latest assistant id sama dengan id yang sudah distream.
6. Samakan class bubble streaming dengan class bubble jawaban final.
7. Render `streamingText` menjadi HTML markdown yang disanitasi sebelum ditampilkan.
8. Kirim `final-content` dari SSE setelah footer sumber ditambahkan agar stream dan final DB tidak berbeda.
9. Sembunyikan badge model saat streaming.
10. Tambahkan test regresi untuk jalur SSE dan pastikan fallback polling tetap ada.

## Rencana Test
- Jalankan subset `ChatUiTest` terkait `refreshPendingChatState`, typewriter marker, dan render chat.
- Jalankan test Laravel relevan setelah patch.
- Jalankan build asset bila JavaScript/Blade berubah.

## Kriteria Selesai
- Jawaban SSE tidak replay sebagai typewriter kedua setelah refresh.
- Jawaban fallback non-SSE tetap ditandai `newMessageId` dan dianimasikan.
- Streaming bubble memakai tampilan yang konsisten dengan jawaban final.
- Verifikasi relevan lulus.
