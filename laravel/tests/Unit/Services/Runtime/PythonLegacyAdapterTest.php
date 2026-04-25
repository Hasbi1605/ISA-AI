<?php

namespace Tests\Unit\Services\Runtime;

use App\Services\Runtime\PythonLegacyAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ReflectionClass;
use Tests\TestCase;

class PythonLegacyAdapterTest extends TestCase
{
    public function test_chat_normalizes_messages_before_calling_python(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], 'ok'),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $adapter = new PythonLegacyAdapter();
        $reflection = new ReflectionClass($adapter);
        $client = $reflection->getProperty('client');
        $client->setAccessible(true);
        $client->setValue($adapter, new Client(['handler' => $stack]));

        $chunks = '';
        foreach ($adapter->chat([
            [
                'id' => 123,
                'conversation_id' => 456,
                'role' => 'user',
                'content' => 'Halo',
                'is_error' => false,
                'created_at' => '2026-04-25T12:37:34Z',
            ],
        ]) as $chunk) {
            $chunks .= $chunk;
        }

        $payload = json_decode((string) $history[0]['request']->getBody(), true);

        $this->assertSame('ok', $chunks);
        $this->assertSame([
            [
                'role' => 'user',
                'content' => 'Halo',
            ],
        ], $payload['messages']);
    }
}
