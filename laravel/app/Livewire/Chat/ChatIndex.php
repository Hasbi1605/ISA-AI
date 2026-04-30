<?php

namespace App\Livewire\Chat;

use App\Jobs\ProcessDocument;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Document;
use App\Services\AIService;
use App\Services\ChatOrchestrationService;
use App\Services\DocumentLifecycleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ChatIndex extends Component
{
    use WithFileUploads;

    #[Url]
    public $q = '';

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

    private const ALLOWED_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

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
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            $this->conversations = collect();
            return;
        }

        $this->conversations = $user->conversations()->orderBy('updated_at', 'desc')->get();
    }

    public function loadAvailableDocuments()
    {
        $documents = Document::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        $this->hasDocumentsInProgress = $documents->contains(function (Document $document) {
            return in_array($document->status, ['pending', 'processing'], true);
        });

        $this->availableDocuments = $documents;
    }

    protected function getReadyDocumentIds(): array
    {
        return Document::where('user_id', Auth::id())
            ->where('status', 'ready')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }

    public function loadConversation($id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->firstOrFail();

        $this->currentConversationId = $conversation->id;
        $this->messages = $conversation->messages->toArray();
        $this->newMessageId = null;
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
        $this->showDocumentSelector = !$this->showDocumentSelector;
    }

    public function toggleDocument($documentId)
    {
        if (in_array($documentId, $this->selectedDocuments)) {
            $this->selectedDocuments = array_values(array_filter($this->selectedDocuments, function ($id) use ($documentId) {
                return $id != $documentId;
            }));
        } else {
            $this->selectedDocuments[] = $documentId;
        }
    }

    public function selectAllDocuments()
    {
        $this->selectedDocuments = $this->getReadyDocumentIds();
    }

    public function toggleSelectAllDocuments()
    {
        $allDocumentIds = $this->getReadyDocumentIds();

        $selectedIds = array_map('intval', $this->selectedDocuments);
        sort($allDocumentIds);
        sort($selectedIds);

        if (!empty($allDocumentIds) && $selectedIds === $allDocumentIds) {
            $this->selectedDocuments = [];
            return;
        }

        $this->selectedDocuments = $allDocumentIds;
    }

    public function clearDocumentSelection()
    {
        $this->selectedDocuments = [];
    }

    public function updatedSelectedDocuments()
    {
        $availableIds = $this->getReadyDocumentIds();

        $availableMap = array_flip($availableIds);
        $this->selectedDocuments = array_values(array_filter(array_map('intval', $this->selectedDocuments), function ($id) use ($availableMap) {
            return isset($availableMap[$id]);
        }));
    }

    public function addSelectedDocumentsToChat()
    {
        $readyMap = array_flip($this->getReadyDocumentIds());

        $this->conversationDocuments = array_values(array_filter(array_unique(array_map('intval', $this->selectedDocuments)), function ($id) use ($readyMap) {
            return isset($readyMap[$id]);
        }));
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

        if (!$document) {
            session()->flash('error', 'Dokumen tidak ditemukan atau sudah dihapus.');
            return;
        }

        try {
            $documentLifecycleService->deleteDocument($document);

            $this->selectedDocuments = array_values(array_filter($this->selectedDocuments, function ($id) use ($documentId) {
                return (int) $id !== (int) $documentId;
            }));

            $this->conversationDocuments = array_values(array_filter($this->conversationDocuments, function ($id) use ($documentId) {
                return (int) $id !== (int) $documentId;
            }));

            $this->loadAvailableDocuments();
            session()->flash('message', 'Dokumen berhasil dihapus.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menghapus dokumen: ' . $e->getMessage());
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
            $this->conversationDocuments = array_values(array_filter($this->conversationDocuments, function ($id) use ($documentIds) {
                return !in_array((int) $id, $documentIds, true);
            }));

            $this->loadAvailableDocuments();
            session()->flash('message', 'Dokumen terpilih berhasil dihapus.');
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menghapus dokumen terpilih: ' . $e->getMessage());
        }
    }

    public function removeConversationDocument($documentId)
    {
        $this->conversationDocuments = array_values(array_filter($this->conversationDocuments, function ($id) use ($documentId) {
            return (int) $id !== (int) $documentId;
        }));

        $this->selectedDocuments = array_values(array_filter($this->selectedDocuments, function ($id) use ($documentId) {
            return (int) $id !== (int) $documentId;
        }));
    }

    public function toggleOlderChats()
    {
        $this->showOlderChats = !$this->showOlderChats;
    }

    public function toggleWebSearch()
    {
        $this->webSearchMode = !$this->webSearchMode;
    }

    public function updatedChatAttachment()
    {
        if (!$this->chatAttachment) {
            return;
        }

        $this->attachmentUploadStatus = null;
        $this->attachmentUploadMessage = '';
        $this->uploadChatAttachment(app(DocumentLifecycleService::class));
    }

    public function uploadChatAttachment(DocumentLifecycleService $documentLifecycleService)
    {
        try {
            $this->validate([
                'chatAttachment' => [
                    'required',
                    'file',
                    'mimes:pdf,docx,xlsx,csv',
                    'mimetypes:' . implode(',', self::ALLOWED_ATTACHMENT_MIME_TYPES),
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
            session()->flash('error', 'Gagal mengunggah dokumen: ' . $e->getMessage());
            $this->attachmentUploadStatus = 'error';
            $this->attachmentUploadMessage = 'Upload gagal. Periksa format file dan coba lagi.';
        } finally {
            $this->isUploadingAttachment = false;
            $this->uploadingAttachmentName = null;
            $this->reset('chatAttachment');
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

    public function sendMessage(?string $prompt = null, AIService $aiService, ?ChatOrchestrationService $orchestrator = null)
    {
        $orchestrator = $orchestrator ?? app(ChatOrchestrationService::class);

        if ($prompt !== null) {
            $this->prompt = $prompt;
        }

        set_time_limit(120);
        $this->newMessageId = null;

        $this->validate([
            'prompt' => 'required|string|min:1',
        ]);

        $this->currentConversationId = $orchestrator->createConversationIfNeeded(
            $this->currentConversationId,
            $this->prompt
        );

        $userMessageArray = $orchestrator->saveUserMessage($this->currentConversationId, $this->prompt);
        $this->messages[] = $userMessageArray;
        $this->dispatch('user-message-acked');
        $this->prompt = '';
        $this->sources = [];

        $history = $orchestrator->buildHistory($this->messages);

        $this->stream('assistant-output', "", true);

        $documentFilenames = $orchestrator->getDocumentFilenames($this->conversationDocuments);
        $sourcePolicy = $orchestrator->getSourcePolicy($documentFilenames);
        $allowAutoRealtimeWeb = $orchestrator->shouldAllowAutoRealtimeWeb($documentFilenames);

        $fullResponse = "";
        $modelName = "AI";
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

            if (!empty($parsedSources)) {
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

        if (!empty($this->sources)) {
            $cleanContent .= $orchestrator->sanitizeAndFormatSources($this->sources);
        }

        $assistantMsg = $orchestrator->saveAssistantMessage($this->currentConversationId, $cleanContent);
        $this->newMessageId = $assistantMsg->id;

        $this->loadConversation($this->currentConversationId);
        $this->loadConversations();
        $this->dispatch('assistant-message-persisted');
    }

    private function sanitizeAssistantOutput(string $text): string
    {
        return app(ChatOrchestrationService::class)->sanitizeAssistantOutput($text);
    }

    private function extractStreamMetadata(string $chunk, string $buffer = ''): array
    {
        return app(ChatOrchestrationService::class)->extractStreamMetadata($chunk, $buffer);
    }

    public function render()
    {
        return view('livewire.chat.chat-index');
    }
}
