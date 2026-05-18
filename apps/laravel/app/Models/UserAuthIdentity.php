<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property string $provider_key
 * @property string $provider_user_id
 * @property string|null $provider_email
 * @property array<string, mixed> $metadata
 * @property string|null $access_token
 * @property string|null $refresh_token
 */
class UserAuthIdentity extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider_key',
        'provider_user_id',
        'provider_email',
        'metadata',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
