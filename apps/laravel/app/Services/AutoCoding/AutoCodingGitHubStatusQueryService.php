<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;

class AutoCodingGitHubStatusQueryService
{
    public function __construct(
        private readonly GitHubContextService $gitHubContextService,
    ) {}

    /**
     * Resolve the best available GitHub context for one task.
     *
     * Prefer persisted final-report context when available, then fall back to
     * a fresh local repository inspection so Telegram can still show GitHub
     * metadata for pending tasks.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    public function resolveForTask(AutoCodingTask $task): array
    {
        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        /** @var array<string, mixed>|null $reportGithub */
        $reportGithub = is_array($latestReport['github'] ?? null) ? $latestReport['github'] : null;

        if ($reportGithub !== null) {
            return $this->normalizeGitHubContext($reportGithub, $task);
        }

        $latestRun = $this->resolveLatestRun($task);
        $runReport = $latestRun instanceof AutoCodingTaskRun && is_array($latestRun->final_report)
            ? $latestRun->final_report
            : [];
        /** @var array<string, mixed>|null $runGithub */
        $runGithub = is_array($runReport['github'] ?? null) ? $runReport['github'] : null;

        if ($runGithub !== null) {
            return $this->normalizeGitHubContext($runGithub, $task);
        }

        $repositoryPath = trim((string) $task->repository_path);
        $branchName = $this->resolveBranchName($task, $latestRun);

        return $this->normalizeGitHubContext($this->gitHubContextService->inspect(
            $repositoryPath,
            $branchName,
            $task->issue_key
        ), $task);
    }

    /**
     * Normalize one GitHub context payload so Telegram reporting can stay stable.
     *
     * @param  array<string, mixed>  $githubContext
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    protected function normalizeGitHubContext(array $githubContext, AutoCodingTask $task): array
    {
        $issue = is_array($githubContext['issue'] ?? null) ? $githubContext['issue'] : [];
        $pullRequest = is_array($githubContext['pull_request'] ?? null) ? $githubContext['pull_request'] : [];
        $ci = is_array($githubContext['ci'] ?? null) ? $githubContext['ci'] : [];
        $branchName = $this->normalizeOptionalString($githubContext['branch_name'] ?? null)
            ?? $this->normalizeOptionalString($task->branch_name);
        $compareUrl = $this->normalizeOptionalString($githubContext['compare_url'] ?? null);

        $normalized = [
            'issue' => [
                'key' => $this->normalizeOptionalString($issue['key'] ?? null)
                    ?? $this->normalizeOptionalString($task->issue_key),
            ],
            'remote_url' => $this->normalizeOptionalString($githubContext['remote_url'] ?? null),
            'repository_slug' => $this->normalizeOptionalString($githubContext['repository_slug'] ?? null),
            'branch_name' => $branchName,
            'upstream_branch' => $this->normalizeOptionalString($githubContext['upstream_branch'] ?? null),
            'head_sha' => $this->normalizeOptionalString($githubContext['head_sha'] ?? null),
            'compare_url' => $compareUrl,
            'pull_request' => [
                'status' => $this->normalizeStatus($pullRequest['status'] ?? null, 'unavailable'),
                'reason' => $this->normalizeOptionalString($pullRequest['reason'] ?? null),
                'url' => $this->normalizeOptionalString($pullRequest['url'] ?? null),
                'title' => $this->normalizeOptionalString($pullRequest['title'] ?? null),
            ],
            'ci' => [
                'status' => $this->normalizeStatus($ci['status'] ?? null, 'unavailable'),
                'reason' => $this->normalizeOptionalString($ci['reason'] ?? null),
                'summary' => $this->normalizeOptionalString($ci['summary'] ?? null),
                'failed_checks' => $this->normalizeOptionalInteger($ci['failed_checks'] ?? null),
                'total_checks' => $this->normalizeOptionalInteger($ci['total_checks'] ?? null),
            ],
        ];

        $normalized['blockers'] = $this->normalizeBlockers($githubContext['blockers'] ?? null) ?? [];
        $normalized['next_action'] = $this->normalizeOptionalString($githubContext['next_action'] ?? null);
        $normalized['headline'] = $this->normalizeOptionalString($githubContext['headline'] ?? null);

        return $normalized;
    }

    /**
     * Resolve the most recent run for one task from loaded relations when possible.
     *
     * @param  AutoCodingTask  $task
     * @return AutoCodingTaskRun|null
     */
    protected function resolveLatestRun(AutoCodingTask $task): ?AutoCodingTaskRun
    {
        $runs = $task->relationLoaded('runs')
            ? $task->runs
            : $task->runs()->with(['artifacts', 'machine', 'steps'])->get();

        /** @var AutoCodingTaskRun|null $latestRun */
        $latestRun = $runs->sortByDesc('id')->first();

        return $latestRun;
    }

    /**
     * Resolve the best available branch name for one task.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun|null  $latestRun
     * @return string|null
     */
    protected function resolveBranchName(AutoCodingTask $task, ?AutoCodingTaskRun $latestRun): ?string
    {
        if (is_string($task->branch_name) && trim($task->branch_name) !== '') {
            return trim($task->branch_name);
        }

        $repositorySnapshot = $latestRun instanceof AutoCodingTaskRun
            ? $latestRun->repository_snapshot
            : [];
        $branchName = $repositorySnapshot['branch_name'] ?? null;

        return is_string($branchName) && trim($branchName) !== ''
            ? trim($branchName)
            : null;
    }

    /**
     * Normalize one mixed value into a trimmed string when possible.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Normalize one mixed status value into a stable lowercase token.
     *
     * @param  mixed  $value
     * @param  string  $fallback
     * @return string
     */
    protected function normalizeStatus(mixed $value, string $fallback): string
    {
        $normalized = $this->normalizeOptionalString($value);

        return $normalized !== null ? strtolower($normalized) : $fallback;
    }

    /**
     * Normalize one mixed value into a positive integer when possible.
     *
     * @param  mixed  $value
     * @return int|null
     */
    protected function normalizeOptionalInteger(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Normalize one mixed blocker payload into a compact string list when possible.
     *
     * @param  mixed  $value
     * @return array<int, string>|null
     */
    protected function normalizeBlockers(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $blockers = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $normalizedItem = trim($item);

            if ($normalizedItem === '') {
                continue;
            }

            $blockers[] = $normalizedItem;
        }

        return $blockers !== [] ? array_values(array_unique(array_slice($blockers, 0, 3))) : null;
    }
}
