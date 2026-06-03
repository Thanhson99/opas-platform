<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use App\Services\AutoCoding\RepositoryContextService;
use PHPUnit\Framework\TestCase;

class RepositoryContextServiceTest extends TestCase
{
    /**
     * Confirm the repository context service normalizes branch and changed files.
     *
     * @return void
     */
    public function test_it_builds_repository_context_from_git_commands(): void
    {
        $service = new RepositoryContextService(new FakeCommandRunner([
            'git rev-parse --show-toplevel' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => '/tmp/example-repo',
                'error_output' => '',
            ],
            'git branch --show-current' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'feature/test-branch',
                'error_output' => '',
            ],
            'git status --short' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => "M apps/laravel/app/Services/Foo.php\n?? docs/roadmap/test.md",
                'error_output' => '',
            ],
        ]));

        $context = $service->inspect('/tmp/example-repo');

        self::assertSame('/tmp/example-repo', $context['repository_path']);
        self::assertSame('feature/test-branch', $context['branch_name']);
        self::assertTrue($context['is_dirty']);
        self::assertSame([
            [
                'path' => 'apps/laravel/app/Services/Foo.php',
                'status' => 'M',
            ],
            [
                'path' => 'docs/roadmap/test.md',
                'status' => '??',
            ],
        ], $context['changed_files']);
    }

    /**
     * Confirm the repository context service can fall back to a secondary git path when the requested path is invalid.
     *
     * @return void
     */
    public function test_it_falls_back_to_an_accessible_repository_candidate(): void
    {
        putenv('AUTO_CODING_CONTAINER_REPOSITORY_PATH=/workspace/repo');

        $service = new RepositoryContextService(new FakeCommandRunner([
            'git rev-parse --show-toplevel@@/tmp/missing-repo' => [
                'successful' => false,
                'exit_code' => 128,
                'output' => '',
                'error_output' => 'fatal: not a git repository',
            ],
            'git rev-parse --show-toplevel@@/workspace/repo' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => '/workspace/repo',
                'error_output' => '',
            ],
            'git branch --show-current@@/workspace/repo' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'main',
                'error_output' => '',
            ],
            'git status --short@@/workspace/repo' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => '',
                'error_output' => '',
            ],
        ]));

        $context = $service->inspect('/tmp/missing-repo');

        self::assertSame('/workspace/repo', $context['repository_path']);
        self::assertSame('main', $context['branch_name']);
        self::assertFalse($context['is_dirty']);
        self::assertSame([], $context['changed_files']);
    }
}

final class FakeCommandRunner implements CommandRunnerInterface
{
    /**
     * @param  array<string, array{successful: bool, exit_code: int, output: string, error_output: string}>  $results
     */
    public function __construct(
        private readonly array $results,
    ) {}

    /**
     * Execute one shell command and return the normalized result.
     *
     * @param  string  $command
     * @param  string|null  $workingDirectory
     * @return array{successful: bool, exit_code: int, output: string, error_output: string}
     */
    public function run(string $command, ?string $workingDirectory = null, ?int $timeoutSeconds = null): array
    {
        if (is_string($workingDirectory)) {
            $compositeKey = sprintf('%s@@%s', $command, $workingDirectory);

            if (array_key_exists($compositeKey, $this->results)) {
                return $this->results[$compositeKey];
            }
        }

        return $this->results[$command] ?? [
            'successful' => false,
            'exit_code' => 128,
            'output' => '',
            'error_output' => 'fatal: not a git repository',
        ];
    }
}
