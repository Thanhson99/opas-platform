<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoCodingExecutionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Record one execution run for an autonomous coding task.
 *
 * @property int $task_id
 * @property int $machine_id
 * @property AutoCodingExecutionStatus $status
 * @property array<string, mixed> $repository_snapshot
 * @property array<int, array<string, string>>|null $changed_files
 * @property array<string, mixed>|null $provider_result
 * @property array<string, mixed>|null $validation_results
 * @property array<string, mixed>|null $final_report
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class AutoCodingTaskRun extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'machine_id',
        'status',
        'repository_snapshot',
        'changed_files',
        'provider_result',
        'validation_results',
        'final_report',
        'started_at',
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
            'repository_snapshot' => 'array',
            'changed_files' => 'array',
            'provider_result' => 'array',
            'validation_results' => 'array',
            'final_report' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the parent task for this run.
     *
     * @return BelongsTo<AutoCodingTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AutoCodingTask::class, 'task_id');
    }

    /**
     * Get the machine that executed this run.
     *
     * @return BelongsTo<AutoCodingMachine, $this>
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(AutoCodingMachine::class, 'machine_id');
    }

    /**
     * Get the structured artifacts emitted during this run.
     *
     * @return HasMany<AutoCodingRunArtifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(AutoCodingRunArtifact::class, 'task_run_id');
    }
}
