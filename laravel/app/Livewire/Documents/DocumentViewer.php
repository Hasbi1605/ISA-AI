<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class DocumentViewer extends Component
{
    public ?int $documentId = null;

    public bool $isOpen = false;

    public function open(int $documentId): void
    {
        $this->documentId = $documentId;
        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->documentId = null;
    }

    public function render()
    {
        $document = null;
        $kind = null;
        $previewStatus = null;
        $streamUrl = null;
        $htmlUrl = null;

        if ($this->isOpen && $this->documentId !== null) {
            $document = Document::query()
                ->where('user_id', Auth::id())
                ->find($this->documentId);

            if ($document !== null) {
                $renderer = app(DocumentPreviewRenderer::class);
                $previewStatus = $document->preview_status;

                $kind = match (true) {
                    $renderer->isPdf($document) => 'pdf',
                    $renderer->isDocx($document) => 'docx',
                    $renderer->isXlsx($document) => 'xlsx',
                    default => 'unknown',
                };

                if ($kind === 'pdf') {
                    $streamUrl = route('documents.preview.stream', $document);
                } elseif (in_array($kind, ['docx', 'xlsx'], true)) {
                    $htmlUrl = route('documents.preview.html', $document);
                }
            }
        }

        return view('livewire.documents.document-viewer', [
            'document' => $document,
            'kind' => $kind,
            'previewStatus' => $previewStatus,
            'streamUrl' => $streamUrl,
            'htmlUrl' => $htmlUrl,
        ]);
    }
}
