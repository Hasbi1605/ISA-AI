# Manual Benchmarks

Folder ini berisi skrip manual dan benchmark network-dependent yang tidak menjadi bagian dari suite otomatis `pytest`.

## Isi

- `benchmark_github_models_cli.py`
- `manual_github_models.py`
- `manual_limit_4o.py`
- `manual_embedding_limit.py`
- `manual_e2e_rag.py`
- `manual_e2e_rag_large.py`
- `manual_token_aware_chunking.py`
- `benchmark_results_github_models.json`

## Catatan

- Beberapa skrip memerlukan `gh auth token`, `AI_SERVICE_TOKEN`, atau akses jaringan.
- Skrip di folder ini sengaja tidak dinamai `test_*.py` supaya tidak tercampur dengan test otomatis.
