"""
Preservation Property Tests for RAG Document Control Fix

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**

These tests verify that RAG retrieval behavior when documents ARE selected
remains unchanged after the fix. These tests should PASS on UNFIXED code
to establish the baseline behavior that must be preserved.

Preservation Requirements:
- RAG retrieval with documents_active=true works correctly
- Authorization filtering with user_id is enforced
- Reranking functionality works correctly
- Web search + RAG combination works correctly
"""

import pytest
from unittest.mock import patch, MagicMock, call
from hypothesis import given, strategies as st, settings, Phase
from app.services.rag_service import get_context_for_query, search_relevant_chunks


# Test queries for preservation testing
PRESERVATION_QUERIES = [
    "what is machine learning?",
    "explain neural networks",
    "how does backpropagation work?",
    "what are transformers in AI?",
]


class TestPreservationWithDocuments:
    """
    Preservation Property Tests: RAG Retrieval With Documents Unchanged
    
    These tests verify that when documents_active=true, the system continues
    to perform RAG retrieval exactly as before the fix.
    
    EXPECTED OUTCOME: These tests should PASS on UNFIXED code (establishing baseline)
    and continue to PASS on FIXED code (confirming no regressions).
    """
    
    @pytest.mark.parametrize("query", PRESERVATION_QUERIES)
    def test_rag_retrieval_with_documents_active(self, query):
        """
        Test that RAG retrieval works correctly when documents_active=true.
        
        Expected behavior (both UNFIXED and FIXED code):
        - get_embeddings_with_fallback() IS called when documents_active=true
        - vectorstore.similarity_search() IS called when documents_active=true
        - result["has_rag"] should be true (if documents found)
        - result["rag_documents"] should contain relevant chunks
        
        **Validates: Requirements 3.3**
        """
        with patch('app.services.rag_service.get_embeddings_with_fallback') as mock_embeddings, \
             patch('app.services.rag_service.Chroma') as mock_chroma:
            
            # Setup mocks to simulate successful RAG retrieval
            mock_embedding_obj = MagicMock()
            mock_embeddings.return_value = (mock_embedding_obj, "test_provider")
            
            mock_vectorstore = MagicMock()
            mock_chroma.return_value = mock_vectorstore
            
            # Simulate finding relevant documents
            mock_doc1 = MagicMock()
            mock_doc1.page_content = "Machine learning is a subset of AI..."
            mock_doc1.metadata = {"filename": "ml_basics.pdf", "chunk_index": 0}
            
            mock_doc2 = MagicMock()
            mock_doc2.page_content = "Neural networks are computational models..."
            mock_doc2.metadata = {"filename": "neural_nets.pdf", "chunk_index": 1}
            
            mock_vectorstore.similarity_search.return_value = [mock_doc1, mock_doc2]
            
            # Call the function with documents_active=true
            result = get_context_for_query(
                query=query,
                documents_active=True,
                force_web_search=False,
                allow_auto_realtime_web=False,
                explicit_web_request=False
            )
            
            # PRESERVATION ASSERTIONS:
            # These should PASS on both unfixed and fixed code
            
            # Assert that get_embeddings_with_fallback() WAS called
            assert mock_embeddings.called, (
                f"REGRESSION: get_embeddings_with_fallback() was NOT called for query '{query}' "
                f"with documents_active=true. This is a regression - it should be called."
            )
            
            # Assert that Chroma vectorstore WAS instantiated
            assert mock_chroma.called, (
                f"REGRESSION: Chroma vectorstore was NOT instantiated for query '{query}' "
                f"with documents_active=true. This is a regression - it should be instantiated."
            )
            
            # Assert that similarity_search WAS called
            assert mock_vectorstore.similarity_search.called, (
                f"REGRESSION: similarity_search() was NOT called for query '{query}' "
                f"with documents_active=true. This is a regression - it should be called."
            )
            
            # Assert that result indicates RAG retrieval occurred
            assert result["has_rag"] is True, (
                f"REGRESSION: result['has_rag'] is False for query '{query}' "
                f"with documents_active=true. Expected: True. Actual: {result['has_rag']}."
            )
            
            # Assert that rag_documents contains the mocked documents
            assert len(result["rag_documents"]) == 2, (
                f"REGRESSION: result['rag_documents'] has unexpected length for query '{query}' "
                f"with documents_active=true. Expected: 2. Actual: {len(result['rag_documents'])}."
            )


    @given(query=st.text(min_size=1, max_size=100))
    @settings(
        max_examples=20,
        phases=[Phase.generate, Phase.target],
        deadline=None
    )
    def test_property_rag_with_documents_always_retrieves(self, query):
        """
        Property-based test: For ANY query with documents_active=true,
        RAG retrieval SHOULD be invoked.
        
        **Validates: Requirements 3.3**
        
        This property test generates random queries and verifies that
        get_embeddings_with_fallback() and similarity_search() ARE called
        when documents_active=true.
        
        EXPECTED OUTCOME: This test should PASS on both unfixed and fixed code.
        """
        with patch('app.services.rag_service.get_embeddings_with_fallback') as mock_embeddings, \
             patch('app.services.rag_service.Chroma') as mock_chroma:
            
            # Setup mocks
            mock_embedding_obj = MagicMock()
            mock_embeddings.return_value = (mock_embedding_obj, "test_provider")
            
            mock_vectorstore = MagicMock()
            mock_chroma.return_value = mock_vectorstore
            mock_vectorstore.similarity_search.return_value = []
            
            # Call the function with documents_active=true
            result = get_context_for_query(
                query=query,
                documents_active=True,
                force_web_search=False,
                allow_auto_realtime_web=False,
                explicit_web_request=False
            )
            
            # Property: RAG retrieval SHOULD be invoked
            assert mock_embeddings.called, (
                f"Property violation: get_embeddings_with_fallback() NOT called for query '{query[:50]}...' "
                f"with documents_active=true (REGRESSION)"
            )
            
            assert mock_chroma.called, (
                f"Property violation: Chroma vectorstore NOT instantiated for query '{query[:50]}...' "
                f"with documents_active=true (REGRESSION)"
            )


