<?php

namespace App\Livewire\Memos;

use App\Models\Memo;
use App\Services\Memo\MemoGenerationService;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
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

    public string $memoPageSize = 'auto';

    public string $memoAdditionalInstruction = '';

    public array $memoChatMessages = [];

    public array $memoChatThreads = [];

    public bool $isGenerating = false;

    public bool $showMemoConfiguration = true;

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
        $this->isGenerating = false;
        $this->resetMemoConfiguration();
        $this->showMemoConfiguration = true;
        $this->addSystemMessage('Lengkapi konfigurasi memo baru. Saya akan menjaga struktur, gaya bahasa, dan format memorandum mengikuti contoh manual.');
    }

    public function generateConfiguredMemo(): void
    {
        if (! $this->validateMemoConfiguration()) {
            return;
        }

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
            $this->generateRevisionFromChat($userMessage);
        } else {
            $this->memoContent = trim($this->memoContent."\n".$userMessage);
            $this->addSystemMessage('Saya simpan instruksi itu sebagai isi/poin memo. Lengkapi konfigurasi utama, lalu tekan Generate Memo.');
        }
    }

    public function generateRevisionFromChat(string $instruction): void
    {
        if (! $this->validateMemoConfiguration()) {
            return;
        }

        $this->isGenerating = true;

        try {
            $this->applyRevisionInstruction($instruction);

            $generationService = app(MemoGenerationService::class);
            $configuration = array_merge($this->memoConfigurationPayload(), [
                'revision_instruction' => trim($instruction),
            ]);

            $memo = $generationService->generate(
                Auth::user(),
                $this->memoType,
                $this->title,
                $this->memoRevisionContext($instruction),
                [],
                $configuration,
            );

            $this->activeMemoId = $memo->id;
            $this->showMemoConfiguration = false;
            $this->rememberCurrentThread();

            $this->addSystemMessage("Revisi memo \"{$memo->title}\" berhasil digenerate. Cek panel Dokumen untuk memastikan hasilnya sudah sesuai.");
            $this->dispatch('memo-document-ready', memoId: $memo->id);
        } catch (\Throwable $e) {
            $this->addSystemMessage('Maaf, terjadi kesalahan saat revisi memo: '.$e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    public function generateFromChat(string $context, bool $fromConfiguration = false): void
    {
        if (! $this->validateMemoConfiguration()) {
            return;
        }

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
            $this->showMemoConfiguration = false;
            $this->rememberCurrentThread();

            $message = $fromConfiguration
                ? "Draft memo \"{$memo->title}\" berhasil digenerate dari konfigurasi. Anda bisa meminta revisi spesifik di sini."
                : "Revisi memo \"{$memo->title}\" berhasil digenerate. Cek panel Dokumen untuk memastikan hasilnya sudah sesuai.";

            $this->addSystemMessage($message);
            $this->dispatch('memo-document-ready', memoId: $memo->id);
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
            'editorConfig' => $this->editorConfig(),
            'onlyOfficeApiUrl' => rtrim((string) config('services.onlyoffice.public_url', ''), '/').'/web-apps/apps/api/documents/api.js',
        ]);
    }

    protected function validateMemoConfiguration(): bool
    {
        try {
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
                'memoPageSize' => ['required', 'in:auto,folio,letter'],
                'memoAdditionalInstruction' => ['nullable', 'string', 'max:2000'],
            ], [
                'memoNumber.required' => 'Nomor memo wajib diisi.',
                'memoRecipient.required' => 'Yth. wajib diisi.',
                'memoSender.required' => 'Dari wajib diisi.',
                'title.required' => 'Hal wajib diisi.',
                'memoDate.required' => 'Tanggal wajib diisi.',
                'memoContent.required' => 'Isi / poin wajib harus diisi.',
                'memoContent.min' => 'Isi / poin wajib minimal :min karakter.',
                'memoSignatory.required' => 'Penandatangan wajib diisi.',
            ]);

            return true;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $this->dispatch('memo-configuration-invalid');

            return false;
        }
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
            'page_size' => $this->resolveMemoPageSize(),
            'page_size_mode' => trim($this->memoPageSize),
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

    protected function memoRevisionContext(string $instruction): string
    {
        $sections = [
            $this->memoDraftContext(),
            "Instruksi revisi wajib diterapkan:\n".trim($instruction),
        ];

        $activeMemoText = $this->activeMemoId
            ? Memo::where('id', $this->activeMemoId)
                ->where('user_id', Auth::id())
                ->value('searchable_text')
            : null;

        if (trim((string) $activeMemoText) !== '') {
            array_unshift($sections, "Isi memo saat ini:\n".trim((string) $activeMemoText));
        }

        return implode("\n\n", array_filter($sections, fn (string $section) => trim($section) !== ''));
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

    protected function applyRevisionInstruction(string $instruction): void
    {
        $this->applyCarbonCopyRevision($instruction);
    }

    protected function applyCarbonCopyRevision(string $instruction): void
    {
        if (! preg_match('/\btembusan\b/iu', $instruction)) {
            return;
        }

        if (! preg_match('/tembusan(?:\s+(?:nomor|no\.?)\s*(\d+))?\s*[,:\-]?\s*(?:untuk\s+|kepada\s+|ke\s+)?(.+)$/iu', $instruction, $matches)) {
            return;
        }

        $item = $this->normalizeCarbonCopyItem((string) ($matches[2] ?? ''));

        if ($item === '') {
            return;
        }

        $lines = $this->carbonCopyLines();
        $normalizedItem = mb_strtolower($item);

        if (collect($lines)->contains(fn (string $line) => mb_strtolower($line) === $normalizedItem)) {
            return;
        }

        $position = isset($matches[1]) && is_numeric($matches[1])
            ? max(1, (int) $matches[1])
            : count($lines) + 1;
        $position = min($position, count($lines) + 1);

        array_splice($lines, $position - 1, 0, [$item]);

        $this->memoCarbonCopy = collect($lines)
            ->values()
            ->map(fn (string $line, int $index) => ($index + 1).'. '.$line)
            ->implode("\n");
    }

    /**
     * @return array<int, string>
     */
    protected function carbonCopyLines(): array
    {
        return collect(preg_split('/\R+/', $this->memoCarbonCopy) ?: [])
            ->map(fn (string $line) => $this->normalizeCarbonCopyItem($line))
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeCarbonCopyItem(string $item): string
    {
        $item = preg_replace('/^\s*\d+[.)]\s*/u', '', $item) ?? $item;
        $item = preg_replace('/^(?:nomor|no\.?)\s*\d+\s*[,:\-]?\s*/iu', '', trim($item)) ?? $item;
        $item = preg_replace('/\s+/u', ' ', trim($item)) ?? $item;

        return trim($item, " \t\n\r\0\x0B.,;:");
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
        $storedPageSizeMode = (string) ($configuration['page_size_mode'] ?? '');
        $storedPageSize = (string) ($configuration['page_size'] ?? '');
        $this->memoPageSize = in_array($storedPageSizeMode, array_keys($this->memoPageSizes()), true)
            ? $storedPageSizeMode
            : (in_array($storedPageSize, array_keys($this->memoPageSizes()), true) ? $storedPageSize : $this->memoPageSize);
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
        $this->memoClosing = '';
        $this->memoSignatory = 'Deni Mulyana';
        $this->memoCarbonCopy = '';
        $this->memoPageSize = 'auto';
        $this->memoAdditionalInstruction = '';
    }

    /**
     * @return array<string, string>
     */
    protected function memoPageSizes(): array
    {
        return [
            'auto' => 'Otomatis',
            'letter' => 'Letter (pendek)',
            'folio' => 'Folio (panjang)',
        ];
    }

    protected function resolveMemoPageSize(): string
    {
        if ($this->memoPageSize !== 'auto') {
            return $this->memoPageSize;
        }

        $body = trim($this->memoBasis."\n".$this->memoContent."\n".$this->memoAdditionalInstruction);
        $lineCount = collect(preg_split('/\R+/', $body) ?: [])
            ->filter(fn (string $line) => trim($line) !== '')
            ->count();

        return strlen($body) <= 900 && $lineCount <= 10 ? 'letter' : 'folio';
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
