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

    /**
     * Resolve the latest detailed local auto-coding task for one status.
     *
     * @param  string  $status
     * @return AutoCodingTask|null
     */
    public function findLatestDetailedByStatus(string $status): ?AutoCodingTask
    {
        $tasks = $this->taskRepository->getLatest(1, trim($status), null);

        return $tasks[0] ?? null;
    }

    /**
     * Resolve the latest detailed local auto-coding task for one issue key.
     *
     * @param  string  $issueKey
     * @return AutoCodingTask|null
     */
    public function findLatestDetailedByIssueKey(string $issueKey): ?AutoCodingTask
    {
        $tasks = $this->taskRepository->getLatest(1, null, trim($issueKey));

        return $tasks[0] ?? null;
    }

    /**
     * Resolve the latest detailed local auto-coding task for one branch name.
     *
     * @param  string  $branchName
     * @return AutoCodingTask|null
     */
    public function findLatestDetailedByBranchName(string $branchName): ?AutoCodingTask
    {
        return $this->taskRepository->findLatestDetailedByBranchName(trim($branchName));
    }

    /**
     * Resolve the latest detailed local auto-coding task for one pull request number.
     *
     * This lookup stays local-first by scanning recent detailed tasks for a
     * persisted GitHub PR URL that ends with the requested numeric id.
     *
     * @param  int  $pullRequestNumber
     * @return AutoCodingTask|null
     */
    public function findLatestDetailedByPullRequestNumber(int $pullRequestNumber): ?AutoCodingTask
    {
        foreach ($this->taskRepository->getLatest(50, null, null) as $task) {
            $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
            $githubContext = is_array($latestReport['github'] ?? null) ? $latestReport['github'] : [];
            $pullRequest = is_array($githubContext['pull_request'] ?? null) ? $githubContext['pull_request'] : [];
            $pullRequestUrl = is_string($pullRequest['url'] ?? null) ? trim((string) $pullRequest['url']) : '';

            if ($pullRequestUrl !== '' && preg_match('#/pull/'.preg_quote((string) $pullRequestNumber, '#').'$#', $pullRequestUrl) === 1) {
                return $task;
            }
        }

        return null;
    }
}
