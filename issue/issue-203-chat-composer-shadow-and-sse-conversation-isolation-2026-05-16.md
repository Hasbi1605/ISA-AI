# Fix Chat Composer Shadow And SSE Conversation Isolation

## Latar Belakang
Ada dua bug UI setelah live SSE aktif:
- Shadow pada chat composer hilang ketika textarea fokus.
- Jika user mengirim request di chat A lalu pindah ke chat B, output request chat A dapat muncul di chat B.

## Tujuan
- Composer tetap memiliki shadow saat textarea fokus.
- SSE stream tetap boleh selesai di background agar jawaban tersimpan.
- Chunk/output/final content dari stream hanya dirender pada conversation asalnya.
- Pending/sidebar tetap diperbarui saat stream selesai di conversation yang sedang tidak dilihat.

## Ruang Lingkup
- CSS reset focus composer.
- State EventSource di `chat-page.js`.
- Kontrak refresh pending conversation Livewire.
- Test regresi untuk CSS dan inactive conversation refresh.

## Di Luar Scope
- Perubahan model, retrieval, prompt, atau kualitas jawaban.
- Refactor besar UI chat.
- Mengubah desain visual composer selain mempertahankan shadow yang sudah ada.

## Area / File Terkait
- `laravel/resources/css/app.css`
- `laravel/resources/js/chat-page.js`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/tests/Feature/Chat/ChatUiTest.php`

## Risiko
- Menutup EventSource saat pindah chat dapat membatalkan stream dan membuat jawaban hilang; karena itu stream harus tetap berjalan.
- Jika event SSE tidak di-scope dengan benar, UI chat aktif bisa kembali menerima output conversation lain.
- Jika refresh pending dipanggil untuk inactive conversation, jangan sampai Livewire memuat conversation tersebut ke layar aktif.

## Langkah Implementasi
1. Hapus `.chat-form:focus-within` dari reset shadow global.
2. Ubah state EventSource menjadi map per conversation.
3. Close/reopen stream hanya untuk conversation yang sama, bukan semua stream.
4. Guard semua event SSE dengan active conversation check sebelum merender output.
5. Tetap panggil `refreshPendingChatState` saat stream selesai agar pending/sidebar bersih.
6. Reset streaming UI hanya untuk `assistant-message-persisted` milik conversation aktif.
7. Tambahkan test regresi CSS dan inactive pending refresh.

## Rencana Test
- `php artisan test --filter='chat_page_renders_multiline|refresh_pending_chat_state'`
- `php artisan test tests/Feature/Chat/ChatUiTest.php tests/Feature/Chat/ChatStreamTest.php`
- `npm run build`
- `npm audit --audit-level=high`
- Full Laravel test sebelum deploy.

## Kriteria Selesai
- Composer shadow tidak hilang saat fokus.
- Output stream chat A tidak muncul di chat B.
- Jawaban chat A tetap tersimpan dan chat A/sidebar tetap update setelah stream selesai.
- Verifikasi relevan lulus.
