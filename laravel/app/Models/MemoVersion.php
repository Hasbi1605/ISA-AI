<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoVersion extends Model
{
    protected $fillable = [
        'memo_id',
        'version_number',
        'label',
        'file_path',
        'status',
        'configuration',
        'searchable_text',
        'revision_instruction',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
        ];
    }

    public function memo(): BelongsTo
    {
        return $this->belongsTo(Memo::class);
    }
}
