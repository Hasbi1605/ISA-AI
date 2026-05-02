# Changelog Tahap 5

Tahap 5 fokus pada stabilitas ingest dokumen panjang dan pengurangan regresi pada jalur RAG.

## Yang Diubah

- Chunking dokumen dipindah ke pendekatan token-aware agar batas pemrosesan lebih akurat.
- Batching ingest dibuat lebih agresif supaya dokumen besar selesai lebih cepat.
- Fallback embedding disusun bertingkat agar rate limit tidak langsung mematahkan proses upload.
- Dokumentasi operasional tahap 5 dipusatkan di sini agar tautan README tetap valid.

## Dampak Runtime

- Dokumen panjang lebih stabil diproses.
- Perubahan model embedding tetap mengikuti konfigurasi `python-ai/config/ai_config.yaml`.
- Behavior existing tetap dipertahankan, hanya jalur ingest dan dokumentasinya yang dirapikan.
