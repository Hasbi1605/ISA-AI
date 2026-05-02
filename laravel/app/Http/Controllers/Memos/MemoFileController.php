<?php

namespace App\Http\Controllers\Memos;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Services\DocumentExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MemoFileController extends Controller
{
    public function signed(Request $request, Memo $memo): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(false), Response::HTTP_FORBIDDEN);

        return $this->fileResponse($memo, 'inline');
    }

    public function download(Request $request, Memo $memo): BinaryFileResponse
    {
        $this->authorizeView($request, $memo);

        return $this->fileResponse($memo, 'attachment');
    }

    public function exportPdf(Request $request, Memo $memo, DocumentExportService $exportService): Response
    {
        $this->authorizeView($request, $memo);

        $html = '<h1>'.e($memo->title).'</h1><p>'.nl2br(e((string) $memo->searchable_text)).'</p>';
        $artifact = $exportService->exportContent($html, 'pdf', $memo->title);

        return response($artifact['body'], Response::HTTP_OK, [
            'Content-Type' => $artifact['content_type'],
            'Content-Disposition' => 'attachment; filename="'.$artifact['file_name'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function fileResponse(Memo $memo, string $disposition): BinaryFileResponse
    {
        abort_if(! $memo->file_path, Response::HTTP_NOT_FOUND);

        $absolute = Storage::disk('local')->path($memo->file_path);
        abort_unless(is_file($absolute), Response::HTTP_NOT_FOUND);

        return response()->file($absolute, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($memo->title).'.docx"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    protected function authorizeView(Request $request, Memo $memo): void
    {
        $user = $request->user();

        abort_if($user === null, Response::HTTP_UNAUTHORIZED);
        abort_if($user->cannot('view', $memo), Response::HTTP_FORBIDDEN);
    }
}
