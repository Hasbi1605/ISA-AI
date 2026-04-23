# Issue Plan: Fix Review Blockers untuk PR #81

## Blocker yang Perlu Diperbaiki

### Blocker 1: RAG context tidak dipakai dalam prompt model
- `$messagesWithRag` sudah dibuat tapi tidak dipakai
- `AgentPrompt` tetap pakai `$query` saja
- Harus dipakai dalam `messages` parameter `AgentPrompt::for()`

### Blocker 2: Fallback web search yield langsung SDK stream mentah
- Di jalur dokumen tidak menjawab (line 241, 272) pakai `yield from $provider->stream($promptObj)`
- Tidak parsing TextDelta/Citation dan tidak emit source marker
- Harusnya sama dengan jalur non-dokumen: iterasi dan emit normalized output

### Blocker 3: calculateSimilarity() pakai class tidak ada
- Pakai `new \Laravel\Ai\Embeddings\EmbeddingPrompt(...)` yang tidak ada
- Contract SDK: `embeddings(array $inputs, ?int $dimensions, ?string $model)`
- Fallback lexical sudah ada, tapi harus protection dengan test

## Langkah Fix

1. **Fix Blocker 1**: Build messages array dengan RAG context, gunakan dalam AgentPrompt
2. **Fix Blocker 2**: Samakan stream parsing di semua cabang fallback web search
3. **Fix Blocker 3**: Gunakan API `embeddings(array $inputs)` SDK yang benar
4. **Tambah test**: Test skenario retrieval success, no-answer + web fallback
5. **Verifikasi**: Jalankan test Laravel
6. **Push**: Commit dan push ke branch PR
7. **Comment**: Tulis ringkasan di PR

## Files yang Berubah
- `laravel/app/Services/Chat/LaravelChatService.php`
- `laravel/app/Services/Document/LaravelDocumentRetrievalService.php`
- `laravel/tests/Unit/Services/Chat/LaravelChatServiceTest.php`