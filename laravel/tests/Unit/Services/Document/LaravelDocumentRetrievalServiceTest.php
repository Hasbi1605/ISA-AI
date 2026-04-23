<?php

namespace Tests\Unit\Services\Document;

use App\Services\Document\LaravelDocumentRetrievalService;
use Illuminate\Support\Facades\Config;
use Laravel\Ai\AiManager;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\EmbeddingsResponse;
use Mockery;
use Tests\TestCase;

class LaravelDocumentRetrievalServiceTest extends TestCase
{
    public function test_calculate_similarity_uses_sdk_embeddings_api_shape(): void
    {
        Config::set('ai.rag.embedding_model', 'text-embedding-3-small');
        Config::set('ai.rag.embedding_dimensions', 1536);

        $this->app->instance(AiManager::class, Mockery::mock(AiManager::class));

        $service = new LaravelDocumentRetrievalService();

        $provider = new class
        {
            public array $inputs = [];
            public ?int $dimensions = null;
            public ?string $model = null;

            public function embeddings(array $inputs, ?int $dimensions = null, ?string $model = null, int $timeout = 30): EmbeddingsResponse
            {
                $this->inputs = $inputs;
                $this->dimensions = $dimensions;
                $this->model = $model;

                return new EmbeddingsResponse(
                    embeddings: [[1.0, 0.0], [1.0, 0.0]],
                    tokens: 2,
                    meta: new Meta(provider: 'test', model: 'embedding-model')
                );
            }
        };

        $score = $this->invokeCalculateSimilarity($service, 'query contoh', 'konten contoh', $provider);

        $this->assertSame(['query contoh', 'konten contoh'], $provider->inputs);
        $this->assertSame(1536, $provider->dimensions);
        $this->assertSame('text-embedding-3-small', $provider->model);
        $this->assertEquals(1.0, $score);
    }

    public function test_calculate_similarity_falls_back_to_lexical_overlap_when_embeddings_fail(): void
    {
        $this->app->instance(AiManager::class, Mockery::mock(AiManager::class));

        $service = new LaravelDocumentRetrievalService();

        $provider = new class
        {
            public function embeddings(array $inputs, ?int $dimensions = null, ?string $model = null, int $timeout = 30): EmbeddingsResponse
            {
                throw new \RuntimeException('Embeddings unavailable');
            }
        };

        $score = $this->invokeCalculateSimilarity($service, 'apel mangga', 'apel jeruk', $provider);

        $this->assertGreaterThan(0.0, $score);
        $this->assertLessThan(1.0, $score);
    }

    private function invokeCalculateSimilarity(
        LaravelDocumentRetrievalService $service,
        string $query,
        string $content,
        object $provider
    ): float {
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculateSimilarity');
        $method->setAccessible(true);

        return $method->invoke($service, $query, $content, $provider);
    }
}
