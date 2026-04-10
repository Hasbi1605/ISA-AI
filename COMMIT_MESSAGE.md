# Commit Message

## fix: address PR #30 security review - prevent global document retrieval

### Changes

1. **Security Fix: Remove RAG retrieval from get_context_for_query()**
   - Prevents potential global document retrieval without user_id filtering
   - get_context_for_query() now handles ONLY web search context
   - All RAG retrieval goes through search_relevant_chunks() with proper authorization
   - Implements Option B from PR review comment

2. **Add missing test dependency**
   - Added hypothesis>=6.0.0 to requirements.txt
   - Fixes potential CI failures due to missing dependency

3. **Update tests to reflect new architecture**
   - Updated test_rag_bug_exploration.py with new reason code assertions
   - Updated test_rag_preservation.py to verify security fix
   - All 13 tests passing

### Security Impact

**Before:**
- get_context_for_query() performed RAG retrieval without user_id/document filtering
- Risk of cross-user document leakage in fallback paths

**After:**
- get_context_for_query() performs NO RAG retrieval (web search only)
- ALL RAG retrieval requires user_id and document_filenames filtering
- Fail-closed security model enforced

### Files Modified

- python-ai/requirements.txt
- python-ai/app/services/rag_service.py
- python-ai/tests/test_rag_bug_exploration.py
- python-ai/tests/test_rag_preservation.py

### Test Results

```
13 passed in 4.26s
```

Addresses review comments in: https://github.com/Hasbi1605/ISTA-AI/pull/30#issuecomment-4221778748
