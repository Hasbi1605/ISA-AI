<?php

namespace Tests\Unit\Services;

use App\Services\LangSearchService;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LangSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('ai.langsearch.api_key', 'test-api-key');
        Config::set('ai.langsearch.api_key_backup', 'test-backup-key');
        Config::set('ai.langsearch.api_url', 'https://api.langsearch.com/v1/web-search');
        Config::set('ai.langsearch.rerank_url', 'https://api.langsearch.com/v1/rerank');
        Config::set('ai.langsearch.rerank_model', 'langsearch-reranker-v1');
        Config::set('ai.langsearch.timeout', 10);
        Config::set('ai.langsearch.rerank_timeout', 8);
        Config::set('ai.langsearch.cache_ttl', 300);
        
        Cache::flush();
    }

    public function test_service_initializes_with_api_keys(): void
    {
        $service = new LangSearchService();
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('apiKeys');
        $property->setAccessible(true);
        
        $apiKeys = $property->getValue($service);
        
        $this->assertCount(2, $apiKeys);
        $this->assertEquals('test-api-key', $apiKeys[0]);
        $this->assertEquals('test-backup-key', $apiKeys[1]);
    }

    public function test_service_works_without_backup_key(): void
    {
        Config::set('ai.langsearch.api_key_backup', null);
        
        $service = new LangSearchService();
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('apiKeys');
        $property->setAccessible(true);
        
        $apiKeys = $property->getValue($service);
        
        $this->assertCount(1, $apiKeys);
        $this->assertEquals('test-api-key', $apiKeys[0]);
    }

    public function test_service_returns_empty_when_not_configured(): void
    {
        Config::set('ai.langsearch.api_key', null);
        Config::set('ai.langsearch.api_key_backup', null);
        
        $service = new LangSearchService();
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isConfigured');
        $method->setAccessible(true);
        
        $this->assertFalse($method->invoke($service));
    }

    public function test_search_returns_empty_array_when_no_api_key(): void
    {
        Config::set('ai.langsearch.api_key', null);
        Config::set('ai.langsearch.api_key_backup', null);
        
        $service = new LangSearchService();
        
        $result = $service->search('test query');
        
        $this->assertEquals([], $result);
    }

    public function test_search_success_with_http_fake(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/web-search' => Http::response([
                'data' => [
                    'webPages' => [
                        'value' => [
                            [
                                'name' => 'Test Article',
                                'snippet' => 'Test description',
                                'url' => 'https://example.com',
                                'datePublished' => '2026-04-26',
                            ],
                            [
                                'name' => 'Another Article',
                                'snippet' => 'Another description',
                                'url' => 'https://example.org',
                                'datePublished' => '2026-04-25',
                            ],
                        ]
                    ]
                ]
            ], 200),
        ]);
        
        $service = new LangSearchService();
        $results = $service->search('test query');
        
        $this->assertCount(2, $results);
        $this->assertEquals('Test Article', $results[0]['title']);
        $this->assertEquals('https://example.com', $results[0]['url']);
        $this->assertEquals('Another Article', $results[1]['title']);
    }

    public function test_search_fallback_to_backup_key(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/web-search' => function ($request) {
                $auth = $request->header('Authorization')[0] ?? '';
                if (str_contains($auth, 'test-api-key')) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }
                return Http::response([
                    'data' => [
                        'webPages' => [
                            'value' => [
                                [
                                    'name' => 'Backup Result',
                                    'snippet' => 'From backup key',
                                    'url' => 'https://backup.example.com',
                                ]
                            ]
                        ]
                    ]
                ], 200);
            },
        ]);
        
        $service = new LangSearchService();
        $results = $service->search('fallback test');
        
        $this->assertCount(1, $results);
        $this->assertEquals('Backup Result', $results[0]['title']);
    }

    public function test_search_retry_on_429_error(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/web-search' => function ($request) {
                static $attempts = 0;
                $attempts++;
                if ($attempts === 1) {
                    return Http::response(['error' => 'Rate limited'], 429);
                }
                return Http::response([
                    'data' => [
                        'webPages' => [
                            'value' => [
                                [
                                    'name' => 'Retry Success',
                                    'snippet' => 'After retry',
                                    'url' => 'https://retry.example.com',
                                ]
                            ]
                        ]
                    ]
                ], 200);
            },
        ]);
        
        $service = new LangSearchService();
        $results = $service->search('retry test');
        
        $this->assertCount(1, $results);
        $this->assertEquals('Retry Success', $results[0]['title']);
    }

    public function test_search_returns_empty_on_server_error(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/web-search' => Http::response(['error' => 'Server Error'], 500),
        ]);
        
        $service = new LangSearchService();
        $results = $service->search('error test');
        
        $this->assertEquals([], $results);
    }

    public function test_rerank_returns_null_for_single_document(): void
    {
        $service = new LangSearchService();
        
        $result = $service->rerank('query', ['single document']);
        
        $this->assertNull($result);
    }

    public function test_rerank_success_with_http_fake(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/rerank' => Http::response([
                'results' => [
                    [
                        'index' => 1,
                        'document' => ['url' => 'https://example.com', 'text' => 'Second doc'],
                        'relevance_score' => 0.95,
                    ],
                    [
                        'index' => 0,
                        'document' => ['url' => 'https://other.com', 'text' => 'First doc'],
                        'relevance_score' => 0.85,
                    ],
                ]
            ], 200),
        ]);
        
        $service = new LangSearchService();
        $documents = [
            ['url' => 'https://other.com', 'text' => 'First doc'],
            ['url' => 'https://example.com', 'text' => 'Second doc'],
        ];
        
        $results = $service->rerank('test query', $documents, 2);
        
        $this->assertNotNull($results);
        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]['index']);
    }

    public function test_rerank_normalizes_search_results_before_request(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/rerank' => function ($request) {
                $this->assertSame('test query', $request['query']);
                $this->assertSame('langsearch-reranker-v1', $request['model']);
                $this->assertSame([
                    [
                        'text' => "Artikel Pertama\n\nRingkasan pertama",
                        'url' => 'https://example.com/first',
                    ],
                    [
                        'text' => "Artikel Kedua\n\nRingkasan kedua",
                        'url' => 'https://example.com/second',
                    ],
                ], $request['documents']);

                return Http::response([
                    'results' => [
                        [
                            'index' => 1,
                            'document' => [
                                'url' => 'https://example.com/second',
                                'text' => "Artikel Kedua\n\nRingkasan kedua",
                            ],
                            'relevance_score' => 0.97,
                        ],
                    ],
                ], 200);
            },
        ]);

        $service = new LangSearchService();
        $results = $service->rerank('test query', [
            [
                'title' => 'Artikel Pertama',
                'snippet' => 'Ringkasan pertama',
                'url' => 'https://example.com/first',
            ],
            [
                'title' => 'Artikel Kedua',
                'snippet' => 'Ringkasan kedua',
                'url' => 'https://example.com/second',
            ],
        ], 2);

        $this->assertNotNull($results);
        $this->assertCount(1, $results);
        $this->assertEquals('https://example.com/second', $results[0]['document']['url']);
    }

    public function test_rerank_error_returns_null(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/rerank' => Http::response(['error' => 'Server Error'], 500),
        ]);
        
        $service = new LangSearchService();
        $documents = [
            ['url' => 'https://a.com', 'text' => 'Doc A'],
            ['url' => 'https://b.com', 'text' => 'Doc B'],
        ];
        
        $results = $service->rerank('test query', $documents);
        
        $this->assertNull($results);
    }

    public function test_rerank_fallback_to_backup_key(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/rerank' => function ($request) {
                $auth = $request->header('Authorization')[0] ?? '';
                if (str_contains($auth, 'test-api-key')) {
                    return Http::response(['code' => 401, 'msg' => 'Unauthorized'], 401);
                }
                return Http::response([
                    'results' => [
                        [
                            'index' => 0,
                            'document' => ['url' => 'https://backup-rerank.com'],
                            'relevance_score' => 0.9,
                        ],
                    ]
                ], 200);
            },
        ]);
        
        $service = new LangSearchService();
        $documents = [
            ['url' => 'https://a.com', 'text' => 'Doc A'],
            ['url' => 'https://b.com', 'text' => 'Doc B'],
        ];
        
        $results = $service->rerank('backup test', $documents);
        
        $this->assertNotNull($results);
        $this->assertCount(1, $results);
    }

    public function test_build_search_context_returns_empty_for_empty_results(): void
    {
        $service = new LangSearchService();
        
        $result = $service->buildSearchContext([]);
        
        $this->assertEquals('', $result);
    }

    public function test_build_search_context_formats_results_correctly(): void
    {
        $service = new LangSearchService();
        
        $results = [
            [
                'title' => 'Test Article',
                'snippet' => 'Test description',
                'url' => 'https://example.com',
                'datePublished' => '2026-04-26',
            ],
            [
                'title' => 'Another Article',
                'snippet' => 'Another description',
                'url' => 'https://example.org',
                'datePublished' => '2026-04-25',
            ],
        ];
        
        $result = $service->buildSearchContext($results);
        
        $this->assertStringContainsString('Hasil 1:', $result);
        $this->assertStringContainsString('Test Article', $result);
        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('Hasil 2:', $result);
        $this->assertStringContainsString('Another Article', $result);
    }

    public function test_cache_key_includes_freshness_and_count(): void
    {
        Http::fake([
            'https://api.langsearch.com/v1/web-search' => Http::sequence()
                ->push([
                    'data' => [
                        'webPages' => [
                            'value' => [
                                ['name' => 'OneDay Result', 'snippet' => 'Fresh', 'url' => 'https://oneday.com'],
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'data' => [
                        'webPages' => [
                            'value' => [
                                ['name' => 'OneWeek Result', 'snippet' => 'Week old', 'url' => 'https://oneweek.com'],
                            ]
                        ]
                    ]
                ], 200)
                ->push([
                    'data' => [
                        'webPages' => [
                            'value' => [
                                ['name' => 'Different Result', 'snippet' => 'Different', 'url' => 'https://other.com'],
                            ]
                        ]
                    ]
                ], 200),
        ]);
        
        $service = new LangSearchService();
        
        $results1 = $service->search('test query', 'oneDay', 5);
        $this->assertEquals('OneDay Result', $results1[0]['title']);
        
        $results2 = $service->search('test query', 'oneWeek', 10);
        $this->assertEquals('OneWeek Result', $results2[0]['title']);
        
        $results3 = $service->search('test query', 'oneWeek', 10);
        $this->assertEquals('OneWeek Result', $results3[0]['title']);
        
        $results4 = $service->search('test query', 'oneDay', 5);
        $this->assertEquals('OneDay Result', $results4[0]['title']);
        
        $results5 = $service->search('test query', 'oneMonth', 3);
        $this->assertEquals('Different Result', $results5[0]['title']);
    }
}
