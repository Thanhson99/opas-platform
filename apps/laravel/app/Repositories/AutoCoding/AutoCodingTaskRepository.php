<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
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
            ->orderBy('id')
            ->first();

        return $task;
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
     * Build the shared detailed query with nested run and artifact relations.
     *
     * @return Builder<AutoCodingTask>
     */
    protected function baseDetailedQuery(): Builder
    {
        return AutoCodingTask::query()
            ->with(['runs.artifacts', 'runs.machine', 'runs.steps'])
            ->orderByDesc('id');
    }
}
