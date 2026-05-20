<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AutoCodingTaskQueryService
{
    public function __construct(
        private readonly AutoCodingTaskRepositoryInterface $taskRepository,
    ) {}

    /**
     * Return paginated local auto-coding tasks for admin APIs.
     *
     * @param  string|null  $status
     * @param  string|null  $issueKey
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, AutoCodingTask>
     */
    public function paginateForAdmin(?string $status, ?string $issueKey, int $perPage): LengthAwarePaginator
    {
        return $this->taskRepository->paginateForAdmin($status, $issueKey, $perPage);
    }

    /**
     * Return the latest local auto-coding tasks for console output.
     *
     * @param  int  $limit
     * @param  string|null  $status
     * @param  string|null  $issueKey
     * @return list<AutoCodingTask>
     */
    public function getLatest(int $limit, ?string $status, ?string $issueKey): array
    {
        return $this->taskRepository->getLatest($limit, $status, $issueKey);
    }

    /**
     * Resolve one detailed local auto-coding task by id.
     *
     * @param  int  $taskId
     * @return AutoCodingTask|null
     */
    public function findDetailedById(int $taskId): ?AutoCodingTask
    {
        return $this->taskRepository->findDetailedById($taskId);
    }

    /**
     * Resolve the oldest pending local auto-coding task for one repository path.
     *
     * @param  string|null  $repositoryPath
     * @return AutoCodingTask|null
     */
    public function findOldestPending(?string $repositoryPath = null): ?AutoCodingTask
    {
        return $this->taskRepository->findOldestPending($repositoryPath);
    }

    /**
     * Resolve the latest detailed local auto-coding task.
     *
     * @return AutoCodingTask|null
     */
    public function findLatestDetailed(): ?AutoCodingTask
    {
        return $this->taskRepository->findLatestDetailed();
    }
}
