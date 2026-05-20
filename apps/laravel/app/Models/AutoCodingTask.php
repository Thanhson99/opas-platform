<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoCodingExecutionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store one logical local coding task request.
 *
 * @property string $summary
 * @property string|null $issue_key
 * @property string $repository_path
 * @property string|null $branch_name
 * @property AutoCodingExecutionStatus $status
 * @property array<string, mixed>|null $context_payload
 * @property array<string, mixed>|null $latest_report
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class AutoCodingTask extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'summary',
        'issue_key',
        'repository_path',
        'branch_name',
        'status',
        'context_payload',
        'latest_report',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AutoCodingExecutionStatus::class,
            'context_payload' => 'array',
            'latest_report' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the runs recorded for this task.
     *
     * @return HasMany<AutoCodingTaskRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AutoCodingTaskRun::class, 'task_id');
    }
}
