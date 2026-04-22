# Issue Plan: Bangun AI Boundary Laravel, Feature Flag, dan Shadow Mode

## Latar Belakang
Issue ini adalah turunan dari issue `#67` setelah fondasi platform siap. Saat ini Laravel berbicara ke `python-ai` lewat boundary HTTP internal pada `AIService`, `ProcessDocument`, dan `DocumentLifecycleService`. Jika migrasi dilakukan langsung per file tanpa boundary baru, logic lama dan baru akan bercampur dan sulit diverifikasi.

Sebelum memindahkan capability satu per satu, aplikasi perlu memiliki boundary internal Laravel yang dapat:

- merutekan request ke runtime lama atau runtime baru
- menjalankan shadow mode tanpa mengubah respons user
- mengumpulkan metadata untuk evaluasi parity

## Tujuan
- Membangun boundary service Laravel yang menjadi pengganti bertahap untuk boundary HTTP ke Python.
- Menyediakan feature flag per capability untuk memilih runtime lama vs runtime baru.
- Menyediakan shadow mode/canary agar parity bisa diverifikasi sebelum cutover.

## Ruang Lingkup
- Definisi contract internal Laravel untuk:
  - chat
  - document process
  - document summarize
  - document delete cleanup
- Pembuatan adapter runtime lama yang tetap memanggil Python agar caller lama tidak pecah.
- Pembuatan kerangka runtime baru berbasis Laravel service/agent tanpa harus memigrasikan semua capability sekaligus.
- Penambahan feature flag dan logging/telemetry minimum untuk membandingkan hasil runtime lama vs baru.

## Di Luar Scope
- Migrasi capability chat/web search atau dokumen secara penuh.
- Cutover final ke runtime baru.
- Penghapusan Python container.
- Refactor UI chat atau halaman dokumen yang tidak terkait boundary.

## Area / File Terkait
- `laravel/app/Services/AIService.php`
- `laravel/app/Jobs/ProcessDocument.php`
- `laravel/app/Services/DocumentLifecycleService.php`
- `laravel/app/Livewire/Chat/ChatIndex.php`
- `laravel/app/Services/ChatOrchestrationService.php`
- `laravel/config/services.php`
- config/feature flag baru untuk runtime AI

## Risiko
- Boundary baru terlalu abstrak dan justru mempersulit implementasi.
- Shadow mode menambah biaya provider jika tidak dibatasi.
- Perbandingan hasil lama vs baru menjadi tidak berguna jika metadata/log tidak distandarkan.
- Caller existing bocor pengetahuan implementation detail yang seharusnya masuk ke boundary.

## Langkah Implementasi
1. Definisikan interface internal untuk operasi AI utama yang saat ini dipakai Laravel.
2. Buat adapter runtime lama yang membungkus flow existing ke Python.
3. Buat runtime resolver berbasis feature flag per capability.
4. Tambahkan shadow mode:
   - user tetap menerima respons runtime utama
   - runtime sekunder dieksekusi hanya untuk evaluasi yang aman
5. Tentukan format metadata parity yang dikumpulkan:
   - latency
   - source summary
   - status sukses/gagal
   - ringkasan drift
6. Rapikan caller Laravel agar bergantung ke boundary baru, bukan langsung ke implementation runtime tertentu.

## Rencana Test
- Unit test resolver runtime berdasarkan feature flag.
- Feature test bahwa caller Laravel tetap bekerja saat runtime lama dipakai penuh.
- Feature test bahwa shadow mode tidak mengubah respons user-facing.
- Test metadata/logging minimum tercatat untuk evaluasi parity.

## Kriteria Selesai
- Laravel memiliki boundary AI internal yang stabil dan dapat dipakai issue migrasi berikutnya.
- Runtime lama dan runtime baru dapat dipilih melalui feature flag.
- Shadow mode tersedia untuk capability yang akan dimigrasikan.
- Caller utama tidak lagi hard-coupled langsung ke HTTP Python implementation detail.
