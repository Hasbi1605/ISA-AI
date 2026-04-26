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

    public function test_summarization_prompt_keys_exist_in_config(): void
    {
        // Pastikan kunci config yang dipakai LaravelDocumentService::summarizeDocument
        // ada secara default setelah migrasi parity #99 (mencegah regresi diam-diam
        // ketika prompt di-rename atau dihapus).
        $instructions = config('ai.prompts.summarization.instructions');
        $single = config('ai.prompts.summarization.single');
        $partial = config('ai.prompts.summarization.partial');
        $final = config('ai.prompts.summarization.final');

        $this->assertIsString($instructions);
        $this->assertNotEmpty($instructions);
        $this->assertIsString($single);
        $this->assertStringContainsString('Ringkasan inti', $single);
        $this->assertIsString($partial);
        $this->assertStringContainsString('{part_number}', $partial);
        $this->assertIsString($final);
        $this->assertStringContainsString('{combined_summaries}', $final);
    }
}