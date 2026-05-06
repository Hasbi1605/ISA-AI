<?php

namespace App\Services\Memo;

use App\Models\Memo;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MemoGenerationService
{
    protected string $baseUrl;

    protected ?string $token;

    protected int $connectTimeout;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ai_document_service.url', 'http://127.0.0.1:8001'), '/');
        $this->token = config('services.ai_document_service.token');
        $this->connectTimeout = max(1, (int) config('services.ai_document_service.connect_timeout', 10));
        $this->timeout = max(1, (int) config('services.ai_document_service.timeout', 120));
    }

    /**
     * @param  array<int, int>  $sourceDocumentIds
     */
    public function generate(User $user, string $memoType, string $title, string $context, array $sourceDocumentIds = [], array $configuration = []): Memo
    {
        $configuration = $this->normalizeConfiguration($configuration);

        $response = Http::withToken($this->token ?: '')
            ->accept('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->asJson()
            ->post($this->baseUrl.'/api/memos/generate-body', [
                'memo_type' => $memoType,
                'title' => $title,
                'context' => $context,
                'configuration' => $configuration,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->body() ?: 'Gagal membuat draft memo.');
        }

        $memo = Memo::create([
            'user_id' => $user->id,
            'title' => $title,
            'memo_type' => $memoType,
            'status' => Memo::STATUS_GENERATED,
            'source_document_ids' => array_values(array_unique(array_map('intval', $sourceDocumentIds))),
            'configuration' => $configuration,
            'searchable_text' => $this->normalizeSearchableText(
                $response->header('X-Memo-Searchable-Text-B64') ?: $response->header('X-Memo-Searchable-Text'),
                $title,
                $context,
            ),
        ]);

        $path = 'memos/'.$user->id.'/'.$memo->id.'-'.Str::uuid().'.docx';
        Storage::disk('local')->put($path, $response->body());

        $memo->forceFill(['file_path' => $path])->save();

        return $memo;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, string>
     */
    protected function normalizeConfiguration(array $configuration): array
    {
        $allowedKeys = [
            'number',
            'recipient',
            'sender',
            'subject',
            'date',
            'basis',
            'content',
            'closing',
            'signatory',
            'carbon_copy',
            'page_size',
            'page_size_mode',
            'additional_instruction',
            'revision_instruction',
        ];

        $normalized = [];

        foreach ($allowedKeys as $key) {
            $value = trim((string) ($configuration[$key] ?? ''));

            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    protected function normalizeSearchableText(?string $headerText, string $title, string $context): string
    {
        $encodedText = trim((string) $headerText);
        $decodedText = $encodedText !== '' ? base64_decode(strtr($encodedText, '-_', '+/'), true) : false;
        $text = is_string($decodedText) ? trim($decodedText) : $encodedText;

        return $text !== '' ? $text : trim($title."\n".$context);
    }
}
