# Production Maintenance: RAM, Disk, dan Log

Dokumen ini berisi checklist maintenance aman untuk droplet production ISTA AI. Fokusnya observasi dan cleanup non-destruktif.

## Prinsip Aman

- Jangan menjalankan `docker volume prune` tanpa backup dan tanpa tahu volume yang akan terhapus.
- Jangan menghapus volume `mysql_data`, `laravel_storage`, atau `chroma_data` kecuali memang sedang restore/rebuild terencana.
- Backup `.env.droplet` dan data penting sebelum cleanup besar.
- Jalankan command inspeksi dulu, baru cleanup.

## Cek Status Service

```bash
cd /opt/ista-ai
docker compose --env-file .env.droplet -f docker-compose.production.yml ps
docker compose --env-file .env.droplet -f docker-compose.production.yml top
```

## Cek RAM

```bash
free -h
docker stats --no-stream
swapon --show
```

Catatan:

- OnlyOffice normalnya memakai RAM cukup besar dibanding service lain.
- Python docs dapat naik saat parsing/embedding dokumen.
- Swap adalah safety net, bukan pengganti RAM.

## Cek Disk

```bash
df -h
docker system df
docker volume ls
du -h --max-depth=1 /opt/ista-ai 2>/dev/null | sort -h
```

## Cleanup Docker yang Relatif Aman

Hapus build cache lama:

```bash
docker builder prune
```

Hapus image dangling:

```bash
docker image prune
```

Hapus container yang sudah berhenti:

```bash
docker container prune
```

Lebih hati-hati dengan command ini karena menghapus image yang tidak sedang dipakai:

```bash
docker image prune -a
```

Jangan jalankan ini tanpa backup dan review:

```bash
docker volume prune
```

## Cek Log

Laravel:

```bash
docker compose --env-file .env.droplet -f docker-compose.production.yml logs --tail=200 laravel
```

Python AI:

```bash
docker compose --env-file .env.droplet -f docker-compose.production.yml logs --tail=200 python-ai
docker compose --env-file .env.droplet -f docker-compose.production.yml logs --tail=200 python-ai-docs
```

OnlyOffice:

```bash
docker compose --env-file .env.droplet -f docker-compose.production.yml logs --tail=200 onlyoffice
```

Caddy:

```bash
docker compose --env-file .env.droplet -f docker-compose.production.yml logs --tail=200 caddy
```

## Cleanup Journal Systemd

Cek ukuran journal:

```bash
journalctl --disk-usage
```

Kurangi log lama, misalnya simpan 7 hari:

```bash
sudo journalctl --vacuum-time=7d
```

## Backup Minimum Sebelum Cleanup Besar

Backup database:

```bash
docker compose --env-file .env.droplet -f docker-compose.production.yml exec mysql \
  sh -lc 'mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > ista-ai-mysql-$(date +%F).sql
```

Backup env:

```bash
cp .env.droplet .env.droplet.bak.$(date +%Y%m%d%H%M%S)
chmod 600 .env.droplet .env.droplet.bak.*
```

Untuk backup volume besar seperti `laravel_storage` dan `chroma_data`, gunakan snapshot droplet atau backup volume terencana.
