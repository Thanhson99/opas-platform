<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoCodingTaskRunResource extends JsonResource
{
    /**
     * Transform one local auto-coding task run into the admin-facing API contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AutoCodingTaskRun $run */
        $run = $this->resource;
        /** @var AutoCodingTask|null $task */
        $task = $run->task;
        /** @var AutoCodingMachine|null $machine */
        $machine = $run->machine;

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'started_at' => $run->started_at instanceof DateTimeInterface
                ? $run->started_at->format(DateTimeInterface::ATOM)
                : null,
            'completed_at' => $run->completed_at instanceof DateTimeInterface
                ? $run->completed_at->format(DateTimeInterface::ATOM)
                : null,
            'task' => $task instanceof AutoCodingTask ? [
                'id' => $task->id,
                'summary' => $task->summary,
                'issue_key' => $task->issue_key,
                'status' => $task->status->value,
            ] : null,
            'machine' => $machine instanceof AutoCodingMachine ? [
                'id' => $machine->id,
                'machine_key' => $machine->machine_key,
                'hostname' => $machine->hostname,
                'operating_system' => $machine->operating_system,
            ] : null,
            'artifact_count' => $run->artifacts->count(),
            'artifact_types' => $run->artifacts->pluck('type')->values()->all(),
            'changed_files' => $run->changed_files,
            'provider_result' => $run->provider_result,
            'validation_results' => $run->validation_results,
            'final_report' => $run->final_report,
        ];
    }
}
