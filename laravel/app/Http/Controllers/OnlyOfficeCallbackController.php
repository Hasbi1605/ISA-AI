<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use App\Services\OnlyOffice\JwtSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class OnlyOfficeCallbackController extends Controller
{
    public function __invoke(Request $request, Memo $memo): JsonResponse
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Token OnlyOffice wajib dikirim.');
        }

        try {
            $payload = app(JwtSigner::class)->verify($token);
        } catch (RuntimeException) {
            abort(Response::HTTP_UNAUTHORIZED, 'Token OnlyOffice tidak valid.');
        }

        $callback = $this->normalizeSignedCallbackPayload($request, $payload);
        $this->validateSignedCallbackPayload($memo, $callback);
        $status = (int) $callback['status'];

        if (in_array($status, [2, 6], true)) {
            $url = (string) $callback['url'];
            abort_if($url === '', Response::HTTP_BAD_REQUEST, 'URL file OnlyOffice kosong.');
            abort_unless($this->isTrustedOnlyOfficeUrl($url), Response::HTTP_FORBIDDEN, 'URL file OnlyOffice tidak dipercaya.');

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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeSignedCallbackPayload(Request $request, array $payload): array
    {
        $callback = isset($payload['payload']) && is_array($payload['payload'])
            ? $payload['payload']
            : $payload;

        foreach (['status', 'key', 'url'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            abort_unless(($callback[$field] ?? null) === $request->input($field), Response::HTTP_FORBIDDEN);
        }

        return $callback;
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    protected function validateSignedCallbackPayload(Memo $memo, array $callback): void
    {
        abort_unless(isset($callback['status']) && is_numeric($callback['status']), Response::HTTP_FORBIDDEN);

        $key = (string) ($callback['key'] ?? '');
        abort_if($key === '', Response::HTTP_BAD_REQUEST, 'Key OnlyOffice wajib dikirim.');
        abort_unless(str_starts_with($key, 'memo-'.$memo->id.'-'), Response::HTTP_FORBIDDEN);

        if (in_array((int) $callback['status'], [2, 6], true)) {
            $url = (string) ($callback['url'] ?? '');
            abort_if($url === '', Response::HTTP_BAD_REQUEST, 'URL file OnlyOffice kosong.');
        }
    }

    protected function isTrustedOnlyOfficeUrl(string $url): bool
    {
        $candidate = parse_url($url);
        $trusted = parse_url((string) config('services.onlyoffice.internal_url'));

        if (! is_array($candidate) || ! is_array($trusted)) {
            return false;
        }

        if (! in_array($candidate['scheme'] ?? '', ['http', 'https'], true)) {
            return false;
        }

        if (($candidate['host'] ?? null) !== ($trusted['host'] ?? null)) {
            return false;
        }

        $trustedPort = $trusted['port'] ?? null;

        return $trustedPort === null || ($candidate['port'] ?? null) === $trustedPort;
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
