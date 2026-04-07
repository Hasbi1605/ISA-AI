<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $client;
    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->client = new Client();
        $this->baseUrl = config('app.ai_service_url', 'http://localhost:8001');
        $this->token = config('app.ai_service_token');
    }

    /**
     * Send a list of messages to the Python AI service and stream the response.
     *
     * @param array $messages
     * @param array|null $document_filenames Optional document filenames for RAG mode
     * @return \Generator
     */
    public function sendChat(array $messages, ?array $document_filenames = null)
    {
        try {
            $payload = [
                'messages' => $messages,
            ];
            
            if ($document_filenames !== null) {
                $payload['document_filenames'] = $document_filenames;
            }
            
            $response = $this->client->post($this->baseUrl . '/api/chat', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'text/event-stream',
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'stream' => True,
            ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                yield $body->read(1024);
            }

        } catch (RequestException $e) {
            Log::error('AI Service Error: ' . $e->getMessage());
            yield "❌ Kesalahan sistem saat menghubungi otak AI. Silakan coba lagi nanti.";
        }
    }
    
    /**
     * Summarize a document.
     *
     * @param string $filename
     * @return array
     */
    public function summarizeDocument(string $filename): array
    {
        try {
            $response = $this->client->post($this->baseUrl . '/api/documents/summarize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'filename' => $filename,
                ],
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
}
