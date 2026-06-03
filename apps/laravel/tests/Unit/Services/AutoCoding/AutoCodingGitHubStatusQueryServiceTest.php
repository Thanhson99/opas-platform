<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingGitHubStatusQueryService;
use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use App\Services\AutoCoding\GitHubContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingGitHubStatusQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the GitHub status query prefers persisted report context when available.
     *
     * @return void
     */
    public function test_it_prefers_persisted_report_github_context(): void
    {
        $task = AutoCodingTask::query()->create([
            'summary' => 'GitHub report task',
            'issue_key' => 'OPAS-0099',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0099',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0099',
                    ],
                    'repository_slug' => 'Thanhson99/laravel-n8n-automation',
                    'compare_url' => 'https://github.com/Thanhson99/laravel-n8n-automation/compare/main...feature/opas-0099',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/99',
                    ],
                    'ci' => [
                        'status' => 'passed',
                        'summary' => 'All required checks passed.',
                    ],
                ],
            ],
        ]);

        $service = new AutoCodingGitHubStatusQueryService(
            new GitHubContextService(new GitHubStatusQueryFakeCommandRunner([]))
        );

        $context = $service->resolveForTask($task);

        self::assertSame('OPAS-0099', $context['issue']['key'] ?? null);
        self::assertSame('Thanhson99/laravel-n8n-automation', $context['repository_slug'] ?? null);
        self::assertNull($context['headline'] ?? null);
        self::assertNull($context['next_action'] ?? null);
    }

    /**
     * Confirm the GitHub status query can fall back to live repository inspection.
     *
     * @return void
     */
    public function test_it_can_fall_back_to_live_repository_inspection(): void
    {
        config()->set('opas.auto_coding.github.base_branch', 'main');

        $task = AutoCodingTask::query()->create([
            'summary' => 'Pending task without report',
            'issue_key' => 'OPAS-0100',
            'repository_path' => '/tmp/example-repo',
            'branch_name' => 'feature/opas-0100',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $service = new AutoCodingGitHubStatusQueryService(
            new GitHubContextService(new GitHubStatusQueryFakeCommandRunner([
                'git remote get-url origin' => [
                    'successful' => true,
                    'exit_code' => 0,
                    'output' => 'https://github.com/Thanhson99/laravel-n8n-automation.git',
                    'error_output' => '',
                ],
                'git rev-parse --abbrev-ref --symbolic-full-name @{upstream}' => [
                    'successful' => true,
                    'exit_code' => 0,
                    'output' => 'origin/feature/opas-0100',
                    'error_output' => '',
                ],
                'git rev-parse HEAD' => [
                    'successful' => true,
                    'exit_code' => 0,
                    'output' => 'abcdef1234567890',
                    'error_output' => '',
                ],
            ]))
        );

        $context = $service->resolveForTask($task);

        self::assertSame('OPAS-0100', $context['issue']['key'] ?? null);
        self::assertSame('Thanhson99/laravel-n8n-automation', $context['repository_slug'] ?? null);
        self::assertSame('feature/opas-0100', $context['branch_name'] ?? null);
        self::assertSame([], $context['blockers'] ?? null);
        self::assertNull($context['next_action'] ?? null);
    }
}

final class GitHubStatusQueryFakeCommandRunner implements CommandRunnerInterface
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
        return $this->results[$command] ?? [
            'successful' => false,
            'exit_code' => 1,
            'output' => '',
            'error_output' => '',
        ];
    }
}
