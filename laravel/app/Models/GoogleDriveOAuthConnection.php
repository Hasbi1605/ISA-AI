<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveOAuthConnection extends Model
{
    use HasFactory;

    public const PROVIDER = 'google_drive';

    protected $table = 'google_drive_oauth_connections';

    protected $fillable = [
        'provider',
        'account_email',
        'access_token',
        'refresh_token',
        'token_type',
        'scope',
        'expires_at',
        'connected_by_user_id',
        'last_refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'last_refreshed_at' => 'datetime',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public static function central(): ?self
    {
        return self::query()
            ->where('provider', self::PROVIDER)
            ->first();
    }
}
