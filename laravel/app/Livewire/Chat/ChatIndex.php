<?php

namespace App\Livewire\Chat;

use App\Models\CloudStorageFile;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use App\Services\AIService;
use App\Services\Chat\ChatDocumentStateService;
use App\Services\ChatOrchestrationService;
use App\Services\CloudStorage\GoogleDriveService;
use App\Services\DocumentExportService;
use App\Services\DocumentLifecycleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ChatIndex extends Component
{
    use WithFileUploads;

    #[Url]
    public $q = '';

    #[Url]
    public string $tab = 'chat';

    public $prompt = '';

    public $currentConversationId;

    public $messages = [];

    public $conversations = [];

    public $selectedDocuments = [];

    public $conversationDocuments = [];

    public $availableDocuments = [];

    public $showDocumentSelector = false;

    public $sources = [];

    public $showOlderChats = false;

    public $webSearchMode = false; // false = auto, true = force/on

    public $chatAttachment;

    public $isUploadingAttachment = false;

    public $attachmentUploadStatus = null;

    public $attachmentUploadMessage = '';

    public $uploadingAttachmentName = null;

    public $hasDocumentsInProgress = false;

    public $newMessageId = null;

    // Maximum chats to show before "Show More"
    const MAX_VISIBLE_CHATS = 10;

    public function mount($id = null)
    {
        $this->loadConversations();
        $this->loadAvailableDocuments();

        if ($id) {
            $this->loadConversation($id);
        }

        if ($this->q) {
            $this->prompt = $this->q;
            $this->q = ''; // clear from URL so it doesn't persist
        }

        if (session()->has('pending_prompt')) {
            $this->prompt = session()->pull('pending_prompt');
        }
    }

    public function loadConversations()
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            $this->conversations = collect();

            return;
        }

        $this->conversations = $user->conversations()->orderBy('updated_at', 'desc')->get();
    }

    protected function chatDocumentStateService(): ChatDocumentStateService
    {
        return app(ChatDocumentStateService::class);
    }

    public function loadAvailableDocuments()
    {
        $state = $this->chatDocumentStateService()->loadAvailableDocuments((int) Auth::id());

        $this->availableDocuments = $state['documents'];
        $this->hasDocumentsInProgress = $state['has_documents_in_progress'];
    }

    public function loadConversation($id, bool $clearNewMessageId = true)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->firstOrFail();

        $this->currentConversationId = $conversation->id;
        $this->messages = $conversation->messages->toArray();
        if ($clearNewMessageId) {
            $this->newMessageId = null;
        }
        $this->dispatch('conversation-activated', id: $conversation->id);
    }

    public function startNewChat()
    {
        $this->currentConversationId = null;
        $this->messages = [];
        $this->prompt = '';
        $this->selectedDocuments = [];
        $this->conversationDocuments = [];
        $this->sources = [];
        $this->newMessageId = null;
        $this->attachmentUploadStatus = null;
        $this->attachmentUploadMessage = '';
        $this->uploadingAttachmentName = null;
        $this->dispatch('conversation-activated', id: null);
    }

    public function toggleDocumentSelector()
    {
        $this->showDocumentSelector = ! $this->showDocumentSelector;
    }

    public function toggleDocument($documentId)
    {
        $this->selectedDocuments = $this->chatDocumentStateService()->toggleDocument($this->selectedDocuments, $documentId);
    }

    public function selectAllDocuments()
    {
        $this->selectedDocuments = $this->chatDocumentStateService()->selectAllReadyDocuments((int) Auth::id());
    }

    public function toggleSelectAllDocuments()
    {
        $this->selectedDocuments = $this->chatDocumentStateService()->toggleSelectAllDocuments(
            $this->selectedDocuments,
            $this->chatDocumentStateService()->readyDocumentIds((int) Auth::id()),
        );
    }

    public function clearDocumentSelection()
    {
        $this->selectedDocuments = [];
    }

    public function updatedSelectedDocuments()
    {
        $this->selectedDocuments = $this->chatDocumentStateService()->filterSelectedDocuments(
            $this->selectedDocuments,
            $this->chatDocumentStateService()->readyDocumentIds((int) Auth::id()),
        );
    }

    public function addSelectedDocumentsToChat()
    {
        $this->conversationDocuments = $this->chatDocumentStateService()->addSelectedDocumentsToChat(
            $this->selectedDocuments,
            $this->chatDocumentStateService()->readyDocumentIds((int) Auth::id()),
        );
    }

    public function clearConversationDocuments()
    {
        $this->conversationDocuments = [];
    }

    public function deleteDocument($documentId, DocumentLifecycleService $documentLifecycleService)
    {
        $document = Document::where('id', $documentId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $document) {
            session()->flash('error', 'Dokumen tidak ditemukan atau sudah dihapus.');

            return;
        }

        try {
            $documentLifecycleService->deleteDocument($document);

            $stateService = $this->chatDocumentStateService();
            $this->selectedDocuments = $stateService->removeDocumentIds($this->selectedDocuments, (int) $documentId);
            $this->conversationDocuments = $stateService->removeDocumentIds($this->conversationDocuments, (int) $documentId);

            $this->loadAvailableDocuments();
            session()->flash('message', 'Dokumen berhasil dihapus.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menghapus dokumen: '.$e->getMessage());
        }
    }

    public function deleteSelectedDocuments(DocumentLifecycleService $documentLifecycleService)
    {
        $documentIds = array_map('intval', $this->selectedDocuments);

        if (empty($documentIds)) {
            session()->flash('error', 'Pilih dokumen terlebih dahulu.');

            return;
        }

        $documents = Document::where('user_id', Auth::id())
            ->whereIn('id', $documentIds)
            ->get();

        try {
            $documentLifecycleService->deleteDocuments($documents);

            $this->selectedDocuments = [];
            $this->conversationDocuments = $this->chatDocumentStateService()->removeDocumentIds(
                $this->conversationDocuments,
                $documentIds,
            );

            $this->loadAvailableDocuments();
            session()->flash('message', 'Dokumen terpilih berhasil dihapus.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menghapus dokumen terpilih: '.$e->getMessage());
        }
    }

    public function removeConversationDocument($documentId)
    {
        $this->conversationDocuments = $this->chatDocumentStateService()->removeDocumentIds(
            $this->conversationDocuments,
            (int) $documentId,
        );

        $this->selectedDocuments = $this->chatDocumentStateService()->removeDocumentIds(
            $this->selectedDocuments,
            (int) $documentId,
        );
    }

    public function toggleOlderChats()
    {
        $this->showOlderChats = ! $this->showOlderChats;
    }

    public function toggleWebSearch()
    {
        $this->webSearchMode = ! $this->webSearchMode;
    }

    public function updatedChatAttachment()
    {
        if (! $this->chatAttachment) {
            return;
        }

        $this->attachmentUploadStatus = null;
        $this->attachmentUploadMessage = '';
        $this->uploadChatAttachment(app(DocumentLifecycleService::class));
    }

    public function uploadChatAttachment(DocumentLifecycleService $documentLifecycleService)
    {
        try {
            $this->enforceRateLimit('uploadChatAttachment', 5, 60, 'Terlalu banyak upload dokumen. Coba lagi sebentar.');
            $this->validate([
                'chatAttachment' => [
                    'required',
                    'file',
                    'mimes:'.implode(',', Document::attachmentFileExtensions()),
                    'mimetypes:'.implode(',', Document::attachmentMimeTypes()),
                    'max:51200',
                ],
            ]);

            $this->isUploadingAttachment = true;
            $this->uploadingAttachmentName = $this->chatAttachment->getClientOriginalName();

            $documentLifecycleService->uploadDocument($this->chatAttachment, Auth::id());

            session()->flash('message', 'Dokumen berhasil diunggah dan sedang diproses.');
            $this->attachmentUploadStatus = 'success';
            $this->attachmentUploadMessage = 'Upload berhasil. Dokumen sedang diproses.';
            $this->loadAvailableDocuments();
        } catch (ValidationException $e) {
            $errors = $e->validator->errors();
            $message = $errors->first('file') ?: ($errors->first('chatAttachment') ?: 'Upload gagal. Periksa format file dan coba lagi.');
            session()->flash('error', $message);
            $this->attachmentUploadStatus = 'error';
            $this->attachmentUploadMessage = $message;
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal mengunggah dokumen: '.$e->getMessage());
            $this->attachmentUploadStatus = 'error';
            $this->attachmentUploadMessage = 'Upload gagal. Periksa format file dan coba lagi.';
        } finally {
            $this->isUploadingAttachment = false;
            $this->uploadingAttachmentName = null;
            $this->reset('chatAttachment');
        }
    }

    #[On('google-drive-document-imported')]
    public function refreshDocumentsAfterGoogleDriveImport(?int $documentId = null): void
    {
        $this->loadAvailableDocuments();

        if ($documentId !== null) {
            $document = Document::query()
                ->whereKey((int) $documentId)
                ->where('user_id', Auth::id())
                ->first();

            if ($document) {
                $this->conversationDocuments = $this->chatDocumentStateService()->addDocumentIds(
                    $this->conversationDocuments,
                    (int) $documentId,
                );

                $this->dispatch('conversation-documents-preview',
                    ids: $this->conversationDocuments,
                    documents: [[
                        'id' => (int) $document->id,
                        'name' => (string) $document->original_name,
                        'extension' => (string) $document->extension,
                        'status' => (string) $document->status,
                    ]],
                );
            }
        }

        $this->attachmentUploadStatus = 'success';
        $this->attachmentUploadMessage = 'File Google Drive berhasil ditambahkan dan sedang diproses.';

        session()->flash('message', 'File Google Drive berhasil ditambahkan dan sedang diproses.');
    }

    /**
     * Upload a persisted assistant answer to the office Google Drive in the requested format.
     *
     * @return array{ok: bool, message?: string, file_name?: string, web_view_link?: ?string, folder_external_id?: ?string}
     */
    public function saveAnswerToGoogleDrive(int $messageId, string $targetFormat): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return [
                'ok' => false,
                'message' => 'Anda harus login terlebih dahulu.',
            ];
        }

        $targetFormat = $this->normalizeDriveExportFormat($targetFormat);

        if ($targetFormat === null) {
            return [
                'ok' => false,
                'message' => 'Format upload Google Drive tidak didukung.',
            ];
        }

        if ($this->isRateLimited('saveAnswerToGoogleDrive', 10, 60)) {
            return [
                'ok' => false,
                'message' => 'Terlalu banyak permintaan ekspor Google Drive. Coba lagi sebentar.',
            ];
        }

        $message = Message::query()
            ->whereKey($messageId)
            ->where('role', 'assistant')
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $userId))
            ->first();

        if ($message === null) {
            return [
                'ok' => false,
                'message' => 'Jawaban AI tidak ditemukan.',
            ];
        }

        try {
            $contentHtml = (string) Str::of($message->content)->markdown([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);

            $exportService = app(DocumentExportService::class);
            $artifact = $exportService->exportContent(
                $contentHtml,
                $targetFormat,
                'ista-ai-jawaban-'.$message->id,
            );

            $tempRelativePath = 'tmp/cloud/google-drive/'.Str::uuid().'.'.$targetFormat;
            Storage::disk('local')->put($tempRelativePath, $artifact['body']);
            $tempAbsolutePath = Storage::disk('local')->path($tempRelativePath);

            try {
                $upload = app(GoogleDriveService::class)->uploadFromPath(
                    $tempAbsolutePath,
                    $artifact['file_name'],
                    $artifact['content_type'],
                    null,
                );

                CloudStorageFile::create([
                    'user_id' => (int) $userId,
                    'provider' => 'google_drive',
                    'direction' => CloudStorageFile::DIRECTION_EXPORT,
                    'local_type' => Message::class,
                    'local_id' => $message->id,
                    'external_id' => $upload['external_id'],
                    'name' => $upload['name'],
                    'mime_type' => $upload['mime_type'],
                    'web_view_link' => $upload['web_view_link'],
                    'folder_external_id' => $upload['folder_external_id'],
                    'size_bytes' => $upload['size_bytes'],
                    'synced_at' => now(),
                ]);
            } finally {
                Storage::disk('local')->delete($tempRelativePath);
            }

            return [
                'ok' => true,
                'file_name' => $upload['name'],
                'web_view_link' => $upload['web_view_link'],
                'folder_external_id' => $upload['folder_external_id'],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function deleteConversation($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if ($conversation) {
            // Delete all messages first
            $conversation->messages()->delete();
            // Delete the conversation
            $conversation->delete();

            // If we deleted the current conversation, reset
            if ($this->currentConversationId == $id) {
                $this->startNewChat();
            }

            // Reload conversations
            $this->loadConversations();
        }
    }

    public function sendMessage(AIService $aiService, ?string $prompt = null, ?ChatOrchestrationService $orchestrator = null)
    {
        $orchestrator = $orchestrator ?? app(ChatOrchestrationService::class);

        if ($prompt !== null) {
            $this->prompt = $prompt;
        }

        $this->newMessageId = null;

        $this->validate([
            'prompt' => 'required|string|min:1|max:8000',
        ]);

        $this->enforceRateLimit('sendMessage', 10, 60, 'Terlalu banyak mengirim pesan. Coba lagi sebentar.');
        set_time_limit(120);

        $this->currentConversationId = $orchestrator->createConversationIfNeeded(
            $this->currentConversationId,
            $this->prompt
        );

        $conversationIdForRequest = (int) $this->currentConversationId;

        $userMessageArray = $orchestrator->saveUserMessage($conversationIdForRequest, $this->prompt);
        $this->messages[] = $userMessageArray;
        $this->dispatch('user-message-acked');
        $this->prompt = '';
        $this->sources = [];

        $history = $orchestrator->buildHistory($this->messages);

        $this->stream('assistant-output', '', true);

        $documentFilenames = $orchestrator->getDocumentFilenames($this->conversationDocuments);
        $sourcePolicy = $orchestrator->getSourcePolicy($documentFilenames);
        $allowAutoRealtimeWeb = $orchestrator->shouldAllowAutoRealtimeWeb($documentFilenames);

        $fullResponse = '';
        $modelName = 'AI';
        $streamBuffer = '';

        foreach (
            $aiService->sendChat(
                $history,
                $documentFilenames,
                (string) Auth::id(),
                $this->webSearchMode,
                $sourcePolicy,
                $allowAutoRealtimeWeb
            ) as $chunk
        ) {
            [$chunk, $streamBuffer, $parsedModelName, $parsedSources] = $orchestrator->extractStreamMetadata(
                (string) $chunk,
                $streamBuffer
            );

            if ($parsedModelName !== null) {
                $modelName = $parsedModelName;
                $this->stream('model-name', $modelName);
            }

            if (! empty($parsedSources)) {
                $this->sources = $parsedSources;
                $this->dispatch('assistant-sources', $this->sources);
            }

            $chunk = $orchestrator->sanitizeAssistantOutput((string) $chunk);

            if ($chunk !== '') {
                $fullResponse .= $chunk;
                $this->stream('assistant-output', $chunk);
            }
        }

        $cleanContent = $orchestrator->cleanResponseContent($fullResponse);

        if (! empty($this->sources)) {
            $cleanContent .= $orchestrator->sanitizeAndFormatSources($this->sources);
        }

        $assistantMsg = $orchestrator->saveAssistantMessage($conversationIdForRequest, $cleanContent);
        $this->newMessageId = $assistantMsg->id;

        if ((int) $this->currentConversationId === $conversationIdForRequest) {
            $this->loadConversation($conversationIdForRequest, clearNewMessageId: false);
        }
        $this->loadConversations();
        $this->dispatch('assistant-message-persisted');
    }

    public function render()
    {
        return view('livewire.chat.chat-index', [
            'googleDriveUploadAvailable' => app(GoogleDriveService::class)->canUploadWithConfiguredAccount(),
        ]);
    }

    private function normalizeDriveExportFormat(string $targetFormat): ?string
    {
        $normalized = strtolower(trim($targetFormat));

        return in_array($normalized, ['pdf', 'docx', 'xlsx', 'csv'], true)
            ? $normalized
            : null;
    }

    private function enforceRateLimit(string $action, int $maxAttempts, int $decaySeconds = 60, ?string $message = null): void
    {
        $key = $this->rateLimitKey($action);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'rate_limit' => $message ?? 'Terlalu banyak permintaan. Silakan coba lagi sebentar.',
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function isRateLimited(string $action, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $key = $this->rateLimitKey($action);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return true;
        }

        RateLimiter::hit($key, $decaySeconds);

        return false;
    }

    private function rateLimitKey(string $action): string
    {
        $userId = Auth::id();
        $ip = request()?->ip() ?? 'unknown';
        $userPart = $userId ? 'user-'.$userId : 'guest';

        return implode(':', [static::class, $action, $userPart, $ip]);
    }
}
