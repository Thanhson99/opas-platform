<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoCodingExecutionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store one logical local coding task request.
 *
 * @property string $summary
 * @property string|null $issue_key
 * @property string $repository_path
 * @property string|null $branch_name
 * @property int|null $assigned_machine_id
 * @property \Illuminate\Support\Carbon|null $claimed_at
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
        'assigned_machine_id',
        'claimed_at',
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
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the machine currently assigned to execute this task.
     *
     * @return BelongsTo<AutoCodingMachine, $this>
     */
    public function assignedMachine(): BelongsTo
    {
        return $this->belongsTo(AutoCodingMachine::class, 'assigned_machine_id');
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
