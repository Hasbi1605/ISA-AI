<?php

namespace App\Livewire\Memos;

use App\Models\Memo;
use App\Services\Memo\MemoGenerationService;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

class MemoWorkspace extends Component
{
    private const DRAFT_THREAD_KEY = 'draft';

    public ?int $activeMemoId = null;

    public string $memoType = 'memo_internal';

    public string $title = '';

    public string $memoPrompt = '';

    public string $memoNumber = '';

    public string $memoRecipient = '';

    public string $memoSender = 'Kepala Istana Kepresidenan Yogyakarta';

    public string $memoDate = '';

    public string $memoBasis = '';

    public string $memoContent = '';

    public string $memoClosing = '';

    public string $memoSignatory = 'Deni Mulyana';

    public string $memoCarbonCopy = '';

    public string $memoPageSize = 'folio';

    public string $memoAdditionalInstruction = '';

    public array $memoChatMessages = [];

    public array $memoChatThreads = [];

    public bool $isGenerating = false;

    public bool $showMemoConfiguration = true;

    public string $previewMode = 'preview'; // 'preview' or 'editor'

    public ?string $previewHtml = null;

    public function mount(): void
    {
        $this->resetMemoConfiguration();
        $this->addSystemMessage('Lengkapi konfigurasi memo terlebih dahulu. Setelah draft dibuat, kolom revisi akan aktif di panel yang sama.');
    }

    public function loadMemo(int $memoId): void
    {
        $this->rememberCurrentThread();

        $memo = Memo::where('id', $memoId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $memo) {
            return;
        }

        $this->activeMemoId = $memo->id;
        $this->memoType = $memo->memo_type;
        $this->title = $memo->title;
        $this->applyMemoConfiguration($memo->configuration ?? []);
        $this->previewHtml = $memo->searchable_text ? nl2br(e($memo->searchable_text)) : null;
        $this->previewMode = 'preview';
        $this->showMemoConfiguration = false;
        $this->memoPrompt = '';

        $threadKey = $this->threadKey($memo->id);

        if (! empty($this->memoChatThreads[$threadKey])) {
            $this->memoChatMessages = $this->memoChatThreads[$threadKey];

            return;
        }

        $storedThread = $this->normalizeStoredThread($memo->chat_messages ?? []);

        if ($storedThread !== []) {
            $this->memoChatMessages = $storedThread;
            $this->rememberCurrentThread();

            return;
        }

        $this->memoChatMessages = [
            $this->makeSystemMessage("Memo \"{$memo->title}\" dimuat. Anda bisa meminta revisi atau generate ulang."),
        ];
        $this->rememberCurrentThread();
    }

    public function startNewMemo(): void
    {
        $this->rememberCurrentThread();

        $this->activeMemoId = null;
        $this->memoPrompt = '';
        $this->memoChatMessages = [];
        $this->previewHtml = null;
        $this->isGenerating = false;
        $this->resetMemoConfiguration();
        $this->previewMode = 'preview';
        $this->showMemoConfiguration = true;
        $this->addSystemMessage('Lengkapi konfigurasi memo baru. Saya akan menjaga struktur, gaya bahasa, dan format memorandum mengikuti contoh manual.');
    }

    public function generateConfiguredMemo(): void
    {
        $this->validateMemoConfiguration();

        $this->memoChatMessages[] = [
            'role' => 'user',
            'content' => $this->memoConfigurationSummary(),
            'timestamp' => now()->format('H:i'),
        ];
        $this->rememberCurrentThread();

        $this->generateFromChat($this->memoDraftContext(), true);
    }

    public function sendMemoChat(): void
    {
        $this->validate([
            'memoPrompt' => 'required|string|min:1',
        ]);

        $userMessage = trim($this->memoPrompt);
        $this->memoPrompt = '';

        $this->memoChatMessages[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->format('H:i'),
        ];
        $this->rememberCurrentThread();

        if ($this->activeMemoId) {
            $this->generateFromChat($userMessage);
        } else {
            $this->memoContent = trim($this->memoContent."\n".$userMessage);
            $this->addSystemMessage('Saya simpan instruksi itu sebagai isi/poin memo. Lengkapi konfigurasi utama, lalu tekan Generate Memo.');
        }
    }

