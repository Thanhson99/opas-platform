<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoCodingTaskStatusResource extends JsonResource
{
    /**
     * Transform one local auto-coding task into a compact polling contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AutoCodingTask $task */
        $task = $this->resource;
        /** @var AutoCodingTaskRun|null $latestRun */
        $latestRun = $task->runs->sortByDesc('id')->first();
        /** @var array<string, mixed>|null $latestReport */
        $latestReport = is_array($task->latest_report) ? $task->latest_report : null;
        /** @var array<string, mixed>|null $summary */
        $summary = is_array($latestReport['summary'] ?? null) ? $latestReport['summary'] : null;
        /** @var array<string, mixed>|null $validation */
        $validation = is_array($latestReport['validation'] ?? null) ? $latestReport['validation'] : null;
        /** @var array<string, mixed>|null $provider */
        $provider = is_array($latestReport['provider_result'] ?? null) ? $latestReport['provider_result'] : null;
        $artifactCount = $summary !== null && is_numeric($summary['artifact_count'] ?? null)
            ? (int) $summary['artifact_count']
            : 0;

        return [
            'id' => $task->id,
            'summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'status' => $task->status->value,
            'branch_name' => $task->branch_name,
            'repository_path' => $task->repository_path,
            'completed_at' => $task->completed_at instanceof DateTimeInterface
                ? $task->completed_at->format(DateTimeInterface::ATOM)
                : null,
            'latest_run' => $latestRun instanceof AutoCodingTaskRun ? [
                'id' => $latestRun->id,
                'machine_id' => $latestRun->machine_id,
                'status' => $latestRun->status->value,
                'started_at' => $latestRun->started_at instanceof DateTimeInterface
                    ? $latestRun->started_at->format(DateTimeInterface::ATOM)
                    : null,
                'completed_at' => $latestRun->completed_at instanceof DateTimeInterface
                    ? $latestRun->completed_at->format(DateTimeInterface::ATOM)
                    : null,
                'artifact_count' => $latestRun->artifacts->count(),
            ] : null,
            'machine' => $latestRun?->machine === null ? null : [
                'id' => $latestRun->machine->id,
                'machine_key' => $latestRun->machine->machine_key,
                'hostname' => $latestRun->machine->hostname,
                'operating_system' => $latestRun->machine->operating_system,
                'last_seen_at' => $latestRun->machine->last_seen_at instanceof DateTimeInterface
                    ? $latestRun->machine->last_seen_at->format(DateTimeInterface::ATOM)
                    : null,
            ],
            'progress' => [
                'artifact_count' => $artifactCount,
                'validation_status' => is_string($validation['overall_status'] ?? null)
                    ? $validation['overall_status']
                    : 'not_run',
                'provider_status' => is_string($provider['status'] ?? null)
                    ? $provider['status']
                    : 'unknown',
            ],
        ];
    }
}
