# Issue: DigitalOcean Deployment untuk Stack Hybrid Python + Laravel

## Latar Belakang

`main` saat ini sudah kembali ke baseline hybrid Python + Laravel yang stabil untuk demo dan operasi awal. Repo sudah memiliki `docker-compose.yml`, tetapi jalur deploy produksi belum benar-benar siap karena:

- `laravel/Dockerfile` belum ada
- compose saat ini masih terlalu bercampur dengan asumsi local development
- belum ada reverse proxy publik yang jelas
- belum ada panduan deploy DigitalOcean yang eksplisit untuk branch hybrid

Targetnya adalah menyiapkan jalur deploy yang aman dan realistis untuk satu droplet DigitalOcean dengan biaya rendah, tanpa memaksa migrasi ke Laravel-only.

## Fakta Codebase

- Laravel memanggil Python AI service melalui `AI_SERVICE_URL`.
- `laravel/bootstrap/app.php` sudah trust proxy, jadi aman berada di belakang reverse proxy.
- `python-ai/Dockerfile` sudah ada dan cukup dekat ke kebutuhan runtime produksi.
- `laravel/.env.production` sudah mendokumentasikan environment produksi, tetapi masih mengasumsikan service Docker tanpa file deploy yang lengkap.
- Root `docker-compose.yml` saat ini mereferensikan `laravel/Dockerfile` yang belum ada.

## Asumsi

- Deploy target adalah **1 droplet** DigitalOcean.
- Domain akan diarahkan ke droplet dan TLS diterminasi di reverse proxy publik.
- Traffic awal relatif kecil sampai sedang, sehingga pendekatan single-node masih memadai.
- Kita menyiapkan jalur deploy yang praktis dan stabil, bukan platform HA atau autoscaling.

## Tujuan

1. Menyediakan file deploy produksi yang benar-benar bisa dijalankan.
2. Memisahkan concern development lokal dari deployment DigitalOcean.
3. Menjaga arsitektur hybrid: Laravel publik, Python internal.
4. Menyediakan dokumentasi deploy langkah demi langkah yang bisa langsung dipakai.

## Rencana Implementasi

1. Tambah file Docker untuk Laravel production image.
2. Tambah file compose khusus produksi/DigitalOcean.
3. Tambah reverse proxy config untuk expose aplikasi ke internet.
4. Tambah `.dockerignore` agar build context tidak membawa artefak lokal.
5. Tambah dokumentasi deploy DigitalOcean yang sesuai dengan repo ini.
6. Verifikasi sintaks compose/build secara lokal.

## Risiko

- Laravel di repo ini belum memiliki jalur container produksi bawaan, jadi entrypoint perlu dibuat hati-hati.
- Build context repo berpotensi besar karena ada artefak lokal; `.dockerignore` wajib benar.
- Jika TLS/domain belum siap saat deploy, aplikasi mungkin hanya bisa diakses via IP sementara.

## Done When

- Ada file deploy produksi yang valid untuk stack hybrid.
- Laravel image bisa dibuild dari repo ini.
- Compose produksi lolos validasi konfigurasi.
- Dokumentasi deploy DigitalOcean tersedia dan sesuai dengan file deploy yang dibuat.
