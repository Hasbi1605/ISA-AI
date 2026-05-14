<?php

namespace App\Services\OnlyOffice;

use App\Models\Memo;
use App\Models\MemoVersion;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class MemoDocumentKey
{
    public function forEditor(Memo $memo, ?MemoVersion $version = null): string
    {
        $cacheKey = $this->editorCacheKey($memo, $version);

        // Cache the key computed at editor-open time so it stays stable for
        // the duration of the editor session (up to 24 hours). This prevents
        // the key from changing after a save callback updates updated_at,
        // which would cause subsequent callbacks to be rejected as stale.
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($memo, $version) {
            return $this->baseKey($memo, $version);
        });
    }

    public function invalidateEditorKey(Memo $memo, ?MemoVersion $version = null): void
    {
        Cache::forget($this->editorCacheKey($memo, $version));
    }

    public function forConversion(Memo $memo, ?MemoVersion $version = null): string
    {
        return $this->baseKey($memo, $version).'-pdf';
    }

    /**
     * Generate a short-lived, memo-bound token for the signed file URL.
     * The token is stored in cache and validated in MemoFileController::signed().
     * Multi-use within TTL — OnlyOffice may request the file more than once per session.
     */
    public function generateFileToken(Memo $memo, ?int $versionId, int $ttlMinutes): string
    {
        $token = Str::random(40);
        Cache::put(
            'oo_file_token:'.$token,
            ['memo_id' => $memo->id, 'version_id' => $versionId],
            now()->addMinutes($ttlMinutes + 5)
        );

        return $token;
    }

    /**
     * Validate an oo_token from a signed file request.
     * Returns true only if the token exists in cache and belongs to the given memo.
     */
    public function validateFileToken(string $token, Memo $memo): bool
    {
        $data = Cache::get('oo_file_token:'.$token);

        if ($data === null) {
            return false;
        }

        return (int) ($data['memo_id'] ?? 0) === (int) $memo->id;
    }

    protected function baseKey(Memo $memo, ?MemoVersion $version = null): string
    {
        $timestamp = $version?->updated_at?->timestamp
            ?? $memo->updated_at?->timestamp
            ?? now()->timestamp;
        $path = $version?->file_path ?: ($memo->file_path ?: '');
        $pathHash = substr(sha1($path), 0, 12);
        $scope = $version ? 'v'.$version->id : 'current';

        return 'memo-'.$memo->id.'-'.$scope.'-'.$timestamp.'-'.$pathHash;
    }

    protected function editorCacheKey(Memo $memo, ?MemoVersion $version = null): string
    {
        return 'onlyoffice_doc_key:'.$memo->id.':'.($version?->id ?? 'base');
    }
}
