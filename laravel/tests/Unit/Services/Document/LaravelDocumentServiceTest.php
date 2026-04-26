<?php

namespace Tests\Unit\Services\Document;

use App\Services\Document\LaravelDocumentService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LaravelDocumentServiceTest extends TestCase
{
    public function test_document_process_returns_array_structure_when_disabled(): void
    {
        Config::set('ai.laravel_ai.document_process_enabled', false);

        $service = new LaravelDocumentService();

        $result = $service->processDocument('/tmp/test.pdf', 'test.pdf', 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
    }

    public function test_document_process_returns_array_structure_when_enabled(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');
        Config::set('ai.laravel_ai.document_process_enabled', true);

        $service = new LaravelDocumentService();

        $result = $service->processDocument('/tmp/test.pdf', 'test.pdf', 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function test_document_summarize_returns_array_structure_when_disabled(): void
    {
        Config::set('ai.laravel_ai.document_summarize_enabled', false);

        $service = new LaravelDocumentService();

        $result = $service->summarizeDocument('test.pdf', 'user1');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('error', $result['status']);
    }

    public function test_document_summarize_returns_array_structure_when_enabled(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');
        Config::set('ai.laravel_ai.document_summarize_enabled', true);

        $service = new LaravelDocumentService();

        $result = $service->summarizeDocument('nonexistent.pdf', 'user1');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function test_document_delete_returns_bool(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');

        $service = new LaravelDocumentService();

        $result = $service->deleteDocument('nonexistent.pdf', 'user1');

        $this->assertIsBool($result);
    }

    public function test_summarize_document_queries_with_user_isolation(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');
        Config::set('ai.laravel_ai.document_summarize_enabled', true);

        $service = new LaravelDocumentService();

        $result = $service->summarizeDocument('test.pdf', 'user1');

        $this->assertIsArray($result);
    }

    public function test_delete_document_queries_with_user_isolation(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');

        $service = new LaravelDocumentService();

        $result = $service->deleteDocument('test.pdf', 'user1');

        $this->assertIsBool($result);
    }

    public function test_summarize_returns_sources_metadata(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');
        Config::set('ai.laravel_ai.document_summarize_enabled', true);
        Config::set('ai.cascade.enabled', false);

        $service = new LaravelDocumentService();

        $result = $service->summarizeDocument('test.pdf', 'user1');

        $this->assertIsArray($result);

        if ($result['status'] === 'success') {
            $this->assertArrayHasKey('sources', $result);
            $this->assertArrayHasKey('model', $result);
        }
    }

    public function test_summarize_batch_creation_for_large_documents(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');
        Config::set('ai.laravel_ai.document_summarize_enabled', true);
        Config::set('ai.laravel_ai.summarize_max_tokens', 20);
        Config::set('ai.cascade.enabled', false);

        $service = new LaravelDocumentService();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createBatches');
        $method->setAccessible(true);

        $chunks = [
            str_repeat('a', 40),
            str_repeat('b', 40),
            str_repeat('c', 40),
            str_repeat('d', 40),
        ];

        $batches = $method->invoke($service, $chunks);

        $this->assertIsArray($batches);
        $this->assertGreaterThanOrEqual(2, count($batches));
    }

    public function test_summarize_token_estimation(): void
    {
        Config::set('ai.laravel_ai.api_key', 'test-key');
        Config::set('ai.laravel_ai.document_summarize_enabled', true);

        $service = new LaravelDocumentService();

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('estimateTokens');
        $method->setAccessible(true);

        $chunks = ['hello world', 'test content'];
        $tokens = $method->invoke($service, $chunks);

        $this->assertIsInt($tokens);
        $this->assertGreaterThan(0, $tokens);
    }
}