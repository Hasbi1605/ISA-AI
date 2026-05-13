<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AIService
{
    /**
     * Sentinel prefix prepended to error chunks yielded by sendChat().
     * GenerateChatResponse detects this prefix to store the message with
     * is_error = true instead of treating it as a normal AI answer.
     */
    public const ERROR_SENTINEL = '[ISTA_AI_ERROR]';

    protected $client;
    protected $baseUrl;
    protected $documentBaseUrl;
    protected $token;
    protected $maxRetries;
    protected $retryDelayMs;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            $this->normalizeStringConfig(config('services.ai_service.url'), 'http://127.0.0.1:8001'),
            '/'
        );
        $this->documentBaseUrl = rtrim(
            $this->normalizeStringConfig(config('services.ai_document_service.url'), $this->baseUrl),
            '/'
        );
        $this->token = $this->normalizeStringConfig(config('services.ai_service.token'));
        $this->maxRetries = max(1, $this->normalizeIntConfig(config('services.ai_service.retries'), 2));
        $this->retryDelayMs = max(0, $this->normalizeIntConfig(config('services.ai_service.retry_delay_ms'), 400));

        $this->client = new Client([
            'connect_timeout' => $this->normalizeFloatConfig(config('services.ai_service.connect_timeout'), 10),
            'timeout' => $this->normalizeFloatConfig(config('services.ai_service.timeout'), 120),
            'read_timeout' => $this->normalizeFloatConfig(config('services.ai_service.read_timeout'), 120),
        ]);
    }

    /**
     * Send a list of messages to the Python AI service and stream the response.
     *
     * @param array $messages
     * @param array|null $document_filenames Optional document filenames for RAG mode
     * @param string|null $user_id User ID for authorization in RAG mode
     * @return \Generator
     */
    public function sendChat(
        array $messages,
        ?array $document_filenames = null,
        ?string $user_id = null,
        bool $force_web_search = false,
        ?string $source_policy = null,
        bool $allow_auto_realtime_web = true
    ) {
        $payload = [
            'messages' => $messages,
            'force_web_search' => $force_web_search,
            'allow_auto_realtime_web' => $allow_auto_realtime_web,
        ];

        if ($source_policy !== null) {
            $payload['source_policy'] = $source_policy;
        }

        if ($document_filenames !== null) {
            $payload['document_filenames'] = $document_filenames;
        }

        if ($user_id !== null) {
            $payload['user_id'] = $user_id;
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = $this->client->post($this->baseUrl . '/api/chat', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->token,
                        'Accept' => 'text/event-stream',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                    'stream' => true,
                ]);

                $body = $response->getBody();

                while (!$body->eof()) {
                    yield $body->read(1024);
                }

                return;
            } catch (RequestException $e) {
                $response = $e->getResponse();
                $responseBody = $response?->getBody()?->getContents();

                Log::warning('AI Service Error', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'base_url' => $this->baseUrl,
                    'status' => $response?->getStatusCode(),
                    'message' => $e->getMessage(),
                    'response_excerpt' => $responseBody ? mb_substr($responseBody, 0, 500) : null,
                ]);

                if ($attempt >= $this->maxRetries) {
                    Log::error('AI Service Error: max retries reached', [
                        'base_url' => $this->baseUrl,
                        'status' => $response?->getStatusCode(),
                        'message' => $e->getMessage(),
                        'response_excerpt' => $responseBody ? mb_substr($responseBody, 0, 500) : null,
                    ]);
                    yield self::ERROR_SENTINEL."❌ Kesalahan sistem saat menghubungi otak AI. Silakan coba lagi nanti.";
                    return;
                }

                if ($this->retryDelayMs > 0) {
                    usleep($this->retryDelayMs * 1000);
                }
            } catch (\Throwable $e) {
                Log::error('Unexpected AI Service Error', [
                    'message' => $e->getMessage(),
                ]);
                yield self::ERROR_SENTINEL."❌ Kesalahan sistem saat menghubungi otak AI. Silakan coba lagi nanti.";
                return;
            }
        }
    }

    /**
     * Summarize a document.
     *
     * @param string $filename
     * @param string|null $user_id User ID for authorization
     * @return array
     */
    public function summarizeDocument(string $filename, ?string $user_id = null): array
    {
        try {
            $payload = [
                'filename' => $filename,
            ];

            if ($user_id !== null) {
                $payload['user_id'] = $user_id;
            }

            $response = $this->client->post($this->documentBaseUrl . '/api/documents/summarize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('AI Service Summarize Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Gagal merangkum dokumen: ' . $e->getMessage()
            ];
        }
    }

    private function normalizeStringConfig(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        $normalized = trim((string) $value);

        if (strlen($normalized) >= 2) {
            $quote = $normalized[0];
            if (($quote === '"' || $quote === "'") && $normalized[strlen($normalized) - 1] === $quote) {
                $normalized = substr($normalized, 1, -1);
            }
        }

        return $normalized === '' ? $default : $normalized;
    }

    private function normalizeIntConfig(mixed $value, int $default): int
    {
        $normalized = $this->normalizeStringConfig($value, (string) $default);
        return is_numeric($normalized) ? (int) $normalized : $default;
    }

    private function normalizeFloatConfig(mixed $value, float $default): float
    {
        $normalized = $this->normalizeStringConfig($value, (string) $default);
        return is_numeric($normalized) ? (float) $normalized : $default;
    }
}
