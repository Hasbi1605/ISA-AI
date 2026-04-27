<?php

namespace Tests\Unit;

use App\Services\AIService;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    public function test_ai_service_normalizes_quoted_runtime_config_values(): void
    {
        config()->set('services.ai_service.url', ' "http://python-ai:8001/" ');
        config()->set('services.ai_service.token', " 'internal-token' ");
        config()->set('services.ai_service.retries', ' "3" ');
        config()->set('services.ai_service.retry_delay_ms', " '450' ");
        config()->set('services.ai_service.connect_timeout', ' "11.5" ');
        config()->set('services.ai_service.timeout', " '121.5' ");
        config()->set('services.ai_service.read_timeout', ' "122.5" ');

        $service = new AIService();
        $reflection = new \ReflectionClass($service);

        $baseUrl = $reflection->getProperty('baseUrl');
        $baseUrl->setAccessible(true);
        $token = $reflection->getProperty('token');
        $token->setAccessible(true);
        $maxRetries = $reflection->getProperty('maxRetries');
        $maxRetries->setAccessible(true);
        $retryDelayMs = $reflection->getProperty('retryDelayMs');
        $retryDelayMs->setAccessible(true);
        $client = $reflection->getProperty('client');
        $client->setAccessible(true);

        $this->assertSame('http://python-ai:8001', $baseUrl->getValue($service));
        $this->assertSame('internal-token', $token->getValue($service));
        $this->assertSame(3, $maxRetries->getValue($service));
        $this->assertSame(450, $retryDelayMs->getValue($service));

        $clientConfig = $client->getValue($service)->getConfig();

        $this->assertSame(11.5, $clientConfig['connect_timeout']);
        $this->assertSame(121.5, $clientConfig['timeout']);
        $this->assertSame(122.5, $clientConfig['read_timeout']);
    }
}
