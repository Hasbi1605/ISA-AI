<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentExportController extends Controller
{
    public function export(Request $request, DocumentExportService $exportService): Response
    {
        $data = $request->validate([
            'content_html' => ['required', 'string'],
            'target_format' => ['required', 'in:pdf,docx,xlsx,csv'],
            'file_name' => ['nullable', 'string', 'max:120'],
        ]);

        $artifact = $exportService->exportContent(
            $data['content_html'],
            $data['target_format'],
            $data['file_name'] ?? null,
        );

        return response($artifact['body'], Response::HTTP_OK, [
            'Content-Type' => $artifact['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$artifact['file_name'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function extractTables(Request $request, Document $document, DocumentExportService $exportService): JsonResponse
    {
        $this->authorizeView($request, $document);

        $result = $exportService->extractTables($document);

        return response()->json($result);
    }

    public function extractContent(Request $request, Document $document, DocumentExportService $exportService): JsonResponse
    {
        $this->authorizeView($request, $document);

        $result = $exportService->extractContent($document);

        return response()->json($result);
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
}
