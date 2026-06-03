<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use App\Services\AutoCoding\GitHubContextService;
use Tests\TestCase;

class GitHubContextServiceTest extends TestCase
{
    /**
     * Confirm the GitHub context service builds local repository metadata from git commands.
     *
     * @return void
     */
    public function test_it_builds_local_github_context_from_origin_remote(): void
    {
        config()->set('opas.auto_coding.github.base_branch', 'main');

        $service = new GitHubContextService(new GitHubFakeCommandRunner([
            'git remote get-url origin' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'https://github.com/Thanhson99/laravel-n8n-automation.git',
                'error_output' => '',
            ],
            'git rev-parse --abbrev-ref --symbolic-full-name @{upstream}' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'origin/feature/opas-0070-auto-coding-local-foundation',
                'error_output' => '',
            ],
            'git rev-parse HEAD' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'abcdef1234567890',
                'error_output' => '',
            ],
        ]));

        $context = $service->inspect('/tmp/example-repo', 'feature/opas-0070-auto-coding-local-foundation', 'OPAS-0070');

        self::assertSame('Thanhson99/laravel-n8n-automation', $context['repository_slug']);
        self::assertSame('origin/feature/opas-0070-auto-coding-local-foundation', $context['upstream_branch']);
        self::assertSame('abcdef1234567890', $context['head_sha']);
        self::assertSame(
            'https://github.com/Thanhson99/laravel-n8n-automation/compare/main...feature/opas-0070-auto-coding-local-foundation',
            $context['compare_url']
        );
        self::assertSame('unavailable', $context['pull_request']['status']);
        self::assertSame('OPAS-0070', $context['issue']['key']);
    }
}

final class GitHubFakeCommandRunner implements CommandRunnerInterface
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
        return $this->results[$command];
    }
}
