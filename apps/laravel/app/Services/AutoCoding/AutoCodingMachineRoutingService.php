<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Repositories\AutoCoding\Interfaces\AutoCodingMachineRepositoryInterface;
use App\Support\RepositoryPathMatcher;
use Illuminate\Support\Facades\DB;

class AutoCodingMachineRoutingService
{
    public function __construct(
        private readonly AutoCodingMachineRepositoryInterface $machineRepository,
    ) {}

    /**
     * Assign one pending task to the best available machine for its repository context.
     *
     * @param  AutoCodingTask  $task
     * @param  array<int, string>  $requiredCapabilities
     * @param  string|null  $preferredMachineKey
     * @return AutoCodingTask
     */
    public function routePendingTask(
        AutoCodingTask $task,
        array $requiredCapabilities = [],
        ?string $preferredMachineKey = null,
    ): AutoCodingTask {
        if ($task->status !== AutoCodingExecutionStatus::Pending) {
            return $task;
        }

        $requiredCapabilities = $this->normalizeCapabilityNames($requiredCapabilities);

        return DB::transaction(function () use ($task, $requiredCapabilities, $preferredMachineKey): AutoCodingTask {
            $lockedTask = $this->lockRoutableTask($task);

            if (! $lockedTask instanceof AutoCodingTask) {
                $freshTask = $task->fresh(['runs.artifacts', 'runs.machine', 'runs.steps', 'assignedMachine']);

                return $freshTask instanceof AutoCodingTask ? $freshTask : $task;
            }

            $routingDecision = $this->resolveRoutingDecision($lockedTask, $requiredCapabilities, $preferredMachineKey);
            $machine = $routingDecision['machine'];
            $routingReport = $this->buildRoutingReport(
                $routingDecision,
                $requiredCapabilities,
                $preferredMachineKey,
            );
            $contextPayload = is_array($lockedTask->context_payload) ? $lockedTask->context_payload : [];

            $lockedTask->update([
                'assigned_machine_id' => $machine?->id,
                'context_payload' => array_merge($contextPayload, [
                    'routing' => $routingReport,
                ]),
                'latest_report' => array_merge($lockedTask->latest_report ?? [], [
                    'routing' => $routingReport,
                ]),
            ]);

            /** @var AutoCodingTask $freshTask */
            $freshTask = $lockedTask->fresh(['runs.artifacts', 'runs.machine', 'runs.steps', 'assignedMachine']);

            return $freshTask;
        });
    }

    /**
     * Lock and revalidate one task before writing routing ownership.
     *
     * @param  AutoCodingTask  $task
     * @return AutoCodingTask|null
     */
    protected function lockRoutableTask(AutoCodingTask $task): ?AutoCodingTask
    {
        /** @var AutoCodingTask|null $lockedTask */
        $lockedTask = AutoCodingTask::query()
            ->whereKey($task->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedTask instanceof AutoCodingTask) {
            return null;
        }

        if ($lockedTask->status !== AutoCodingExecutionStatus::Pending) {
            return null;
        }

        if ($lockedTask->repository_path !== $task->repository_path) {
            return null;
        }

        if ($task->assigned_machine_id === null) {
            return $lockedTask->assigned_machine_id === null ? $lockedTask : null;
        }

        return (int) $lockedTask->assigned_machine_id === (int) $task->assigned_machine_id
            ? $lockedTask
            : null;
    }

    /**
     * Determine whether one machine can safely claim a new task now.
     *
     * @param  AutoCodingMachine  $machine
     * @return bool
     */
    public function canClaimNewTask(AutoCodingMachine $machine): bool
    {
        if (! $this->isOnline($machine) || ! $this->isAcceptingWork($machine)) {
            return false;
        }

        return $this->hasAvailableCapacity($machine);
    }

    /**
     * Reroute a pending task when its assigned machine can no longer claim new work.
     *
     * @param  AutoCodingTask  $task
     * @return AutoCodingTask
     */
    public function rerouteIfAssignedMachineUnavailable(AutoCodingTask $task): AutoCodingTask
    {
        if ($task->status !== AutoCodingExecutionStatus::Pending || $task->assigned_machine_id === null) {
            return $task;
        }

        $assignedMachine = $task->assignedMachine instanceof AutoCodingMachine
            ? $task->assignedMachine
            : AutoCodingMachine::query()->find($task->assigned_machine_id);

        if ($assignedMachine instanceof AutoCodingMachine && $this->canClaimNewTask($assignedMachine)) {
            return $task;
        }

        return $this->routePendingTask($task, $this->resolveRequiredCapabilities($task), null);
    }