class TestAuthorizationFiltering:
    """
    Test that authorization filtering with user_id is enforced.
    
    **Validates: Requirements 3.3**
    """
    
    def test_search_relevant_chunks_requires_user_id(self):
        """
        Test that search_relevant_chunks requires user_id for security.
        
        Expected behavior (both UNFIXED and FIXED code):
        - When user_id is None, search should fail with empty results
        - When user_id is provided, search should proceed with filtering
        """
        with patch('app.services.rag_service.get_embeddings_with_fallback') as mock_embeddings, \
             patch('app.services.rag_service.Chroma') as mock_chroma:
            
            # Setup mocks
            mock_embedding_obj = MagicMock()
            mock_embeddings.return_value = (mock_embedding_obj, "test_provider")
            
            mock_vectorstore = MagicMock()
            mock_chroma.return_value = mock_vectorstore
            mock_vectorstore.similarity_search_with_score.return_value = []
            
            # Test 1: Without user_id (should fail)
            chunks, success = search_relevant_chunks(
                query="test query",
                filenames=["test.pdf"],
                user_id=None
            )
            
            assert success is False, (
                "REGRESSION: search_relevant_chunks should fail when user_id is None"
            )
            assert len(chunks) == 0, (
                "REGRESSION: search_relevant_chunks should return empty list when user_id is None"
            )
            
            # Test 2: With user_id (should succeed)
            mock_vectorstore.similarity_search_with_score.return_value = [
                (MagicMock(page_content="test", metadata={"filename": "test.pdf", "chunk_index": 0}), 0.9)
            ]
            
            chunks, success = search_relevant_chunks(
                query="test query",
                filenames=["test.pdf"],
                user_id="user123"
            )
            
            assert success is True, (
                "REGRESSION: search_relevant_chunks should succeed when user_id is provided"
            )
            
            # Verify that the filter includes user_id
            call_args = mock_vectorstore.similarity_search_with_score.call_args
            if call_args:
                filter_dict = call_args[1].get('filter', {})
                # The filter should contain user_id either directly or in $and clause
                assert 'user_id' in str(filter_dict), (
                    "REGRESSION: user_id filter not applied in search_relevant_chunks"
                )


class TestReranking:
    """
    Test that reranking functionality works correctly.
    
    **Validates: Requirements 3.4**
    """
    
    def test_reranking_applied_when_enabled(self):
        """
        Test that reranking is applied to document chunks when enabled.
        
        Expected behavior (both UNFIXED and FIXED code):
        - When LANGSEARCH_RERANK_ENABLED=true, reranking should be applied
        - Reranked results should include rerank_score
        """
        with patch('app.services.rag_service.get_embeddings_with_fallback') as mock_embeddings, \
             patch('app.services.rag_service.Chroma') as mock_chroma, \
             patch('app.services.rag_service.get_langsearch_service') as mock_langsearch_service, \
             patch.dict('os.environ', {'LANGSEARCH_RERANK_ENABLED': 'true'}):
            
            # Setup mocks
            mock_embedding_obj = MagicMock()
            mock_embeddings.return_value = (mock_embedding_obj, "test_provider")
            
            mock_vectorstore = MagicMock()
            mock_chroma.return_value = mock_vectorstore
            
            # Simulate multiple document candidates
            mock_docs = [
                (MagicMock(page_content=f"Document {i}", metadata={"filename": f"doc{i}.pdf", "chunk_index": i}), 0.9 - i*0.1)
                for i in range(5)
            ]
            mock_vectorstore.similarity_search_with_score.return_value = mock_docs
            
            # Setup LangSearch service mock
            mock_langsearch = MagicMock()
            mock_langsearch_service.return_value = mock_langsearch
            
            # Simulate reranking results (reverse order for testing)
            mock_langsearch.rerank_documents.return_value = [
                {"index": 4, "relevance_score": 0.95},
                {"index": 3, "relevance_score": 0.90},
                {"index": 2, "relevance_score": 0.85},
            ]
            
            # Call search_relevant_chunks
            chunks, success = search_relevant_chunks(
                query="test query",
                filenames=["doc0.pdf"],
                user_id="user123",
                top_k=3
            )
            
            assert success is True, (
                "REGRESSION: search_relevant_chunks should succeed with reranking"
            )
            
            # Verify reranking was called
            assert mock_langsearch.rerank_documents.called, (
                "REGRESSION: rerank_documents should be called when reranking is enabled"
            )
            
            # Verify reranked results include rerank_score
            if len(chunks) > 0:
                assert "rerank_score" in chunks[0], (
                    "REGRESSION: reranked chunks should include rerank_score"
                )


