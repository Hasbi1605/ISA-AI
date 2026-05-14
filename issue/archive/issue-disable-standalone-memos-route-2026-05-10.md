# Disable Standalone `/memos` Pages

## Tujuan
Memastikan semua akses halaman memo standalone di `/memos` diarahkan ke workspace memo baru di `/chat?tab=memo`, karena pengelolaan memo saat ini sudah dipusatkan di `https://ista-ai.app/chat?tab=memo`.

## Scope
- Nonaktifkan halaman user-facing `/memos`, `/memos/create`, dan `/memos/{memo}` dengan redirect ke route `chat` tab `memo`.
- Pindahkan endpoint teknis memo yang masih dipakai workspace baru dari `/memos/...` ke `/chat/memos/...`, seperti download, export PDF, dan signed file OnlyOffice.
- Pertahankan callback OnlyOffice yang tidak berada di `/memos`.
- Sesuaikan test rute agar perilaku redirect baru terdokumentasi.

## Risiko
- Route name teknis tetap `memos.*` agar referensi internal tidak perlu refactor besar, tetapi path publiknya tidak lagi di bawah `/memos`.
- Jika target akhirnya ingin menghapus seluruh backend memo lama, perlu fase lanjutan yang lebih besar karena chat memo masih memakai model dan service memo.

## Verifikasi
- Jalankan test Laravel relevan untuk dashboard, route memo, dan policy/file memo.
