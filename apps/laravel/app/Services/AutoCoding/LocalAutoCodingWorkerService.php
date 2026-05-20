<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;

class LocalAutoCodingWorkerService
{
    public function __construct(
        private readonly LocalMachineService $localMachineService,
        private readonly LocalAutoCodingTaskService $localAutoCodingTaskService,
    ) {}

    /**
     * Run one local worker cycle that heartbeats the machine, claims a task, and optionally executes it.
     *
     * @param  string|null  $repositoryPath
     * @param  bool  $shouldExecute
     * @return array{
     *   machine: array<string, mixed>,
     *   task: array<string, mixed>|null,
     *   run: array<string, mixed>|null,
     *   claimed: bool,
     *   executed: bool
     * }
     */
    public function runCycle(?string $repositoryPath = null, bool $shouldExecute = true): array
    {
        $effectiveRepositoryPath = $this->resolveRepositoryPath($repositoryPath);
        $machine = $this->localMachineService->resolve($effectiveRepositoryPath);
        $task = $this->localAutoCodingTaskService->claimNextPendingTask($effectiveRepositoryPath);

        if (! $task instanceof AutoCodingTask) {
            return [
                'machine' => $this->buildMachinePayload($machine),
                'task' => null,
                'run' => null,
                'claimed' => false,
                'executed' => false,
            ];
        }

        if (! $shouldExecute) {
            return [
                'machine' => $this->buildMachinePayload($machine),
                'task' => $this->buildTaskPayload($task),
                'run' => null,
                'claimed' => true,
                'executed' => false,
            ];
        }

        $run = $this->localAutoCodingTaskService->executePendingTask($task->id);

        return [
            'machine' => $this->buildMachinePayload($machine),
            'task' => $this->buildTaskPayload($task),
            'run' => $this->buildRunPayload($run),
            'claimed' => true,
            'executed' => true,
        ];
    }

    /**
     * Resolve the repository path used by one worker cycle.
     *
     * @param  string|null  $repositoryPath
     * @return string
     */
    protected function resolveRepositoryPath(?string $repositoryPath): string
    {
        if (is_string($repositoryPath) && trim($repositoryPath) !== '') {
            return trim($repositoryPath);
        }

        $configuredPath = config('opas.auto_coding.default_repository_path');

        return is_string($configuredPath) && trim($configuredPath) !== ''
            ? trim($configuredPath)
            : base_path('..');
    }

    /**
     * Build the compact machine payload returned by one worker cycle.
     *
     * @param  AutoCodingMachine  $machine
     * @return array<string, mixed>
     */
    protected function buildMachinePayload(AutoCodingMachine $machine): array
    {
        return [
            'id' => $machine->id,
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'repository_path' => $machine->repository_path,
        ];
    }

    /**
     * Build the compact task payload returned by one worker cycle.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    protected function buildTaskPayload(AutoCodingTask $task): array
    {
        return [
            'id' => $task->id,
            'summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'status' => $task->status->value,
            'repository_path' => $task->repository_path,
        ];
    }

    /**
     * Build the compact run payload returned by one worker cycle.
     *
     * @param  AutoCodingTaskRun  $run
     * @return array<string, mixed>
     */
    protected function buildRunPayload(AutoCodingTaskRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'task_id' => $run->task_id,
            'machine_id' => $run->machine_id,
        ];
    }
}