    /**
     * Determine whether a machine exposes a workspace for one repository path.
     *
     * @param  AutoCodingMachine  $machine
     * @param  string  $repositoryPath
     * @return bool
     */
    public function machineMatchesRepository(AutoCodingMachine $machine, string $repositoryPath): bool
    {
        if (is_string($machine->repository_path) && RepositoryPathMatcher::matches($machine->repository_path, $repositoryPath)) {
            return true;
        }

        $workspaceBindings = $machine->workspace_bindings ?? [];

        foreach ($workspaceBindings as $binding) {
            if (is_string($binding['repository_path'] ?? null) && RepositoryPathMatcher::matches($binding['repository_path'], $repositoryPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the best currently available machine for one task.
     *
     * @param  AutoCodingTask  $task
     * @param  array<int, string>  $requiredCapabilities
     * @param  string|null  $preferredMachineKey
     * @return array{machine:AutoCodingMachine|null,candidate_count:int,unassigned_reason:string|null}
     */
    protected function resolveRoutingDecision(
        AutoCodingTask $task,
        array $requiredCapabilities,
        ?string $preferredMachineKey,
    ): array {
        $machines = $this->machineRepository->getLatestForRepository($task->repository_path);
        $candidateCount = count($machines);
        $preferredMachineWasSeen = false;
        $hasOnlineMachine = false;
        $hasAcceptingMachine = false;
        $hasCapacity = false;

        foreach ($machines as $machine) {
            if ($preferredMachineKey !== null && $machine->machine_key !== $preferredMachineKey) {
                continue;
            }

            $preferredMachineWasSeen = true;

            if (! $this->isOnline($machine)) {
                continue;
            }

            $hasOnlineMachine = true;

            if (! $this->isAcceptingWork($machine)) {
                continue;
            }

            $hasAcceptingMachine = true;

            if (! $this->hasAvailableAssignmentCapacity($machine)) {
                continue;
            }

            $hasCapacity = true;

            if ($this->machineHasCapabilities($machine, $requiredCapabilities)) {
                return [
                    'machine' => $machine,
                    'candidate_count' => $candidateCount,
                    'unassigned_reason' => null,
                ];
            }
        }

        return [
            'machine' => null,
            'candidate_count' => $candidateCount,
            'unassigned_reason' => $this->resolveUnassignedReason(
                $candidateCount,
                $preferredMachineKey,
                $preferredMachineWasSeen,
                $hasOnlineMachine,
                $hasAcceptingMachine,
                $hasCapacity,
            ),
        ];
    }

    /**
     * Determine whether one machine has all requested capability flags.
     *
     * @param  AutoCodingMachine  $machine
     * @param  array<int, string>  $requiredCapabilities
     * @return bool
     */
    protected function machineHasCapabilities(AutoCodingMachine $machine, array $requiredCapabilities): bool
    {
        if ($requiredCapabilities === []) {
            return true;
        }

        $capabilities = $this->normalizeCapabilityFlags($machine->capabilities);

        foreach ($requiredCapabilities as $capability) {
            if (($capabilities[$capability] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize machine capability flags read from persisted machine records.
     *
     * @param  mixed  $capabilities
     * @return array<string, bool>
     */
    protected function normalizeCapabilityFlags(mixed $capabilities): array
    {
        if (! is_array($capabilities)) {
            return [];
        }

        $capabilityFlags = [];

        foreach ($capabilities as $key => $value) {
            $capabilityName = is_string($key) ? $key : $value;

            if (! is_string($capabilityName) || trim($capabilityName) === '') {
                continue;
            }

            $capabilityFlags[strtolower(trim($capabilityName))] = is_bool($value) ? $value : true;
        }

        return $capabilityFlags;
    }

    /**
     * Normalize required capability names for routing comparisons and reports.
     *
     * @param  array<mixed>  $capabilities
     * @return array<int, string>
     */
    protected function normalizeCapabilityNames(array $capabilities): array
    {
        $capabilityNames = [];

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                continue;
            }

            $capabilityNames[] = strtolower(trim($capability));
        }

        return array_values(array_unique($capabilityNames));
    }

    /**
     * Resolve required capability names already stored on one task routing context.
     *
     * @param  AutoCodingTask  $task
     * @return array<int, string>
     */
    protected function resolveRequiredCapabilities(AutoCodingTask $task): array
    {
        $routing = is_array($task->context_payload['routing'] ?? null)
            ? $task->context_payload['routing']
            : [];
        $requiredCapabilities = is_array($routing['required_capabilities'] ?? null)
            ? $routing['required_capabilities']
            : [];

        return $this->normalizeCapabilityNames($requiredCapabilities);
    }

    /**
     * Determine whether one machine heartbeat is still fresh.
     *
     * @param  AutoCodingMachine  $machine
     * @return bool
     */
    protected function isOnline(AutoCodingMachine $machine): bool
    {
        if ($machine->last_seen_at === null) {
            return false;
        }

        $staleSeconds = config('opas.auto_coding.machine_stale_seconds');
        $threshold = is_numeric($staleSeconds) && (int) $staleSeconds > 0 ? (int) $staleSeconds : 300;

        return $machine->last_seen_at->diffInSeconds(now()) <= $threshold;
    }

    /**
     * Determine whether one reported availability state accepts new work.
     *
     * @param  AutoCodingMachine  $machine
     * @return bool
     */
    protected function isAcceptingWork(AutoCodingMachine $machine): bool
    {
        return in_array($machine->availability_status, ['idle'], true);
    }

    /**
     * Count active tasks already assigned to one machine.
     *
     * @param  AutoCodingMachine  $machine
     * @return int
     */
    protected function activeAssignedTaskCount(AutoCodingMachine $machine): int
    {
        return AutoCodingTask::query()
            ->where('assigned_machine_id', $machine->id)
            ->whereIn('status', [
                AutoCodingExecutionStatus::Running->value,
            ])
            ->count();
    }

    /**
     * Count pending or running tasks already reserved for one machine.
     *
     * @param  AutoCodingMachine  $machine
     * @return int
     */
    protected function reservedAssignedTaskCount(AutoCodingMachine $machine): int
    {
        return AutoCodingTask::query()
            ->where('assigned_machine_id', $machine->id)
            ->whereIn('status', [
                AutoCodingExecutionStatus::Pending->value,
                AutoCodingExecutionStatus::Running->value,
            ])
            ->count();
    }

    /**
     * Determine whether one machine has remaining task capacity.
     *
     * @param  AutoCodingMachine  $machine
     * @return bool
     */
    protected function hasAvailableCapacity(AutoCodingMachine $machine): bool
    {
        return $this->activeAssignedTaskCount($machine) < max(1, (int) $machine->max_parallel_tasks);
    }

    /**
     * Determine whether one machine can receive another pending assignment.
     *
     * @param  AutoCodingMachine  $machine
     * @return bool
     */
    protected function hasAvailableAssignmentCapacity(AutoCodingMachine $machine): bool
    {
        return $this->reservedAssignedTaskCount($machine) < max(1, (int) $machine->max_parallel_tasks);
    }

    /**
     * Resolve why no candidate machine could be assigned.
     *
     * @param  int  $candidateCount
     * @param  string|null  $preferredMachineKey
     * @param  bool  $preferredMachineWasSeen
     * @param  bool  $hasOnlineMachine
     * @param  bool  $hasAcceptingMachine
     * @param  bool  $hasCapacity
     * @return string
     */
    protected function resolveUnassignedReason(
        int $candidateCount,
        ?string $preferredMachineKey,
        bool $preferredMachineWasSeen,
        bool $hasOnlineMachine,
        bool $hasAcceptingMachine,
        bool $hasCapacity,
    ): string {
        if ($candidateCount === 0) {
            return 'no_repository_binding';
        }

        if ($preferredMachineKey !== null && ! $preferredMachineWasSeen) {
            return 'preferred_machine_not_bound';
        }

        if (! $hasOnlineMachine) {
            return 'no_online_machine';
        }

        if (! $hasAcceptingMachine) {
            return 'no_idle_machine';
        }

        if (! $hasCapacity) {
            return 'capacity_full';
        }

        return 'missing_capability';
    }

    /**
     * Build the persisted routing report fragment for task context and API responses.
     *
     * @param  array{machine:AutoCodingMachine|null,candidate_count:int,unassigned_reason:string|null}  $routingDecision
     * @param  array<int, string>  $requiredCapabilities
     * @param  string|null  $preferredMachineKey
     * @return array<string, mixed>
     */
    protected function buildRoutingReport(
        array $routingDecision,
        array $requiredCapabilities,
        ?string $preferredMachineKey,
    ): array {
        $machine = $routingDecision['machine'];

        return [
            'status' => $machine instanceof AutoCodingMachine ? 'assigned' : 'unassigned',
            'assigned_machine_id' => $machine?->id,
            'assigned_machine_key' => $machine?->machine_key,
            'candidate_machine_count' => $routingDecision['candidate_count'],
            'unassigned_reason' => $routingDecision['unassigned_reason'],
            'preferred_machine_key' => $preferredMachineKey,
            'required_capabilities' => array_values($requiredCapabilities),
            'routed_at' => now()->toIso8601String(),
        ];
    }
}
