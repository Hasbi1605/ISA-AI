# Dynamic Chat Loading Animation

## Latar Belakang
Loading bubble chat saat ini berupa tiga titik `animate-bounce` statis ketika jawaban AI belum mulai streaming. Pengguna meminta indikator tersebut dibuat lebih dinamis dan kontekstual berdasarkan mode penggunaan: dokumen, web, chat biasa, dan revisi memo.

## Tujuan
- Mengganti loading bubble tiga titik agar lebih eye-catching namun tetap halus dan tidak berlebihan.
- Menampilkan status proses yang sesuai konteks sebelum jawaban muncul.
- Mempertahankan alur streaming jawaban yang sudah ada tanpa mengubah backend AI.

## Ruang Lingkup
- Area chat utama di `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`.
- State frontend Alpine di `laravel/resources/js/chat-page.js` untuk menentukan konteks loading dari event pengiriman pesan / mode aktif.
- Integrasi ringan dengan form composer / memo chat bila diperlukan untuk mengirim konteks loading.
- Styling animasi CSS/Tailwind inline yang kompatibel dengan dark mode.

## Di Luar Scope
- Mengubah logic backend AI, prompt, retrieval, atau API Python.
- Mengubah hasil jawaban, sources, atau penyimpanan pesan.
- Redesign besar layout chat/memo.

## Area / File Terkait
- `laravel/resources/views/livewire/chat/partials/chat-messages.blade.php`: render bubble loading dan streaming.
- `laravel/resources/js/chat-page.js`: Alpine data/event untuk chat, composer, memo, dan helper loading context.
- `laravel/resources/views/livewire/chat/partials/chat-composer.blade.php`: kemungkinan sumber mode web/dokumen/attachment.
- `laravel/resources/views/livewire/memos/partials/memo-chat.blade.php`: kemungkinan sumber konteks revisi memo.

## Risiko
- Konteks mode salah terdeteksi jika event pengiriman pesan tidak membawa detail yang cukup.
- Animasi terlalu ramai atau mengganggu readability bila tidak dibatasi.
- Perubahan frontend harus tetap aman untuk Livewire/Alpine re-render.

## Langkah Implementasi
1. Petakan event `message-send` dan form submit yang sudah ada untuk mengetahui data mode/dokumen/memo yang tersedia.
2. Tambahkan helper Alpine untuk menentukan urutan label loading:
   - dokumen/attachment: `Sedang membaca dokumen` → `AI sedang berpikir` → `Menampilkan jawaban`
   - web: `Mencari jawaban` → `AI sedang berpikir` → `Menampilkan jawaban`
   - chat biasa: `AI sedang berpikir` → `Menampilkan jawaban`
   - memo revisi: `Membuat ulang memo` → `AI sedang berpikir` → `Menampilkan jawaban`
3. Saat streaming dimulai tanpa teks, tampilkan bubble baru dengan tiga titik yang berputar/pulse dan label aktif.
4. Saat teks pertama mulai diterima, ubah label singkat menjadi `Menampilkan jawaban` sebelum konten streaming tampil.
5. Pastikan dark mode, aksesibilitas `aria-live`, dan fallback normal chat berjalan.

## Rencana Test
- Jalankan `npm run build` dari folder `laravel`.
- Jalankan test Laravel relevan / full `php artisan test` bila feasible.
- Jalankan `git diff --check`.
- Setelah PR deploy, lakukan browser QA pada `https://ista-ai.app` untuk chat biasa, mode web, dokumen/attachment, dan memo revision bila kredensial/akses tersedia.

## Kriteria Selesai
- Loading bubble baru tampil dinamis dan kontekstual sesuai mode.
- Jawaban tetap streaming seperti sebelumnya.
- Build/test relevan lulus.
- PR dibuat, preview terdeploy, dan QA/review tidak menemukan blocker sebelum meminta approval merge.
