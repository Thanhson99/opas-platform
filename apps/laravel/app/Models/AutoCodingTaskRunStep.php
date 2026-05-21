<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AutoCodingWorkflowStep;
use App\Enums\AutoCodingWorkflowStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persist one workflow step attempt for an auto-coding run.
 *
 * @property int $task_run_id
 * @property AutoCodingWorkflowStep $step_key
 * @property int $sequence
 * @property int $attempt
 * @property AutoCodingWorkflowStepStatus $status
 * @property bool $is_retryable
 * @property array<string, mixed>|null $input_payload
 * @property array<string, mixed>|null $output_payload
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class AutoCodingTaskRunStep extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_run_id',
        'step_key',
        'sequence',
        'attempt',
        'status',
        'is_retryable',
        'input_payload',
        'output_payload',
        'error_message',
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
            'step_key' => AutoCodingWorkflowStep::class,
            'status' => AutoCodingWorkflowStepStatus::class,
            'is_retryable' => 'boolean',
            'input_payload' => 'array',
            'output_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the parent run that owns this workflow step attempt.
     *
     * @return BelongsTo<AutoCodingTaskRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AutoCodingTaskRun::class, 'task_run_id');
    }
}
