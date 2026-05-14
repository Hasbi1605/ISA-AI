# Roadmap: 4 Fitur Baru ISTA AI (Output Multi-Format, Document Viewer Sidebar, Canvas Memo, Google Drive)

## Latar Belakang
Mentor proyek ISTA AI meminta empat penambahan fitur besar yang belum ada di repo:

1. **Output multi-format**: dokumen sumber (mis. PDF berisi tabel) bisa diekstrak dan diekspor ke Excel/CSV/DOCX/PDF.
2. **Document viewer di sidebar**: user bisa membaca isi dokumen yang di-upload langsung di dalam ISTA AI tanpa download manual.
3. **Canvas memo (Word-like editor)**: AI generate body memo lengkap (bukan hanya kop/heading), user edit di editor mirip Microsoft Word, simpan sebagai DOCX.
4. **Integrasi Google Drive**: pegawai bisa langsung memproses file dari Drive tanpa download-upload manual (Tahap 7 di Issue #1).

Issue #1 di repo sudah mencantumkan rencana Tahap 6 (Generate Draft Dokumen) dan Tahap 7 (Integrasi Google Drive / OneDrive via MCP), tetapi keduanya **belum dimulai sama sekali**.

## Keputusan Desain (sudah dikonfirmasi user)

| Pertanyaan | Keputusan |
|---|---|
| Editor Word-like untuk Canvas | **OnlyOffice Document Server (Community Edition, AGPL, gratis)** — embed via iframe, ribbon penuh, native DOCX |
| Urutan implementasi | **C → A → B → D** (viewer dulu sebagai quick win, canvas sebelum Drive) |
| Cloud storage scope | **Google Drive saja** untuk MVP; OneDrive fase berikutnya bila tetap diperlukan |
| Pendekatan integrasi cloud | **Direct Google API** (tanpa MCP wrapper di MVP); MCP dapat dibungkus belakangan |
| Planning approach | Tulis 4 issue markdown di `issue/` sebelum implementasi (sesuai `AGENTS.md`) |

## Urutan Fase + Estimasi

| Fase | Fitur | Issue Markdown | Estimasi |
|---|---|---|---|
| 1 | C — Document Viewer di Sidebar | `issue-document-viewer-sidebar-2026-04-30.md` | 3–5 hari |
| 2 | A — Output Multi-Format | `issue-multi-format-export-2026-04-30.md` | 4–6 hari |
| 3 | B — Canvas Memo Editor (OnlyOffice) | `issue-canvas-memo-onlyoffice-2026-04-30.md` | 6–9 hari |
| 4 | D — Google Drive Integration | `issue-google-drive-integration-2026-04-30.md` | 5–7 hari |

Total: ~20–27 hari kerja sequential, atau ~3–4 minggu kalua ada paralelisme antara tim Python & Laravel.

## Dependensi Antar-Fase

- **Fase 1** independen.
- **Fase 2** bisa berdiri sendiri, tapi tombol "Export" terpasang di viewer Fase 1, jadi paling rapi setelah Fase 1.
- **Fase 3** menggunakan endpoint export PDF dari Fase 2 (reuse). Bisa start paralel dengan Fase 2 di bagian Python jika resource cukup.
- **Fase 4** independen secara teknis tapi di-akhir karena perubahan paling tersentralisasi (auth, ingest pipeline) — risikonya rendah jika fase 1–3 sudah stabil.

## Aturan Kerja Per Fase
Mengikuti `AGENTS.md`:
1. PR kecil-kecil per langkah dalam issue (bukan satu PR raksasa per fase).
2. Test dulu sebelum/segera setelah implementasi (TDD jika feasible).
3. Verifikasi: `php artisan test` (Laravel) dan `pytest` di venv (Python).
4. Tidak ada merge sebelum review approval-style di PR.
5. Tidak ada implementasi besar tanpa issue markdown di folder ini.

## Risiko Lintas-Fase
- Memori container Python AI sudah jadi concern (`issue-python-ai-memory-optimization-2026-04-28.md`). Fase 1 (DOCX→HTML) dan Fase 2 (table extraction, weasyprint) menambah dependensi Python — wajib pakai pola subprocess `document_runner.py`.
- OnlyOffice container di Fase 3 menambah ~1.5–2 GB RAM idle. Perlu rekomendasi minimum 4 GB RAM total di droplet production. Dokumentasikan di README deploy.
- Token cloud (Fase 4) wajib encrypted at rest. Pastikan `.env.example` lengkap dan tidak ada commit yang ekspos secret.

## File Roadmap Terkait
- `issue/issue-document-viewer-sidebar-2026-04-30.md`
- `issue/issue-multi-format-export-2026-04-30.md`
- `issue/issue-canvas-memo-onlyoffice-2026-04-30.md`
- `issue/issue-google-drive-integration-2026-04-30.md`

## Status
- [x] Roadmap & 4 issue markdown disiapkan (PR planning ini).
- [ ] Fase 1 — Document Viewer di Sidebar.
- [ ] Fase 2 — Output Multi-Format.
- [ ] Fase 3 — Canvas Memo Editor (OnlyOffice).
- [ ] Fase 4 — Google Drive Integration.
