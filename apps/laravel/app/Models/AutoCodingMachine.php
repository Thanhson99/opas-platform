<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represent one local machine that can execute autonomous coding tasks.
 *
 * @property string $machine_key
 * @property string $hostname
 * @property string $operating_system
 * @property string $availability_status
 * @property string|null $repository_path
 * @property string|null $access_token_hash
 * @property \Illuminate\Support\Carbon|null $access_token_last_used_at
 * @property array<string, mixed>|null $capabilities
 * @property array<int, array<string, mixed>>|null $workspace_bindings
 * @property int $max_parallel_tasks
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 */
class AutoCodingMachine extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'machine_key',
        'hostname',
        'operating_system',
        'availability_status',
        'repository_path',
        'access_token_hash',
        'access_token_last_used_at',
        'capabilities',
        'workspace_bindings',
        'max_parallel_tasks',
        'metadata',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'workspace_bindings' => 'array',
            'max_parallel_tasks' => 'integer',
            'metadata' => 'array',
            'access_token_last_used_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the task runs executed by this machine.
     *
     * @return HasMany<AutoCodingTaskRun, $this>
     */
    public function taskRuns(): HasMany
    {
        return $this->hasMany(AutoCodingTaskRun::class, 'machine_id');
    }
}
