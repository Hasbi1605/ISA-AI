<?php

namespace App\Services\OnlyOffice;

use App\Models\Memo;
use App\Models\MemoVersion;

class MemoDocumentKey
{
    public function forEditor(Memo $memo, ?MemoVersion $version = null): string
    {
        return $this->baseKey($memo, $version);
    }

    public function forConversion(Memo $memo, ?MemoVersion $version = null): string
    {
        return $this->baseKey($memo, $version).'-pdf';
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
}
