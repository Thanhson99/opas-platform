<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthProviderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property string $display_name
 * @property AuthProviderType $type
 * @property bool $enabled
 * @property int $sort_order
 * @property string $visibility
 * @property string|null $icon
 * @property array<string, mixed> $capabilities
 * @property array<string, mixed> $public_config
 * @property array<string, mixed> $secret_config
 * @property string|null $email_verification_mode
 */
class AuthProvider extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'display_name',
        'type',
        'enabled',
        'sort_order',
        'visibility',
        'icon',
        'capabilities',
        'public_config',
        'secret_config',
        'email_verification_mode',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AuthProviderType::class,
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'capabilities' => 'array',
            'public_config' => 'array',
            'secret_config' => 'encrypted:array',
        ];
    }
}
