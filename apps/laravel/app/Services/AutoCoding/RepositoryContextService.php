<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use RuntimeException;
use Throwable;

class RepositoryContextService
{
    public function __construct(
        private readonly CommandRunnerInterface $commandRunner,
    ) {}

    /**
     * Inspect the local repository state for the current or requested path.
     *
     * @param  string|null  $repositoryPath
     * @return array{
     *   repository_path: string,
     *   branch_name: string|null,
     *   is_dirty: bool,
     *   changed_files: array<int, array{path: string, status: string}>,
     *   raw_status: list<string>
     * }
     */
    public function inspect(?string $repositoryPath = null): array
    {
        $resolvedRepositoryPath = $this->resolveRepositoryPath($repositoryPath);
        $branchName = $this->resolveBranchName($resolvedRepositoryPath);
        $statusLines = $this->resolveStatusLines($resolvedRepositoryPath);

        return [
            'repository_path' => $resolvedRepositoryPath,
            'branch_name' => $branchName,
            'is_dirty' => $statusLines !== [],
            'changed_files' => $this->parseStatusLines($statusLines),
            'raw_status' => $statusLines,
        ];
    }

    /**
     * Resolve the repository root for the requested path or current working tree.
     *
     * @param  string|null  $repositoryPath
     * @return string
     */
    protected function resolveRepositoryPath(?string $repositoryPath): string
    {
        foreach ($this->buildRepositoryPathCandidates($repositoryPath) as $candidatePath) {
            $result = $this->commandRunner->run('git rev-parse --show-toplevel', $candidatePath);

            if ($result['successful'] && $result['output'] !== '') {
                return $result['output'];
            }
        }

        throw new RuntimeException('Unable to resolve the local git repository path.');
    }

    /**
     * Build the ordered repository path candidates that may contain the git working tree.
     *
     * @param  string|null  $repositoryPath
     * @return array<int, string>
     */
    protected function buildRepositoryPathCandidates(?string $repositoryPath): array
    {
        $candidates = [];

        $this->appendCandidatePath($candidates, $repositoryPath);
        $this->appendCandidatePath($candidates, $this->resolveConfiguredRepositoryPath());
        $this->appendCandidatePath($candidates, $this->resolveConfiguredContainerRepositoryPath());
        $this->appendCandidatePath($candidates, $this->resolveBasePath(''));
        $this->appendCandidatePath($candidates, $this->resolveBasePath('..'));
        $this->appendCandidatePath($candidates, $this->resolveBasePath('../..'));

        return array_values(array_unique($candidates));
    }

    /**
     * Append one normalized repository candidate path when it is non-empty.
     *
     * @param  array<int, string>  $candidates
     * @param  string|null  $candidatePath
     * @return void
     */
    protected function appendCandidatePath(array &$candidates, ?string $candidatePath): void
    {
        if (! is_string($candidatePath)) {
            return;
        }

        $normalizedCandidatePath = trim($candidatePath);

        if ($normalizedCandidatePath === '') {
            return;
        }

        $resolvedPath = realpath($normalizedCandidatePath);

        $candidates[] = is_string($resolvedPath)
            ? $resolvedPath
            : $normalizedCandidatePath;
    }

    /**
     * Resolve the configured repository-path override safely outside full Laravel runtime tests.
     *
     * @return string|null
     */
    protected function resolveConfiguredRepositoryPath(): ?string
    {
        try {
            $configuredPath = config('opas.auto_coding.default_repository_path');
        } catch (Throwable) {
            return null;
        }

        return is_string($configuredPath) && trim($configuredPath) !== ''
            ? trim($configuredPath)
            : null;
    }

    /**
     * Resolve the configured container/server repository-path override.
     *
     * @return string|null
     */
    protected function resolveConfiguredContainerRepositoryPath(): ?string
    {
        try {
            $configuredPath = config('opas.auto_coding.container_repository_path');
        } catch (Throwable) {
            $configuredPath = getenv('AUTO_CODING_CONTAINER_REPOSITORY_PATH') ?: null;
        }

        return is_string($configuredPath) && trim($configuredPath) !== ''
            ? trim($configuredPath)
            : null;
    }

    /**
     * Resolve one Laravel base path safely outside full Laravel runtime tests.
     *
     * @param  string  $suffix
     * @return string|null
     */
    protected function resolveBasePath(string $suffix): ?string
    {
        if (! function_exists('base_path')) {
            return null;
        }

        try {
            $resolvedBasePath = base_path($suffix);
        } catch (Throwable) {
            return null;
        }

        return trim($resolvedBasePath) !== ''
            ? trim($resolvedBasePath)
            : null;
    }

    /**
     * Resolve the currently checked out branch for the repository.
     *
     * @param  string  $repositoryPath
     * @return string|null
     */
    protected function resolveBranchName(string $repositoryPath): ?string
    {
        $result = $this->commandRunner->run('git branch --show-current', $repositoryPath);

        return $result['successful'] && $result['output'] !== ''
            ? $result['output']
            : null;
    }

    /**
     * Resolve the raw git status lines for the repository.
     *
     * @param  string  $repositoryPath
     * @return list<string>
     */
    protected function resolveStatusLines(string $repositoryPath): array
    {
        $result = $this->commandRunner->run('git status --short', $repositoryPath);
        if (! $result['successful'] || $result['output'] === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $result['output']) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Parse the short git status lines into a stable changed-file summary.
     *
     * @param  list<string>  $statusLines
     * @return array<int, array{path: string, status: string}>
     */
    protected function parseStatusLines(array $statusLines): array
    {
        $files = [];

        foreach ($statusLines as $line) {
            $status = trim(substr($line, 0, 2));
            $path = trim(substr($line, 2));

            if ($path === '') {
                continue;
            }

            $files[] = [
                'path' => $path,
                'status' => $status !== '' ? $status : 'unknown',
            ];
        }

        return $files;
    }
}
