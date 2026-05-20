<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;

class GitHubContextService
{
    public function __construct(
        private readonly CommandRunnerInterface $commandRunner,
    ) {}

    /**
     * Build the local GitHub context that can be resolved without remote API calls.
     *
     * @param  string  $repositoryPath
     * @param  string|null  $branchName
     * @param  string|null  $issueKey
     * @return array<string, mixed>
     */
    public function inspect(string $repositoryPath, ?string $branchName, ?string $issueKey): array
    {
        $remoteUrl = $this->resolveGitOutput('git remote get-url origin', $repositoryPath);
        $repositorySlug = $this->parseRepositorySlug($remoteUrl);
        $upstreamBranch = $this->resolveGitOutput('git rev-parse --abbrev-ref --symbolic-full-name @{upstream}', $repositoryPath);
        $headSha = $this->resolveGitOutput('git rev-parse HEAD', $repositoryPath);
        $baseBranch = config('opas.auto_coding.github.base_branch', 'main');

        return [
            'issue' => [
                'key' => $issueKey,
            ],
            'remote_url' => $remoteUrl,
            'repository_slug' => $repositorySlug,
            'branch_name' => $branchName,
            'upstream_branch' => $upstreamBranch,
            'head_sha' => $headSha,
            'compare_url' => $this->buildCompareUrl($repositorySlug, $baseBranch, $branchName),
            'pull_request' => [
                'status' => 'unavailable',
                'reason' => 'GitHub CLI or API integration is not configured in Phase 1.',
            ],
            'ci' => [
                'status' => 'unavailable',
                'reason' => 'GitHub CLI or API integration is not configured in Phase 1.',
            ],
        ];
    }

    /**
     * Resolve one git command output from the repository.
     *
     * @param  string  $command
     * @param  string  $repositoryPath
     * @return string|null
     */
    protected function resolveGitOutput(string $command, string $repositoryPath): ?string
    {
        $result = $this->commandRunner->run($command, $repositoryPath);

        if (! $result['successful'] || $result['output'] === '') {
            return null;
        }

        return $result['output'];
    }

    /**
     * Parse the GitHub repository slug from an origin remote URL.
     *
     * @param  string|null  $remoteUrl
     * @return string|null
     */
    protected function parseRepositorySlug(?string $remoteUrl): ?string
    {
        if (! is_string($remoteUrl) || $remoteUrl === '') {
            return null;
        }

        if (preg_match('#github\.com[:/](?<slug>[^/]+/[^/]+?)(?:\.git)?$#', $remoteUrl, $matches) !== 1) {
            return null;
        }

        return $matches['slug'];
    }

    /**
     * Build a compare URL for the current branch when the repository slug is known.
     *
     * @param  string|null  $repositorySlug
     * @param  mixed  $baseBranch
     * @param  string|null  $branchName
     * @return string|null
     */
    protected function buildCompareUrl(?string $repositorySlug, mixed $baseBranch, ?string $branchName): ?string
    {
        if (! is_string($repositorySlug) || $repositorySlug === '') {
            return null;
        }

        if (! is_string($baseBranch) || $baseBranch === '') {
            return null;
        }

        if (! is_string($branchName) || $branchName === '') {
            return null;
        }

        return sprintf('https://github.com/%s/compare/%s...%s', $repositorySlug, $baseBranch, $branchName);
    }
}
