# Issue #192 — Tuning RAG Dokumen: HyDE, top_k, Rerank, dan Recall Berbasis Eval

## Latar Belakang

Audit (#188) dan baseline metrik (#190) menemukan beberapa kandidat tuning RAG dokumen:
- HyDE saat ini `mode: always` — menambah ~300-600ms per request retrieval
- `top_k: 8` dan `doc_candidates: 25` — context besar memperlambat TTFT LLM
- HyDE `timeout: 3s` sudah ketat, tapi `max_attempts: 2` bisa memperlambat jika model pertama lambat

## Keputusan Berbasis Eval

### HyDE: `always` → `smart`

**Alasan:**
- Mode `always` menambah ~300-600ms untuk SETIAP query dokumen, termasuk query sederhana seperti "ringkaskan dokumen ini" yang tidak butuh HyDE
- Mode `smart` sudah punya pattern detection yang baik di `_should_use_hyde()` — aktif untuk query konseptual (mengapa, bagaimana, analisis, evaluasi, dll)
- Eval set (#190) menunjukkan query dokumen kantor mayoritas adalah ringkasan, fakta spesifik, dan pertanyaan langsung — bukan query konseptual yang butuh HyDE
- Rollback mudah: ubah `mode: smart` kembali ke `mode: always` di YAML

**Guardrail:**
- Mode `smart` tetap aktif untuk query konseptual panjang (>= 8 kata dengan tanda tanya)
- Tidak menghapus HyDE — hanya mengubah kapan diaktifkan

### top_k: 8 → 5, doc_candidates: 25 → 20

**Alasan:**
- `top_k: 8` dengan PDR aktif berarti 8 parent chunks (~1500 token each) = ~12.000 token context
- Mengurangi ke 5 chunks = ~7.500 token — masih cukup untuk menjawab pertanyaan dokumen kantor
- `doc_candidates: 25 → 20` mengurangi jumlah chunk yang di-fetch dari ChromaDB sebelum rerank
- Eval set (#190) menunjukkan 5 chunk sudah cukup untuk recall pada query dokumen kantor
- Rollback: ubah nilai di YAML

**Guardrail:**
- `top_n` tetap sama dengan `top_k` (5) agar reranker tidak membuang chunk yang dibutuhkan
- Multi-document retrieval tetap adil: forced inclusion tetap aktif

### HyDE max_attempts: 2 → 1

**Alasan:**
- Dengan `timeout: 3s` dan `max_attempts: 2`, worst case HyDE bisa memakan 6s
- Mengurangi ke 1 attempt: jika model pertama gagal, langsung fallback ke query asli
- Tidak mengurangi kualitas karena model pertama (Groq/Llama) sudah cepat dan reliable

## Scope Implementasi

### File Diubah
- `python-ai/config/ai_config.yaml` — ubah HyDE mode, top_k, doc_candidates, max_attempts
- `python-ai/app/services/rag_hybrid.py` — kurangi max_attempts default dari 2 ke 1
- `python-ai/tests/test_rag_policy_singleton.py` atau test baru — verifikasi HyDE smart mode

### File Baru
- `python-ai/tests/test_rag_tuning.py` — test untuk perubahan tuning
- `issue/issue-192-rag-tuning-hyde-topk-rerank-2026-05-15.md` — issue plan ini

## Acceptance Criteria

- [ ] HyDE mode berubah ke `smart` di config
- [ ] top_k berubah ke 5, doc_candidates ke 20
- [ ] HyDE max_attempts berubah ke 1
- [ ] Test memverifikasi HyDE smart mode aktif untuk query konseptual
- [ ] Test memverifikasi HyDE smart mode skip untuk query sederhana
- [ ] Full Python test hijau

## Rollback

Semua perubahan bisa di-rollback dengan mengubah nilai di `ai_config.yaml`:
```yaml
hyde:
  mode: always      # rollback dari smart
  max_tokens: 100
  timeout: 3

semantic_rerank:
  top_k: 8          # rollback dari 5
  top_n: 8          # rollback dari 5
  doc_candidates: 25 # rollback dari 20
```

## Cara Verifikasi

```bash
cd python-ai && source venv/bin/activate && pytest
```
