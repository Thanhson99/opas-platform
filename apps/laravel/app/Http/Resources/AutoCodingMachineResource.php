<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTaskRun;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoCodingMachineResource extends JsonResource
{
    /**
     * Transform a local auto-coding machine into the admin-facing API contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AutoCodingMachine $machine */
        $machine = $this->resource;
        /** @var AutoCodingTaskRun|null $latestRun */
        $latestRun = $machine->taskRuns->sortByDesc('id')->first();

        return [
            'id' => $machine->id,
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'repository_path' => $machine->repository_path,
            'status' => $this->resolveStatus($machine),
            'last_seen_at' => $machine->last_seen_at instanceof DateTimeInterface
                ? $machine->last_seen_at->format(DateTimeInterface::ATOM)
                : null,
            'task_run_count' => $machine->taskRuns->count(),
            'latest_run' => $latestRun instanceof AutoCodingTaskRun ? [
                'id' => $latestRun->id,
                'task_id' => $latestRun->task_id,
                'status' => $latestRun->status->value,
                'started_at' => $latestRun->started_at instanceof DateTimeInterface
                    ? $latestRun->started_at->format(DateTimeInterface::ATOM)
                    : null,
                'completed_at' => $latestRun->completed_at instanceof DateTimeInterface
                    ? $latestRun->completed_at->format(DateTimeInterface::ATOM)
                    : null,
            ] : null,
            'metadata' => $machine->metadata,
        ];
    }

    /**
     * Resolve the derived machine availability status from the last seen time.
     *
     * @param  AutoCodingMachine  $machine
     * @return string
     */
    protected function resolveStatus(AutoCodingMachine $machine): string
    {
        if ($machine->last_seen_at === null) {
            return 'unknown';
        }

        $staleSeconds = config('opas.auto_coding.machine_stale_seconds', 300);
        $threshold = is_numeric($staleSeconds) && (int) $staleSeconds > 0 ? (int) $staleSeconds : 300;

        return $machine->last_seen_at->diffInSeconds(now()) <= $threshold ? 'online' : 'stale';
    }
}
