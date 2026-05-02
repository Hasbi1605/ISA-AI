<?php

namespace Tests\Unit\Services\Chat;

use App\Models\Document;
use App\Models\User;
use App\Services\Chat\ChatDocumentStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatDocumentStateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_load_available_documents_marks_in_progress_documents_and_returns_ready_ids(): void
    {
        $user = User::factory()->create();
        $readyDocument = $this->createDocument($user, ['status' => 'ready']);
        $this->createDocument($user, ['status' => 'processing']);
        $this->createDocument($user, ['status' => 'pending']);

        $service = app(ChatDocumentStateService::class);
        $state = $service->loadAvailableDocuments($user->id);

        $this->assertCount(3, $state['documents']);
        $this->assertTrue($state['has_documents_in_progress']);
        $this->assertSame([$readyDocument->id], $service->readyDocumentIds($user->id));
    }

    public function test_selection_helpers_keep_only_ready_document_ids(): void
    {
        $service = app(ChatDocumentStateService::class);

        $selectedDocuments = $service->toggleDocument([], 10);
        $this->assertSame([10], $selectedDocuments);

        $selectedDocuments = $service->toggleDocument($selectedDocuments, 12);
        $this->assertSame([10, 12], $selectedDocuments);

        $filteredDocuments = $service->filterSelectedDocuments([10, 12, 99], [10]);
        $this->assertSame([10], $filteredDocuments);

        $this->assertSame([], $service->toggleSelectAllDocuments([10], [10]));
        $this->assertSame([10, 12], $service->toggleSelectAllDocuments([], [10, 12]));
        $this->assertSame([10], $service->addSelectedDocumentsToChat([10, 12], [10]));
        $this->assertSame([10], $service->removeDocumentIds([10, 12], 12));
    }

    private function createDocument(User $user, array $overrides = []): Document
    {
        return Document::create(array_merge([
            'user_id' => $user->id,
            'filename' => uniqid('doc_', true).'.pdf',
            'original_name' => uniqid('file_', true).'.pdf',
            'file_path' => 'documents/'.$user->id.'/'.uniqid('path_', true).'.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 120 * 1024,
            'status' => 'ready',
        ], $overrides));
    }
}
