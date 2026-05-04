<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CloudStorageFile extends Model
{
    use HasFactory;

    public const DIRECTION_IMPORT = 'import';

    public const DIRECTION_EXPORT = 'export';

    protected $fillable = [
        'user_id',
        'provider',
        'direction',
        'local_type',
        'local_id',
        'external_id',
        'name',
        'mime_type',
        'web_view_link',
        'folder_external_id',
        'size_bytes',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'local_id' => 'integer',
            'size_bytes' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function local(): MorphTo
    {
        return $this->morphTo();
    }
}
