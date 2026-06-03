<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $key
 * @property string $display_name
 * @property string $purpose
 * @property string $environment
 * @property string|null $machine_group
 * @property bool $enabled
 * @property bool $is_default
 * @property string $locale
 * @property string|null $api_base_url
 * @property array<int, string> $allowed_chat_ids
 * @property array<int, string> $allowed_user_ids
 * @property array<int, string> $allowed_actions
 * @property array<string, mixed> $public_config
 * @property array<string, mixed> $secret_config
 */
class TelegramBotConfig extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'display_name',
        'purpose',
        'environment',
        'machine_group',
        'enabled',
        'is_default',
        'locale',
        'api_base_url',
        'allowed_chat_ids',
        'allowed_user_ids',
        'allowed_actions',
        'public_config',
        'secret_config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'allowed_chat_ids' => 'array',
            'allowed_user_ids' => 'array',
            'allowed_actions' => 'array',
            'public_config' => 'array',
            'secret_config' => 'encrypted:array',
        ];
    }

    /**
     * @return HasMany<TelegramBotConfigAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(TelegramBotConfigAudit::class)->latest('created_at');
    }
}
