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
     * @param  array<string, mixed>  $machineContext
     * @return array{
     *   machine: array<string, mixed>,
     *   task: array<string, mixed>|null,
     *   run: array<string, mixed>|null,
     *   claimed: bool,
     *   executed: bool
     * }
     */
    public function runCycle(?string $repositoryPath = null, bool $shouldExecute = true, array $machineContext = []): array
    {
        $effectiveRepositoryPath = $this->resolveRepositoryPath($repositoryPath);
        $machine = $this->recordHeartbeat($effectiveRepositoryPath, 'idle', $machineContext);
        $task = $this->localAutoCodingTaskService->claimNextPendingTask($effectiveRepositoryPath, $machine);

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

        $machine = $this->recordHeartbeat($effectiveRepositoryPath, 'busy', $machineContext);

        try {
            $run = $this->localAutoCodingTaskService->executePendingTask($task->id, $machine);
        } finally {
            $machine = $this->recordHeartbeat($effectiveRepositoryPath, 'idle', $machineContext);
        }

        return [
            'machine' => $this->buildMachinePayload($machine),
            'task' => $this->buildTaskPayload($task),
            'run' => $this->buildRunPayload($run),
            'claimed' => true,
            'executed' => true,
        ];
    }

    /**
     * Persist one worker heartbeat with machine availability and routing context.
     *
     * @param  string  $repositoryPath
     * @param  string  $availabilityStatus
     * @param  array<string, mixed>  $machineContext
     * @return AutoCodingMachine
     */
    protected function recordHeartbeat(
        string $repositoryPath,
        string $availabilityStatus,
        array $machineContext,
    ): AutoCodingMachine {
        $machine = $this->localMachineService->resolve($repositoryPath);

        return $this->localMachineService->recordHeartbeat([
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'availability_status' => $availabilityStatus,
            'repository_path' => $repositoryPath,
            'capabilities' => $this->resolveCapabilities($machineContext),
            'workspace_bindings' => $this->resolveWorkspaceBindings($repositoryPath, $machineContext),
            'max_parallel_tasks' => $this->resolveMaxParallelTasks($machineContext),
            'metadata' => array_merge(is_array($machine->metadata) ? $machine->metadata : [], [
                'worker_mode' => 'local',
            ]),
        ]);
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
     * Resolve worker capability flags from runtime context or config.
     *
     * @param  array<string, mixed>  $machineContext
     * @return array<string, bool>
     */
    protected function resolveCapabilities(array $machineContext): array
    {
        $configuredCapabilities = config('opas.auto_coding.local_worker.capabilities', []);
        $capabilities = is_array($machineContext['capabilities'] ?? null)
            ? $machineContext['capabilities']
            : (is_array($configuredCapabilities) ? $configuredCapabilities : []);
        $capabilityFlags = [];

        foreach ($capabilities as $key => $capability) {
            $capabilityName = is_string($key) && is_bool($capability) ? $key : $capability;

            if (! is_string($capabilityName) || trim($capabilityName) === '') {
                continue;
            }

            $capabilityFlags[strtolower(trim($capabilityName))] = is_bool($capability) ? $capability : true;
        }

        return $capabilityFlags;
    }

    /**
     * Resolve worker workspace bindings from runtime context or config.
     *
     * @param  string  $repositoryPath
     * @param  array<string, mixed>  $machineContext
     * @return array<int, array<string, mixed>>
     */
    protected function resolveWorkspaceBindings(string $repositoryPath, array $machineContext): array
    {
        $bindings = is_array($machineContext['workspace_bindings'] ?? null)
            ? $this->normalizeWorkspaceBindingRows($machineContext['workspace_bindings'])
            : $this->parseConfiguredWorkspaceBindings();

        if ($bindings === []) {
            return [[
                'repository_path' => $repositoryPath,
                'workspace_path' => $repositoryPath,
                'active_branch' => null,
            ]];
        }

        return $bindings;
    }

    /**
     * Normalize runtime workspace binding rows into the heartbeat payload shape.
     *
     * @param  array<mixed>  $workspaceBindings
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeWorkspaceBindingRows(array $workspaceBindings): array
    {
        $bindings = [];

        foreach ($workspaceBindings as $binding) {
            if (! is_array($binding) || ! is_string($binding['repository_path'] ?? null)) {
                continue;
            }

            $repositoryPath = trim((string) $binding['repository_path']);

            if ($repositoryPath === '') {
                continue;
            }

            $workspacePath = is_string($binding['workspace_path'] ?? null)
                ? trim((string) $binding['workspace_path'])
                : $repositoryPath;
            $activeBranch = is_string($binding['active_branch'] ?? null)
                ? trim((string) $binding['active_branch'])
                : null;

            $bindings[] = [
                'repository_path' => $repositoryPath,
                'workspace_path' => $workspacePath !== '' ? $workspacePath : $repositoryPath,
                'active_branch' => $activeBranch !== '' ? $activeBranch : null,
            ];
        }

        return $bindings;
    }

    /**
     * Parse configured workspace binding strings into heartbeat payload rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseConfiguredWorkspaceBindings(): array
    {
        $configuredBindings = config('opas.auto_coding.local_worker.workspace_bindings', []);

        if (! is_array($configuredBindings)) {
            return [];
        }

        $bindings = [];

        foreach ($configuredBindings as $configuredBinding) {
            if (! is_string($configuredBinding) || trim($configuredBinding) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $configuredBinding));
            $repositoryPath = $parts[0];

            if ($repositoryPath === '') {
                continue;
            }

            $bindings[] = [
                'repository_path' => $repositoryPath,
                'workspace_path' => $parts[1] ?? $repositoryPath,
                'active_branch' => $parts[2] ?? null,
            ];
        }

        return $bindings;
    }

    /**
     * Resolve worker parallelism from runtime context or config.
     *
     * @param  array<string, mixed>  $machineContext
     * @return int
     */
    protected function resolveMaxParallelTasks(array $machineContext): int
    {
        $maxParallelTasks = $machineContext['max_parallel_tasks']
            ?? config('opas.auto_coding.local_worker.max_parallel_tasks', 1);

        return is_numeric($maxParallelTasks) && (int) $maxParallelTasks > 0
            ? min((int) $maxParallelTasks, 10)
            : 1;
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
            'availability_status' => $machine->availability_status,
            'capabilities' => $machine->capabilities,
            'workspace_bindings' => $machine->workspace_bindings,
            'max_parallel_tasks' => $machine->max_parallel_tasks,
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
            'assigned_machine_id' => $task->assigned_machine_id,
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
