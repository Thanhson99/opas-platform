<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoCodingTaskResource extends JsonResource
{
    /**
     * Transform a local auto-coding task into the admin-facing API contract.
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

        return [
            'id' => $task->id,
            'summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'repository_path' => $task->repository_path,
            'branch_name' => $task->branch_name,
            'status' => $task->status->value,
            'completed_at' => $task->completed_at instanceof DateTimeInterface
                ? $task->completed_at->format(DateTimeInterface::ATOM)
                : null,
            'run_count' => $task->runs->count(),
            'latest_run' => $latestRun instanceof AutoCodingTaskRun ? [
                'id' => $latestRun->id,
                'status' => $latestRun->status->value,
                'artifact_count' => $latestRun->artifacts->count(),
                'started_at' => $latestRun->started_at instanceof DateTimeInterface
                    ? $latestRun->started_at->format(DateTimeInterface::ATOM)
                    : null,
                'completed_at' => $latestRun->completed_at instanceof DateTimeInterface
                    ? $latestRun->completed_at->format(DateTimeInterface::ATOM)
                    : null,
            ] : null,
            'artifact_types' => $latestRun instanceof AutoCodingTaskRun
                ? $latestRun->artifacts->pluck('type')->values()->all()
                : [],
            'latest_report' => $task->latest_report,
        ];
    }
}
