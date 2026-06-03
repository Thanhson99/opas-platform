<?php

declare(strict_types=1);

namespace App\Repositories\AutoCoding\Interfaces;

use App\Models\AutoCodingTask;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AutoCodingTaskRepositoryInterface
{
    /**
     * Return local auto-coding tasks with related runs for admin APIs.
     *
     * @param  string|null  $status
     * @param  string|null  $issueKey
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, AutoCodingTask>
     */
    public function paginateForAdmin(?string $status, ?string $issueKey, int $perPage): LengthAwarePaginator;

    /**
     * Return the latest local auto-coding tasks for console listings.
     *
     * @param  int  $limit
     * @param  string|null  $status
     * @param  string|null  $issueKey
     * @return list<AutoCodingTask>
     */
    public function getLatest(int $limit, ?string $status, ?string $issueKey): array;

    /**
     * Find one detailed local auto-coding task by id.
     *
     * @param  int  $taskId
     * @return AutoCodingTask|null
     */
    public function findDetailedById(int $taskId): ?AutoCodingTask;

    /**
     * Find the oldest pending local auto-coding task, optionally constrained to one repository path.
     *
     * @param  string|null  $repositoryPath
     * @return AutoCodingTask|null
     */
    public function findOldestPending(?string $repositoryPath = null): ?AutoCodingTask;

    /**
     * Find the latest detailed local auto-coding task.
     *
     * @return AutoCodingTask|null
     */
    public function findLatestDetailed(): ?AutoCodingTask;

    /**
     * Find the latest detailed local auto-coding task for one branch name.
     *
     * @param  string  $branchName
     * @return AutoCodingTask|null
     */
    public function findLatestDetailedByBranchName(string $branchName): ?AutoCodingTask;
}
