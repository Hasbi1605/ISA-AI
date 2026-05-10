# Security Deep Audit ISTA AI — 2026-05-10

## Sumber kebenaran
Permintaan terbaru pengguna: audit keamanan seluruh sistem ISTA AI secara mendalam, termasuk history git repo, deteksi potensi kebocoran database atau token API yang terekspos, dan penambahan security test yang relevan.

File issue lama bersifat historis kecuali temuan audit membuktikan keterkaitan langsung.

## Tujuan
- Memetakan aset, trust boundary, dan permukaan serangan utama ISTA AI.
- Melakukan audit kode Laravel, Python AI runtime, konfigurasi deploy, dependency, dan history git untuk indikasi secret/database/API token yang terekspos.
- Mengklasifikasikan temuan dengan severity realistis: Critical, High, Medium, Low, Informational.
- Menambahkan security test yang relevan untuk blocker tervalidasi jika secara teknis memungkinkan.
- Memperbaiki blocker keamanan tervalidasi dengan perubahan sekecil mungkin.
- Membuat PR, deploy preview ke `https://ista-ai.app`, menjalankan QA keamanan berbasis browser setelah deploy, dan menghentikan workflow sebelum merge untuk meminta approval eksplisit.

## Aset utama
- Data pengguna, sesi, password hash, OTP, dan token autentikasi Laravel.
- Dokumen, memo, file upload/download, preview, export, dan lifecycle penyimpanan.
- Integrasi AI dan provider eksternal: OpenAI/GitHub Models/Bedrock/embedding/vector/RAG.
- Integrasi Google Drive/OAuth dan callback terkait.
- Database aplikasi, storage Laravel, queue/cache/session, dan artefak deploy production.
- Secret runtime: `.env`, `.env.droplet`, API keys, OAuth credentials, database URL/password, deploy key, dan token GitHub.

## Trust boundary dan attacker model
- Unauthenticated internet attacker pada route publik, endpoint auth, callback, dan health/deploy surface.
- Authenticated user rendah yang mencoba IDOR, privilege escalation, akses dokumen/memo pengguna lain, atau abuse AI endpoints.
- Malicious file uploader yang mencoba upload executable, path traversal, XSS via filename/content, atau abuse parser dokumen.
- Attacker dengan akses ke repo/git history yang mencari secret, dump database, atau token yang pernah committed.
- Supply-chain attacker melalui dependency Node/PHP/Python atau konfigurasi CI/deploy.
- Internal/operator risk: secret tersimpan di repo lokal, deployment config terlalu permisif, debug/log exposing sensitive data.

## Target audit
1. **Git dan secret exposure**
   - Worktree saat ini, untracked/ignored sensitive files, `.gitignore`, GitHub remote, dan seluruh history git.
   - Pola secret umum: API key, GitHub token, OpenAI/Anthropic/Bedrock/AWS, Google OAuth, DB credentials, private keys, JWT/session secrets, dump database.
2. **Laravel**
   - Auth, registration, OTP, password reset, profile, session/token handling.
   - Authorization untuk dokumen, memo, chat, Google Drive, export/download, admin-like routes.
   - Upload/download, preview, OnlyOffice/PDF callback, storage path, MIME/extension, signed URL/callback validation.
   - Validation, mass assignment, SQL/raw query, rate limiting, CSRF/CORS, debug/config exposure.
3. **Python AI runtime**
   - API boundary dari Laravel ke Python, file/document parsing, prompt/data leakage, provider fallback, request validation, logging, temp files.
   - Dependency/security posture dan unsafe subprocess/file handling.
4. **Deploy/ops/CI**
   - Docker compose, deploy scripts, GitHub Actions, environment assumptions, exposed ports, production debug, permission boundaries.
5. **Tests**
   - Security regression tests untuk temuan tervalidasi, terutama authz/IDOR, callback validation, file handling, secret scanner/safety checks, dan Python runtime input handling.

## Workflow eksekusi
1. Jalankan dua audit independen read-only sebelum implementasi:
   - `parallel-security-auditor-deep`
   - `parallel-security-redteam`
2. Tambahkan `parallel-security-reviewer` bila ada disagreement, area auth/secrets/deploy tersentuh, atau perlu pass provider-diverse.
3. Rekonsiliasi temuan secara konservatif. P0/P1 dari salah satu reviewer dianggap blocker sampai terbukti tidak valid lewat bukti kode.
4. Delegasikan security tests ke `parallel-security-test-engineer` dengan ownership file eksplisit.
5. Delegasikan fix ke `parallel-security-fixer`; lead tidak mengubah app/test/config/security files langsung.
6. Jalankan validasi lokal relevan setelah setiap perubahan.
7. Commit/push/PR dilakukan oleh `parallel-git-publisher`.
8. Setelah PR ada, deploy branch ke `https://ista-ai.app` via `parallel-production-deployer`.
9. QA browser hanya setelah deploy dan hanya terhadap `https://ista-ai.app` via `parallel-qa-browser`.
10. Jalankan security review loop, post satu komentar status PR biasa, lalu fix blocker setelah komentar terposting.
11. Akhiri dengan full final verification Laravel dan Python sesuai `AGENTS.md`, lalu minta approval merge eksplisit.

## Acceptance criteria
- Ada bukti audit read-only dari minimal dua pass independen sebelum implementasi.
- Secret/history exposure diperiksa dan hasilnya diringkas tanpa membocorkan nilai secret.
- Semua blocker keamanan tervalidasi diperbaiki atau dinyatakan blocked dengan alasan teknis yang jelas.
- Security tests relevan ditambahkan untuk setiap blocker yang diperbaiki bila memungkinkan.
- Test Laravel dan Python relevan berjalan; full final verification dilakukan sebelum merge-ready.
- PR berisi ringkasan security posture, test evidence, deploy SHA, QA evidence, residual risk, dan tidak di-merge tanpa approval user.

## Risiko dan batasan
- Repo berisi file sensitif lokal seperti `.env.droplet`; nilai secret tidak boleh dicetak di output publik.
- Secret yang ditemukan di history tetap dianggap compromised sampai dirotasi, meski dihapus dari HEAD.
- Deploy production preview dapat memerlukan akses/secret eksternal; bila tidak tersedia, workflow berhenti di titik aman dan melaporkan blocker.
- Audit otomatis tidak menggantikan rotasi credential atau audit infrastruktur cloud di luar repo.