class TestWebSearchCombination:
    """
    Test that web search + RAG combination works correctly.
    
    **Validates: Requirements 3.1, 3.2**
    """
    
    def test_web_search_with_force_flag(self):
        """
        Test that web search works with force_web_search flag.
        
        Expected behavior (both UNFIXED and FIXED code):
        - When force_web_search=true, web search should be performed
        - Web search should work independently of documents_active status
        """
        with patch('app.services.rag_service.get_embeddings_with_fallback') as mock_embeddings, \
             patch('app.services.rag_service.Chroma') as mock_chroma, \
             patch('app.services.rag_service.get_langsearch_service') as mock_langsearch_service:
            
            # Setup mocks
            mock_embedding_obj = MagicMock()
            mock_embeddings.return_value = (mock_embedding_obj, "test_provider")
            
            mock_vectorstore = MagicMock()
            mock_chroma.return_value = mock_vectorstore
            mock_vectorstore.similarity_search.return_value = []
            
            # Setup LangSearch service mock
            mock_langsearch = MagicMock()
            mock_langsearch_service.return_value = mock_langsearch
            mock_langsearch.search.return_value = [
                {"title": "Test Result", "snippet": "Test snippet", "url": "https://example.com"}
            ]
            mock_langsearch.build_search_context.return_value = "Test search context"
            
            # Test with force_web_search=true and documents_active=false
            result = get_context_for_query(
                query="what is the weather today?",
                documents_active=False,
                force_web_search=True,
                allow_auto_realtime_web=False,
                explicit_web_request=False
            )
            
            # Verify web search was performed
            assert mock_langsearch.search.called, (
                "REGRESSION: web search should be performed when force_web_search=true"
            )
            
            assert result["has_search"] is True, (
                "REGRESSION: result['has_search'] should be True when web search is performed"
            )
            
            assert len(result["search_results"]) > 0, (
                "REGRESSION: search_results should not be empty when web search is performed"
            )
    
    
    def test_realtime_intent_triggers_web_search(self):
        """
        Test that realtime queries trigger web search automatically.
        
        Expected behavior (both UNFIXED and FIXED code):
        - Queries with high realtime intent should trigger web search
        - This should work independently of documents_active status
        
        **Validates: Requirements 3.2**
        """
        with patch('app.services.rag_service.get_embeddings_with_fallback') as mock_embeddings, \
             patch('app.services.rag_service.Chroma') as mock_chroma, \
             patch('app.services.rag_service.get_langsearch_service') as mock_langsearch_service:
            
            # Setup mocks
            mock_embedding_obj = MagicMock()
            mock_embeddings.return_value = (mock_embedding_obj, "test_provider")
            
            mock_vectorstore = MagicMock()
            mock_chroma.return_value = mock_vectorstore
            mock_vectorstore.similarity_search.return_value = []
            
            # Setup LangSearch service mock
            mock_langsearch = MagicMock()
            mock_langsearch_service.return_value = mock_langsearch
            mock_langsearch.search.return_value = [
                {"title": "Latest Score", "snippet": "Barcelona 3-1 Atletico", "url": "https://example.com"}
            ]
            mock_langsearch.build_search_context.return_value = "Latest score context"
            
            # Test with realtime query
            result = get_context_for_query(
                query="jam berapa sekarang?",  # High realtime intent
                documents_active=False,
                force_web_search=False,
                allow_auto_realtime_web=True,
                explicit_web_request=False
            )
            
            # Verify web search was triggered by realtime intent
            assert mock_langsearch.search.called, (
                "REGRESSION: web search should be triggered for high realtime intent queries"
            )
            
            assert result["has_search"] is True, (
                "REGRESSION: result['has_search'] should be True for realtime queries"
            )
            
            assert result["realtime_intent"] == "high", (
                "REGRESSION: realtime_intent should be 'high' for time-related queries"
            )


if __name__ == "__main__":
    pytest.main([__file__, "-v", "-s"])
