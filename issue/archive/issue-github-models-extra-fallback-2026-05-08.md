# GitHub Models Extra Chat Fallback

## Latar Belakang

Katalog GitHub Models menyediakan beberapa model tier rendah/menengah yang masih bisa diakses walaupun model GPT utama sempat terkena 429. Saat ini chain chat langsung berpindah dari GPT-4.1/4o ke Groq dan Bedrock, sehingga kesempatan memakai fallback GitHub Models dari dua akun belum dimanfaatkan.

## Tujuan

- Menambahkan fallback GitHub Models sebelum Bedrock.
- Memakai dua API key GitHub Models (`GITHUB_TOKEN` dan `GITHUB_TOKEN_2`) untuk setiap model fallback yang dipilih.
- Menjaga Bedrock tetap sebagai fallback akhir.

## Rencana Implementasi

1. Tambahkan GPT-4.1 Mini dan GPT-4.1 Nano, masing-masing dengan token primary dan backup, setelah GPT-4o.
2. Pertahankan Groq sebagai fallback kuat setelah model OpenAI GitHub.
3. Tambahkan Mistral Medium 3 dan Mistral Small 3.1, masing-masing dengan token primary dan backup, sebelum Bedrock.
4. Tambahkan test kontrak urutan fallback agar config tidak bergeser tanpa sengaja.
5. Jalankan verifikasi Python relevan dan full pytest.

## Risiko

- Jika limit GitHub Models terjadi pada level akun/IP global, semua fallback GitHub tetap bisa gagal dan sistem akan lanjut ke Groq/Bedrock.
- Model ringan seperti GPT-4.1 Nano lebih cocok sebagai fallback operasional, bukan kualitas utama untuk memo final yang kompleks.
