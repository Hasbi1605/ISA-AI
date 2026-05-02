<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OnlyOfficeCallbackController extends Controller
{
    public function __invoke(Request $request, Memo $memo): JsonResponse
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Token OnlyOffice wajib dikirim.');
        }

        $payload = app(JwtSigner::class)->verify($token);
        abort_unless((int) ($payload['memo_id'] ?? 0) === (int) $memo->id, Response::HTTP_FORBIDDEN);

        $status = (int) $request->input('status', 0);

        if (in_array($status, [2, 6], true)) {
            $url = (string) $request->input('url', '');
            abort_if($url === '', Response::HTTP_BAD_REQUEST, 'URL file OnlyOffice kosong.');

            $response = Http::timeout(60)->get($url);
            abort_unless($response->successful(), Response::HTTP_BAD_GATEWAY, 'Gagal mengunduh file dari OnlyOffice.');

            $path = $memo->file_path ?: 'memos/'.$memo->user_id.'/'.$memo->id.'.docx';
            Storage::disk('local')->put($path, $response->body());

            $memo->forceFill([
                'file_path' => $path,
                'status' => Memo::STATUS_EDITED,
                'searchable_text' => $memo->searchable_text ?: $memo->title,
            ])->save();
        }

        return response()->json(['error' => 0]);
    }

    protected function extractToken(Request $request): ?string
    {
        $authorization = (string) $request->header('Authorization', '');

        if (str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        $token = $request->input('token');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
