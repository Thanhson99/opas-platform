<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use RuntimeException;

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
        $candidatePath = $repositoryPath;
        if (! is_string($candidatePath) || $candidatePath === '') {
            $configuredPath = config('opas.auto_coding.default_repository_path');
            $candidatePath = is_string($configuredPath) && $configuredPath !== ''
                ? $configuredPath
                : base_path('..');
        }

        $result = $this->commandRunner->run('git rev-parse --show-toplevel', $candidatePath);
        if (! $result['successful'] || $result['output'] === '') {
            throw new RuntimeException('Unable to resolve the local git repository path.');
        }

        return $result['output'];
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
