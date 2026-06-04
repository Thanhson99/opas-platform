<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
use App\Support\RepositoryPathMatcher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AutoCodingTaskRepository implements AutoCodingTaskRepositoryInterface
{
    /**
     * Return local auto-coding tasks with related runs for admin APIs.
     *
     * @param  string|null  $status
     * @param  string|null  $issueKey
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, AutoCodingTask>
     */
    public function paginateForAdmin(?string $status, ?string $issueKey, int $perPage): LengthAwarePaginator
    {
        return $this->baseDetailedQuery()
            ->when($status !== null && $status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($issueKey !== null && $issueKey !== '', fn (Builder $query) => $query->where('issue_key', $issueKey))
            ->paginate($perPage);
    }

    /**
     * Return the latest local auto-coding tasks for console listings.
     *
     * @param  int  $limit
     * @param  string|null  $status
     * @param  string|null  $issueKey
     * @return list<AutoCodingTask>
     */
    public function getLatest(int $limit, ?string $status, ?string $issueKey): array
    {
        /** @var list<AutoCodingTask> $tasks */
        $tasks = $this->baseDetailedQuery()
            ->when($status !== null && $status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($issueKey !== null && $issueKey !== '', fn (Builder $query) => $query->where('issue_key', $issueKey))
            ->limit($limit)
            ->get()
            ->all();

        return $tasks;
    }

    /**
     * Find one detailed local auto-coding task by id.
     *
     * @param  int  $taskId
     * @return AutoCodingTask|null
     */
    public function findDetailedById(int $taskId): ?AutoCodingTask
    {
        /** @var AutoCodingTask|null $task */
        $task = $this->baseDetailedQuery()->find($taskId);

        return $task;
    }

    /**
     * Find the oldest pending local auto-coding task, optionally constrained to one repository path.
     *
     * @param  string|null  $repositoryPath
     * @return AutoCodingTask|null
     */
    public function findOldestPending(?string $repositoryPath = null): ?AutoCodingTask
    {
        /** @var AutoCodingTask|null $task */
        $task = AutoCodingTask::query()
            ->when(
                $repositoryPath !== null && $repositoryPath !== '',
                fn (Builder $query) => $query->where('repository_path', $repositoryPath)
            )
            ->where('status', AutoCodingExecutionStatus::Pending->value)
            ->whereNull('assigned_machine_id')
            ->orderBy('id')
            ->first();

        return $task;
    }

    /**
     * Find the oldest pending task that one machine is allowed to claim.
     *
     * @param  AutoCodingMachine  $machine
     * @param  string|null  $repositoryPath
     * @return AutoCodingTask|null
     */
    public function findOldestPendingForMachine(AutoCodingMachine $machine, ?string $repositoryPath = null): ?AutoCodingTask
    {
        $claimableRepositoryPaths = $this->resolveClaimableRepositoryPaths($machine, $repositoryPath);

        if ($claimableRepositoryPaths === []) {
            return null;
        }

        $task = $this->requiresFullRepositoryMatcher($claimableRepositoryPaths)
            ? null
            : $this->findOldestPendingForMachineWithRepositoryFilter($machine, $claimableRepositoryPaths);

        if ($task instanceof AutoCodingTask) {
            return $task;
        }

        /** @var AutoCodingTask|null $task */
        $task = AutoCodingTask::query()
            ->where('status', AutoCodingExecutionStatus::Pending->value)
            ->where(function (Builder $query) use ($machine): void {
                $query->whereNull('assigned_machine_id')
                    ->orWhere('assigned_machine_id', $machine->id);
            })
            ->orderByRaw('case when assigned_machine_id = ? then 0 else 1 end', [$machine->id])
            ->orderBy('id')
            ->get()
            ->first(fn (AutoCodingTask $pendingTask): bool => $this->taskMatchesClaimRepository(
                $pendingTask,
                $claimableRepositoryPaths,
            ));

        return $task;
    }

    /**
     * Return oldest pending tasks assigned away from one machine but matching its repositories.
     *
     * @param  AutoCodingMachine  $machine
     * @param  string|null  $repositoryPath
     * @param  int  $limit
     * @return list<AutoCodingTask>
     */
    public function getOldestPendingAssignedOutsideMachine(
        AutoCodingMachine $machine,
        ?string $repositoryPath = null,
        int $limit = 10,
    ): array {
        $claimableRepositoryPaths = $this->resolveClaimableRepositoryPaths($machine, $repositoryPath);

        if ($claimableRepositoryPaths === []) {
            return [];
        }

        $tasks = $this->requiresFullRepositoryMatcher($claimableRepositoryPaths)
            ? []
            : $this->getOldestPendingAssignedOutsideMachineWithRepositoryFilter(
                $machine,
                $claimableRepositoryPaths,
                $limit,
            );

        if (count($tasks) >= max(1, min($limit, 50))) {
            return $tasks;
        }

        /** @var list<AutoCodingTask> $fallbackTasks */
        $fallbackTasks = AutoCodingTask::query()
            ->with('assignedMachine')
            ->where('status', AutoCodingExecutionStatus::Pending->value)
            ->whereNotNull('assigned_machine_id')
            ->where('assigned_machine_id', '!=', $machine->id)
            ->orderBy('id')
            ->get()
            ->filter(fn (AutoCodingTask $pendingTask): bool => $this->taskMatchesClaimRepository(
                $pendingTask,
                $claimableRepositoryPaths,
            ))
            ->take(max(1, min($limit, 50)))
            ->values()
            ->all();

        return $fallbackTasks;
    }

    /**
     * Find the latest detailed local auto-coding task.
     *
     * @return AutoCodingTask|null
     */
    public function findLatestDetailed(): ?AutoCodingTask
    {
        /** @var AutoCodingTask|null $task */
        $task = $this->baseDetailedQuery()->first();

        return $task;
    }

    /**
     * Find the latest detailed local auto-coding task for one branch name.
     *
     * @param  string  $branchName
     * @return AutoCodingTask|null
     */
    public function findLatestDetailedByBranchName(string $branchName): ?AutoCodingTask
    {
        /** @var AutoCodingTask|null $task */
        $task = $this->baseDetailedQuery()
            ->where('branch_name', trim($branchName))
            ->first();

        return $task;
    }

    /**
     * Build the shared detailed query with nested run and artifact relations.
     *
     * @return Builder<AutoCodingTask>
     */
    protected function baseDetailedQuery(): Builder
    {
        return AutoCodingTask::query()
            ->with(['runs.artifacts', 'runs.machine', 'runs.steps', 'assignedMachine'])
            ->orderByDesc('id');
    }

    /**
     * Resolve all repository paths currently bound to one machine.
     *
     * @param  AutoCodingMachine  $machine
     * @return array<int, string>
     */
    protected function resolveMachineRepositoryPaths(AutoCodingMachine $machine): array
    {
        $repositoryPaths = [];

        if (is_string($machine->repository_path) && trim($machine->repository_path) !== '') {
            $repositoryPaths[] = trim($machine->repository_path);
        }

        $workspaceBindings = $machine->workspace_bindings ?? [];

        foreach ($workspaceBindings as $binding) {
            if (! is_string($binding['repository_path'] ?? null)) {
                continue;
            }

            $repositoryPath = trim((string) $binding['repository_path']);

            if ($repositoryPath !== '') {
                $repositoryPaths[] = $repositoryPath;
            }
        }

        return RepositoryPathMatcher::variantsForExactMatch($repositoryPaths);
    }

    /**
     * Resolve repository paths one machine can claim after applying an optional repository constraint.
     *
     * @param  AutoCodingMachine  $machine
     * @param  string|null  $repositoryPath
     * @return list<string>
     */
    protected function resolveClaimableRepositoryPaths(AutoCodingMachine $machine, ?string $repositoryPath): array
    {
        $repositoryPaths = $this->resolveMachineRepositoryPaths($machine);
        $requestedRepositoryPath = is_string($repositoryPath) ? trim($repositoryPath) : '';

        if ($requestedRepositoryPath === '') {
            /** @var list<string> $claimableRepositoryPaths */
            $claimableRepositoryPaths = array_values($repositoryPaths);

            return $claimableRepositoryPaths;
        }

        foreach ($repositoryPaths as $boundRepositoryPath) {
            if (RepositoryPathMatcher::matches($boundRepositoryPath, $requestedRepositoryPath)) {
                return RepositoryPathMatcher::variantsForExactMatch([$requestedRepositoryPath]);
            }
        }

        return [];
    }

    /**
     * Determine whether one pending task matches the machine and optional repository constraint.
     *
     * @param  AutoCodingTask  $task
     * @param  list<string>  $claimableRepositoryPaths
     * @return bool
     */
    protected function taskMatchesClaimRepository(
        AutoCodingTask $task,
        array $claimableRepositoryPaths,
    ): bool {
        foreach ($claimableRepositoryPaths as $boundRepositoryPath) {
            if (RepositoryPathMatcher::matches($boundRepositoryPath, $task->repository_path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether exact SQL variants are not enough for safe oldest-task ordering.
     *
     * @param  list<string>  $repositoryPaths
     * @return bool
     */
    protected function requiresFullRepositoryMatcher(array $repositoryPaths): bool
    {
        foreach ($repositoryPaths as $repositoryPath) {
            if (RepositoryPathMatcher::isWindowsStyle($repositoryPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Try finding the oldest machine-claimable pending task using an exact repository SQL prefilter.
     *
     * @param  AutoCodingMachine  $machine
     * @param  list<string>  $claimableRepositoryPaths
     * @return AutoCodingTask|null
     */
    protected function findOldestPendingForMachineWithRepositoryFilter(
        AutoCodingMachine $machine,
        array $claimableRepositoryPaths,
    ): ?AutoCodingTask {
        /** @var AutoCodingTask|null $task */
        $task = AutoCodingTask::query()
            ->whereIn('repository_path', $claimableRepositoryPaths)
            ->where('status', AutoCodingExecutionStatus::Pending->value)
            ->where(function (Builder $query) use ($machine): void {
                $query->whereNull('assigned_machine_id')
                    ->orWhere('assigned_machine_id', $machine->id);
            })
            ->orderByRaw('case when assigned_machine_id = ? then 0 else 1 end', [$machine->id])
            ->orderBy('id')
            ->first();

        return $task;
    }

    /**
     * Try returning pending stale-assignment candidates using an exact repository SQL prefilter.
     *
     * @param  AutoCodingMachine  $machine
     * @param  list<string>  $claimableRepositoryPaths
     * @param  int  $limit
     * @return list<AutoCodingTask>
     */
    protected function getOldestPendingAssignedOutsideMachineWithRepositoryFilter(
        AutoCodingMachine $machine,
        array $claimableRepositoryPaths,
        int $limit,
    ): array {
        /** @var list<AutoCodingTask> $tasks */
        $tasks = AutoCodingTask::query()
            ->with('assignedMachine')
            ->whereIn('repository_path', $claimableRepositoryPaths)
            ->where('status', AutoCodingExecutionStatus::Pending->value)
            ->whereNotNull('assigned_machine_id')
            ->where('assigned_machine_id', '!=', $machine->id)
            ->orderBy('id')
            ->limit(max(1, min($limit, 50)))
            ->get()
            ->all();

        return $tasks;
    }
}
