<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Store one structured artifact emitted by an auto-coding task run.
 *
 * @property int $task_run_id
 * @property string $type
 * @property string $label
 * @property array<string, mixed>|array<int, array<string, string>>|null $payload
 */
class AutoCodingRunArtifact extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_run_id',
        'type',
        'label',
        'payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * Get the owning run for this artifact.
     *
     * @return BelongsTo<AutoCodingTaskRun, $this>
     */
    public function taskRun(): BelongsTo
    {
        return $this->belongsTo(AutoCodingTaskRun::class, 'task_run_id');
    }
}
