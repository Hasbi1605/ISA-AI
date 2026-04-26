<?php

namespace Tests\Unit\Services\Document;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Services\Document\HybridRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('retrieval')]
#[Group('hybrid')]
class HybridRetrievalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        $this->document = Document::create([
            'user_id' => $this->user->id,
            'filename' => 'test_document.pdf',
            'original_name' => 'test_document.pdf',
            'file_path' => 'documents/' . $this->user->id . '/test_document.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1000,
            'status' => 'ready',
        ]);
        
        Storage::disk('local')->put($this->document->file_path, 'ini adalah konten dokumen untuk pengujian retrieval. dokumen berisi informasi tentang teknologi AI dan machine learning. teknik machine learning meliputi supervised learning, unsupervised learning, dan reinforcement learning.');
    }

    #[Test]
    public function it_performs_hybrid_search_with_bm25_and_vector(): void
    {
        config(['ai.rag.hybrid.enabled' => true]);
        
        $service = new HybridRetrievalService();
        
        $result = $service->search('machine learning', [], 5, (string) $this->user->id);
        
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_performs_bm25_only_when_hybrid_disabled(): void
    {
        config(['ai.rag.hybrid.enabled' => false]);
        
        $service = new HybridRetrievalService();
        
        $result = $service->search('machine learning', [], 5, (string) $this->user->id);
        
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_merges_results_using_rrf(): void
    {
        config(['ai.rag.hybrid.enabled' => true]);
        
        $service = new HybridRetrievalService();
        
        $result = $service->search('teknologi', [], 5, (string) $this->user->id);
        
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_reranks_rag_candidates_with_langsearch_when_enabled(): void
    {
        config([
            'ai.rag.hybrid.enabled' => true,
            'ai.rag.pdr.enabled' => false,
            'ai.rag.semantic_rerank.enabled' => true,
            'ai.rag.semantic_rerank.top_n' => 2,
            'ai.rag.semantic_rerank.doc_candidates' => 3,
            'ai.langsearch.api_key' => 'test-langsearch-key',
            'ai.langsearch.rerank_url' => 'https://api.langsearch.com/v1/rerank',
            'ai.langsearch.rerank_model' => 'langsearch-reranker-v1',
        ]);

        Http::fake([
            'api.langsearch.com/v1/rerank' => Http::response([
                'results' => [
                    ['index' => 1, 'relevance_score' => 0.99],
                    ['index' => 0, 'relevance_score' => 0.88],
                ],
            ], 200),
        ]);

        $documentId = $this->document->id;

        $service = new class($documentId) extends HybridRetrievalService {
            public function __construct(private readonly int $documentId)
            {
                parent::__construct();
            }

            protected function getDocumentsForUser(array $filenames, string $userId): array
            {
                return [['id' => $this->documentId, 'original_name' => 'doc-a.pdf']];
            }

            protected function getQueryEmbedding(string $query): ?array
            {
                return [1.0, 0.0];
            }

            protected function getBm25ScoredChunks(Document $document, string $query, int $limit): array
            {
                return [
                    [
                        'content' => 'Chunk pertama',
                        'score' => 0.7,
                        'bm25_score' => 0.7,
                        'filename' => 'doc-a.pdf',
                        'chunk_index' => 0,
                        'chunk_id' => 10,
                        'chunk_type' => 'child',
                    ],
                    [
                        'content' => 'Chunk kedua',
                        'score' => 0.6,
                        'bm25_score' => 0.6,
                        'filename' => 'doc-a.pdf',
                        'chunk_index' => 1,
                        'chunk_id' => 11,
                        'chunk_type' => 'child',
                    ],
                ];
            }

            protected function getVectorScoredChunks(Document $document, ?array $queryEmbedding, string $query, int $limit): array
            {
                return [
                    [
                        'content' => 'Chunk pertama',
                        'score' => 0.8,
                        'vector_score' => 0.8,
                        'filename' => 'doc-a.pdf',
                        'chunk_index' => 0,
                        'chunk_id' => 10,
                        'chunk_type' => 'child',
                    ],
                    [
                        'content' => 'Chunk kedua',
                        'score' => 0.5,
                        'vector_score' => 0.5,
                        'filename' => 'doc-a.pdf',
                        'chunk_index' => 1,
                        'chunk_id' => 11,
                        'chunk_type' => 'child',
                    ],
                ];
            }

            protected function ensureDocumentIngested(Document $document, bool $usePdr): void
            {
                // no-op for deterministic test
            }
        };

        $result = $service->search('query retrieval', [], 2, (string) $this->user->id);

        $this->assertTrue($result['success']);
        $this->assertSame('Chunk kedua', $result['chunks'][0]['content']);
        $this->assertSame(0.99, $result['chunks'][0]['rerank_score']);
        $this->assertSame('Chunk pertama', $result['chunks'][1]['content']);
    }

    #[Test]
    public function it_resolves_pdr_parent_for_child_chunks(): void
    {
        config([
            'ai.rag.hybrid.enabled' => true,
            'ai.rag.pdr.enabled' => true,
            'ai.rag.pdr.child_chunk_size' => 50,
            'ai.rag.pdr.child_chunk_overlap' => 10,
            'ai.rag.pdr.parent_chunk_size' => 200,
            'ai.rag.pdr.parent_chunk_overlap' => 30,
        ]);
        
        $service = new HybridRetrievalService();
        
        $result = $service->search('machine learning', [], 3, (string) $this->user->id);
        
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_returns_empty_for_no_documents(): void
    {
        config(['ai.rag.hybrid.enabled' => true]);
        
        $service = new HybridRetrievalService();
        
        $result = $service->search('query', ['nonexistent.pdf'], 5, (string) ($this->user->id + 999));
        
        $this->assertFalse($result['success']);
        $this->assertEquals('no_documents', $result['reason']);
        $this->assertEmpty($result['chunks']);
    }

    #[Test]
    public function it_respects_user_isolation(): void
    {
        config(['ai.rag.hybrid.enabled' => true]);
        
        $otherUser = User::factory()->create();
        
        $service = new HybridRetrievalService();
        
        $result = $service->search('machine learning', [], 5, (string) $otherUser->id);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('no_documents', $result['reason']);
        $this->assertEmpty($result['chunks']);
    }

    #[Test]
    public function it_calculates_bm25_scores_correctly(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('calculateBm25Scores');
        $method->setAccessible(true);
        
        $service = new HybridRetrievalService();
        
        $query = 'machine learning';
        $documents = [
            'Machine learning adalah teknik AI',
            'Deep learning adalah bagian dari machine learning',
            'Web development tidak terkait dengan machine learning',
        ];
        
        $scores = $method->invoke($service, $query, $documents);
        
        $this->assertIsArray($scores);
        $this->assertCount(3, $scores);
        $this->assertGreaterThan($scores[2], $scores[0]);
    }

    #[Test]
    public function it_merges_with_rrf_formula(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('performRrfMerge');
        $method->setAccessible(true);
        
        $service = new HybridRetrievalService();
        
        $bm25Results = [
            ['content' => 'doc1', 'bm25_score' => 0.9, 'filename' => 'doc1.pdf'],
            ['content' => 'doc2', 'bm25_score' => 0.7, 'filename' => 'doc2.pdf'],
            ['content' => 'doc3', 'bm25_score' => 0.5, 'filename' => 'doc3.pdf'],
        ];
        
        $vectorResults = [
            ['content' => 'doc1', 'vector_score' => 0.8, 'filename' => 'doc1.pdf'],
            ['content' => 'doc2', 'vector_score' => 0.6, 'filename' => 'doc2.pdf'],
            ['content' => 'doc4', 'vector_score' => 0.9, 'filename' => 'doc4.pdf'],
        ];
        
        $merged = $method->invoke($service, $bm25Results, $vectorResults, 3);
        
        $this->assertNotEmpty($merged);
        $this->assertCount(3, $merged);
    }

    #[Test]
    public function it_handles_empty_bm25_results(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('performRrfMerge');
        $method->setAccessible(true);
        
        $service = new HybridRetrievalService();
        
        $bm25Results = [];
        $vectorResults = [
            ['content' => 'doc1', 'vector_score' => 0.8, 'filename' => 'doc1.pdf'],
        ];
        
        $merged = $method->invoke($service, $bm25Results, $vectorResults, 3);
        
        $this->assertNotEmpty($merged);
    }

    #[Test]
    public function it_handles_empty_vector_results(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('performRrfMerge');
        $method->setAccessible(true);
        
        $service = new HybridRetrievalService();
        
        $bm25Results = [
            ['content' => 'doc1', 'bm25_score' => 0.8, 'filename' => 'doc1.pdf'],
        ];
        $vectorResults = [];
        
        $merged = $method->invoke($service, $bm25Results, $vectorResults, 3);
        
        $this->assertNotEmpty($merged);
    }

    #[Test]
    public function it_calculates_cosine_similarity(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('cosineSimilarity');
        $method->setAccessible(true);
        
        $service = new HybridRetrievalService();
        
        $vecA = [1.0, 0.0, 0.0];
        $vecB = [1.0, 0.0, 0.0];
        
        $similarity = $method->invoke($service, $vecA, $vecB);
        
        $this->assertEquals(1.0, $similarity, 5);
        
        $vecC = [1.0, 0.0, 0.0];
        $vecD = [0.0, 1.0, 0.0];
        
        $similarity = $method->invoke($service, $vecC, $vecD);
        
        $this->assertEquals(0.0, $similarity);
    }

    #[Test]
    public function it_tokenizes_text_for_bm25(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('tokenize');
        $method->setAccessible(true);

        $service = new HybridRetrievalService();

        $tokens = $method->invoke($service, 'Machine Learning adalah AI');

        $this->assertContains('machine', $tokens);
        $this->assertContains('learning', $tokens);
        $this->assertContains('adalah', $tokens);
        $this->assertContains('ai', $tokens);
    }

    #[Test]
    public function it_does_not_leak_parent_chunks_from_other_users(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('resolvePdrParents');
        $method->setAccessible(true);

        $service = new HybridRetrievalService();

        $otherUser = User::factory()->create();
        $otherDoc = Document::create([
            'user_id' => $otherUser->id,
            'filename' => 'other_user_doc.pdf',
            'original_name' => 'other_user_doc.pdf',
            'file_path' => 'documents/' . $otherUser->id . '/other_user_doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1000,
            'status' => 'ready',
        ]);

        $otherParentId = md5('other_user_doc.pdf:' . $otherUser->id . ':0:Parent chunk from other us');
        $otherParent = DocumentChunk::create([
            'document_id' => $otherDoc->id,
            'chunk_type' => 'parent',
            'parent_id' => $otherParentId,
            'parent_index' => 0,
            'text_content' => 'Parent chunk from other user - SECRET DATA',
        ]);

        $userParentId = md5('test.pdf:' . $this->user->id . ':0:User parent chunk - a');
        $userParent = DocumentChunk::create([
            'document_id' => $this->document->id,
            'chunk_type' => 'parent',
            'parent_id' => $userParentId,
            'parent_index' => 0,
            'text_content' => 'User parent chunk - allowed',
        ]);

        $childChunks = [
            [
                'content' => 'Child pointing to other user parent',
                'score' => 0.9,
                'filename' => 'test.pdf',
                'chunk_index' => 0,
                'chunk_id' => 999,
                'parent_id' => $otherParentId,
                'chunk_type' => 'child',
            ],
            [
                'content' => 'Child pointing to user parent',
                'score' => 0.8,
                'filename' => 'test.pdf',
                'chunk_index' => 1,
                'chunk_id' => 998,
                'parent_id' => $userParentId,
                'chunk_type' => 'child',
            ],
        ];

        $result = $method->invoke($service, $childChunks, (string) $this->user->id);

        $this->assertNotEmpty($result);
        $contents = array_column($result, 'content');
        $this->assertContains('User parent chunk - allowed', $contents);
        $this->assertNotContains('Parent chunk from other user - SECRET DATA', $contents);

        $otherParent->delete();
        $userParent->delete();
        $otherDoc->delete();
        $otherUser->delete();
    }

    #[Test]
    public function it_does_not_collapse_chunks_with_same_prefix(): void
    {
        $reflection = new \ReflectionClass(HybridRetrievalService::class);
        $method = $reflection->getMethod('performRrfMerge');
        $method->setAccessible(true);

        $service = new HybridRetrievalService();

        $bm25Results = [
            [
                'content' => 'ini adalah teks yang sangat panjang dari dokumen pertama dengan detail berbeda',
                'bm25_score' => 0.9,
                'filename' => 'doc1.pdf',
                'chunk_id' => 1,
                'document_id' => 10,
                'chunk_index' => 0,
            ],
            [
                'content' => 'ini adalah teks yang sangat panjang dari dokumen kedua dengan detail berbeda',
                'bm25_score' => 0.8,
                'filename' => 'doc2.pdf',
                'chunk_id' => 2,
                'document_id' => 20,
                'chunk_index' => 0,
            ],
        ];

        $vectorResults = [
            [
                'content' => 'ini adalah teks yang sangat panjang dari dokumen pertama dengan detail berbeda',
                'vector_score' => 0.7,
                'filename' => 'doc1.pdf',
                'chunk_id' => 1,
                'document_id' => 10,
                'chunk_index' => 0,
            ],
            [
                'content' => 'ini adalah teks yang sangat panjang dari dokumen kedua dengan detail berbeda',
                'vector_score' => 0.6,
                'filename' => 'doc2.pdf',
                'chunk_id' => 2,
                'document_id' => 20,
                'chunk_index' => 0,
            ],
        ];

        $merged = $method->invoke($service, $bm25Results, $vectorResults, 5);

        $this->assertCount(2, $merged);
        $filenames = array_column($merged, 'filename');
        $this->assertContains('doc1.pdf', $filenames);
        $this->assertContains('doc2.pdf', $filenames);
    }
}
