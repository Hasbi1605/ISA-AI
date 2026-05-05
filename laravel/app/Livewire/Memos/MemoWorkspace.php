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

    public array $memoChatMessages = [];

    public array $memoChatThreads = [];

    public bool $isGenerating = false;

    public string $previewMode = 'preview'; // 'preview' or 'editor'

    public ?string $previewHtml = null;

    public function mount(): void
    {
        $this->addSystemMessage('Halo! Saya siap membantu Anda membuat memo. Silakan isi jenis dan judul memo di atas, lalu ketik instruksi untuk konten memo yang ingin digenerate.');
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
        $this->previewHtml = $memo->searchable_text ? nl2br(e($memo->searchable_text)) : null;
        $this->previewMode = 'preview';

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

        $this->reset(['activeMemoId', 'memoType', 'title', 'memoPrompt', 'memoChatMessages', 'previewHtml', 'isGenerating']);
        $this->memoType = 'memo_internal';
        $this->previewMode = 'preview';
        $this->addSystemMessage('Halo! Saya siap membantu Anda membuat memo baru. Silakan isi jenis dan judul memo, lalu ketik instruksi.');
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

        // If we have enough context, auto-generate
        if ($this->title && $this->memoType) {
            $this->generateFromChat($userMessage);
        } else {
            $this->addSystemMessage('Silakan lengkapi jenis memo dan judul terlebih dahulu, lalu saya bisa generate draft untuk Anda.');
        }
    }

    public function generateFromChat(string $context): void
    {
        $this->validate([
            'memoType' => ['required', 'in:'.implode(',', array_keys(Memo::TYPES))],
            'title' => ['required', 'string', 'max:160'],
        ]);

        $this->isGenerating = true;

        try {
            $generationService = app(MemoGenerationService::class);

            $memo = $generationService->generate(
                Auth::user(),
                $this->memoType,
                $this->title,
                $context,
            );

            $this->activeMemoId = $memo->id;
            $this->previewHtml = $memo->searchable_text ? nl2br(e($memo->searchable_text)) : '<p class="text-stone-500">Draft berhasil digenerate. Gunakan tab Editor untuk melihat dokumen lengkap.</p>';
            $this->previewMode = 'preview';
            $this->rememberCurrentThread();

            $this->addSystemMessage("Draft memo \"{$memo->title}\" berhasil digenerate! Anda bisa melihat preview di panel kanan, atau beralih ke Editor untuk mengedit dokumen DOCX secara langsung.\n\nMau saya revisi bagian tertentu?");
        } catch (\Throwable $e) {
            $this->addSystemMessage('Maaf, terjadi kesalahan saat generate memo: '.$e->getMessage());
        } finally {
            $this->isGenerating = false;
        }
    }

    public function regenerate(): void
    {
        if (! $this->activeMemoId) {
            $this->addSystemMessage('Belum ada memo aktif untuk di-regenerate. Silakan buat memo baru.');

            return;
        }

        $lastUserMessage = collect($this->memoChatMessages)
            ->where('role', 'user')
            ->last();

        $context = $lastUserMessage['content'] ?? $this->title;
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
            'editorConfig' => $this->previewMode === 'editor' ? $this->editorConfig() : null,
            'onlyOfficeApiUrl' => rtrim((string) config('services.onlyoffice.public_url', ''), '/').'/web-apps/apps/api/documents/api.js',
        ]);
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