    public function generateFromChat(string $context, bool $fromConfiguration = false): void
    {
        $this->validateMemoConfiguration();

        $this->isGenerating = true;

        try {
            $generationService = app(MemoGenerationService::class);
            $configuration = $this->memoConfigurationPayload();

            $memo = $generationService->generate(
                Auth::user(),
                $this->memoType,
                $this->title,
                $context,
                [],
                $configuration,
            );

            $this->activeMemoId = $memo->id;
            $this->previewHtml = $memo->searchable_text ? nl2br(e($memo->searchable_text)) : '<p class="text-stone-500">Draft berhasil digenerate. Gunakan tab Editor untuk melihat dokumen lengkap.</p>';
            $this->previewMode = 'preview';
            $this->showMemoConfiguration = false;
            $this->rememberCurrentThread();

            $message = $fromConfiguration
                ? "Draft memo \"{$memo->title}\" berhasil digenerate dari konfigurasi. Anda bisa meminta revisi spesifik di sini."
                : "Revisi memo \"{$memo->title}\" berhasil digenerate. Cek preview atau Editor untuk memastikan hasilnya sudah sesuai.";

            $this->addSystemMessage($message);
        } catch (\Throwable $e) {
            $this->addSystemMessage('Maaf, terjadi kesalahan saat generate memo: '.$e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    public function regenerate(): void
    {
        if (! $this->activeMemoId) {
            $this->generateConfiguredMemo();

            return;
        }

        $lastUserMessage = collect($this->memoChatMessages)
            ->where('role', 'user')
            ->last();

        $context = $lastUserMessage['content'] ?? $this->memoDraftContext();
        $this->generateFromChat($context);
    }

    public function switchPreviewMode(string $mode): void
    {
        $this->previewMode = in_array($mode, ['preview', 'editor']) ? $mode : 'preview';
    }

    public function editorConfig(): ?array
    {
        if (! $this->activeMemoId) {
            return null;
        }

        $memo = Memo::where('id', $this->activeMemoId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $memo || ! $memo->file_path) {
            return null;
        }

        $signer = app(JwtSigner::class);
        $laravelInternalUrl = rtrim((string) config('services.onlyoffice.laravel_internal_url', config('app.url')), '/');
        $ttlMinutes = max(1, (int) config('services.onlyoffice.signed_url_ttl_minutes', 30));
        $documentPath = URL::temporarySignedRoute('memos.file.signed', now()->addMinutes($ttlMinutes), $memo, false);
        $callbackPath = route('onlyoffice.callback', $memo, false);

        $config = [
            'document' => [
                'fileType' => 'docx',
                'key' => 'memo-'.$memo->id.'-'.$memo->updated_at?->timestamp,
                'title' => $memo->title.'.docx',
                'url' => $laravelInternalUrl.$documentPath,
            ],
            'documentType' => 'word',
            'editorConfig' => [
                'callbackUrl' => $laravelInternalUrl.$callbackPath,
                'mode' => 'edit',
                'lang' => 'id',
                'user' => [
                    'id' => (string) Auth::id(),
                    'name' => (string) Auth::user()?->name,
                ],
            ],
        ];

        $config['token'] = $signer->sign($config);

        return $config;
    }

    public function render()
    {
        $memos = Memo::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return view('livewire.memos.memo-workspace', [
            'memos' => $memos,
            'memoTypes' => Memo::TYPES,
            'memoPageSizes' => $this->memoPageSizes(),
            'editorConfig' => $this->previewMode === 'editor' ? $this->editorConfig() : null,
            'onlyOfficeApiUrl' => rtrim((string) config('services.onlyoffice.public_url', ''), '/').'/web-apps/apps/api/documents/api.js',
        ]);
    }

    protected function validateMemoConfiguration(): void
    {
        $this->validate([
            'memoType' => ['required', 'in:'.implode(',', array_keys(Memo::TYPES))],
            'memoNumber' => ['required', 'string', 'max:80'],
            'memoRecipient' => ['required', 'string', 'max:500'],
            'memoSender' => ['required', 'string', 'max:240'],
            'title' => ['required', 'string', 'max:160'],
            'memoDate' => ['required', 'string', 'max:80'],
            'memoBasis' => ['nullable', 'string', 'max:4000'],
            'memoContent' => ['required', 'string', 'min:8', 'max:8000'],
            'memoClosing' => ['nullable', 'string', 'max:800'],
            'memoSignatory' => ['required', 'string', 'max:160'],
            'memoCarbonCopy' => ['nullable', 'string', 'max:2000'],
            'memoPageSize' => ['required', 'in:folio,letter'],
            'memoAdditionalInstruction' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function memoConfigurationPayload(): array
    {
        return [
            'number' => trim($this->memoNumber),
            'recipient' => trim($this->memoRecipient),
            'sender' => trim($this->memoSender),
            'subject' => trim($this->title),
            'date' => trim($this->memoDate),
            'basis' => trim($this->memoBasis),
            'content' => trim($this->memoContent),
            'closing' => trim($this->memoClosing),
            'signatory' => trim($this->memoSignatory),
            'carbon_copy' => trim($this->memoCarbonCopy),
            'page_size' => trim($this->memoPageSize),
            'additional_instruction' => trim($this->memoAdditionalInstruction),
        ];
    }

    protected function memoDraftContext(): string
    {
        $sections = [];

        if (trim($this->memoBasis) !== '') {
            $sections[] = "Dasar/konteks:\n".trim($this->memoBasis);
        }

        $sections[] = "Isi/poin wajib:\n".trim($this->memoContent);

        if (trim($this->memoAdditionalInstruction) !== '') {
            $sections[] = "Catatan tambahan:\n".trim($this->memoAdditionalInstruction);
        }

        return implode("\n\n", $sections);
    }

    protected function memoConfigurationSummary(): string
    {
        $summary = [
            'Konfigurasi memo:',
            'Nomor: '.trim($this->memoNumber),
            'Yth.: '.trim($this->memoRecipient),
            'Dari: '.trim($this->memoSender),
            'Hal: '.trim($this->title),
            'Tanggal: '.trim($this->memoDate),
            'Penandatangan: '.trim($this->memoSignatory),
        ];

        if (trim($this->memoCarbonCopy) !== '') {
            $summary[] = "Tembusan:\n".trim($this->memoCarbonCopy);
        }

        return implode("\n", $summary);
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    protected function applyMemoConfiguration(array $configuration): void
    {
        $this->memoNumber = (string) ($configuration['number'] ?? $this->memoNumber);
        $this->memoRecipient = (string) ($configuration['recipient'] ?? $this->memoRecipient);
        $this->memoSender = (string) ($configuration['sender'] ?? $this->memoSender);
        $this->title = (string) ($configuration['subject'] ?? $this->title);
        $this->memoDate = (string) ($configuration['date'] ?? $this->memoDate);
        $this->memoBasis = (string) ($configuration['basis'] ?? $this->memoBasis);
        $this->memoContent = (string) ($configuration['content'] ?? $this->memoContent);
        $this->memoClosing = (string) ($configuration['closing'] ?? $this->memoClosing);
        $this->memoSignatory = (string) ($configuration['signatory'] ?? $this->memoSignatory);
        $this->memoCarbonCopy = (string) ($configuration['carbon_copy'] ?? $this->memoCarbonCopy);
        $this->memoPageSize = in_array(($configuration['page_size'] ?? null), array_keys($this->memoPageSizes()), true)
            ? (string) $configuration['page_size']
            : $this->memoPageSize;
        $this->memoAdditionalInstruction = (string) ($configuration['additional_instruction'] ?? $this->memoAdditionalInstruction);
    }

    protected function resetMemoConfiguration(): void
    {
        $this->memoType = 'memo_internal';
        $this->title = '';
        $this->memoNumber = '';
        $this->memoRecipient = '';
        $this->memoSender = 'Kepala Istana Kepresidenan Yogyakarta';
        $this->memoDate = $this->defaultMemoDate();
        $this->memoBasis = '';
        $this->memoContent = '';
        $this->memoClosing = 'Demikian, mohon arahan lebih lanjut.';
        $this->memoSignatory = 'Deni Mulyana';
        $this->memoCarbonCopy = '';
        $this->memoPageSize = 'folio';
        $this->memoAdditionalInstruction = '';
    }

    /**
     * @return array<string, string>
     */
    protected function memoPageSizes(): array
    {
        return [
            'folio' => 'Folio / memo panjang',
            'letter' => 'Letter / memo pendek',
        ];
    }

    protected function defaultMemoDate(): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $now = now();

        return $now->format('j').' '.$months[(int) $now->format('n')].' '.$now->format('Y');
    }

    protected function addSystemMessage(string $content): void
    {
        $this->memoChatMessages[] = $this->makeSystemMessage($content);
        $this->rememberCurrentThread();
    }

    protected function makeSystemMessage(string $content): array
    {
        return [
            'role' => 'assistant',
            'content' => $content,
            'timestamp' => now()->format('H:i'),
        ];
    }

    protected function rememberCurrentThread(): void
    {
        $this->memoChatThreads[$this->threadKey($this->activeMemoId)] = $this->memoChatMessages;
        $this->persistActiveMemoThread();
    }

    protected function threadKey(?int $memoId = null): string
    {
        return $memoId ? 'memo-'.$memoId : self::DRAFT_THREAD_KEY;
    }

    protected function persistActiveMemoThread(): void
    {
        if (! $this->activeMemoId) {
            return;
        }

        Memo::withoutTimestamps(fn () => Memo::where('id', $this->activeMemoId)
            ->where('user_id', Auth::id())
            ->update(['chat_messages' => $this->memoChatMessages]));
    }

    protected function normalizeStoredThread(array $messages): array
    {
        return collect($messages)
            ->filter(fn ($message) => is_array($message))
            ->map(fn (array $message) => [
                'role' => in_array(($message['role'] ?? null), ['assistant', 'user'], true) ? $message['role'] : 'assistant',
                'content' => (string) ($message['content'] ?? ''),
                'timestamp' => (string) ($message['timestamp'] ?? now()->format('H:i')),
            ])
            ->filter(fn (array $message) => trim($message['content']) !== '')
            ->values()
            ->all();
    }
}
