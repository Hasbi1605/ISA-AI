<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Documents\DocumentPreviewRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPreviewController extends Controller
{
    public function __construct(protected DocumentPreviewRenderer $renderer) {}

    public function status(Request $request, Document $document): JsonResponse
    {
        $this->authorizeView($request, $document);

        return response()->json([
            'id' => $document->id,
            'mime_type' => $document->mime_type,
            'preview_status' => $document->preview_status,
            'kind' => $this->resolveKind($document),
            'is_streamable' => $this->renderer->isPdf($document),
            'is_html_preview' => $this->renderer->isDocx($document) || $this->renderer->isXlsx($document),
        ]);
    }

    public function stream(Request $request, Document $document): BinaryFileResponse|StreamedResponse|Response
    {
        $this->authorizeView($request, $document);

        if (! $this->renderer->isPdf($document)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $absolutePath = $this->resolveSourcePath($document);

        if ($absolutePath === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->file($absolutePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes((string) $document->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function html(Request $request, Document $document): Response
    {
        $this->authorizeView($request, $document);

        if (! ($this->renderer->isDocx($document) || $this->renderer->isXlsx($document))) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($document->preview_status !== Document::PREVIEW_STATUS_READY || ! $document->preview_html_path) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! Storage::disk(DocumentPreviewRenderer::DISK)->exists($document->preview_html_path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $html = (string) Storage::disk(DocumentPreviewRenderer::DISK)->get($document->preview_html_path);

        return response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'sandbox',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function authorizeView(Request $request, Document $document): void
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if ($user->cannot('view', $document)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    protected function resolveSourcePath(Document $document): ?string
    {
        $candidates = [
            $document->file_path,
            'private/'.$document->file_path,
        ];

        foreach ($candidates as $candidate) {
            if (! $candidate) {
                continue;
            }

            $absolute = Storage::disk(DocumentPreviewRenderer::DISK)->path($candidate);
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        return null;
    }

    protected function resolveKind(Document $document): string
    {
        return match (true) {
            $this->renderer->isPdf($document) => 'pdf',
            $this->renderer->isDocx($document) => 'docx',
            $this->renderer->isXlsx($document) => 'xlsx',
            default => 'unknown',
        };
    }
}
