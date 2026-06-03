<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingExecutionContextService;
use Tests\TestCase;

class AutoCodingExecutionContextServiceTest extends TestCase
{
    /**
     * Confirm task context payloads are normalized into a stable execution context.
     *
     * @return void
     */
    public function test_it_builds_one_normalized_execution_context(): void
    {
        $service = $this->app->make(AutoCodingExecutionContextService::class);
        $task = new AutoCodingTask([
            'repository_path' => base_path('..'),
            'context_payload' => [
                'repository_path' => ' '.base_path('..').' ',
                'should_run_validation' => true,
                'provider_name' => ' null ',
                'provider_options' => [
                    'model' => 'qwen2.5:7b',
                    '' => 'ignored',
                ],
                'follow_up_answers' => [[
                    'response' => 'Focus on workflow',
                    'response_type' => 'free_text',
                    'submitted_at' => '2026-05-21T10:00:00+07:00',
                ]],
                'dirty_workspace_policy' => ' block ',
                'scope_paths' => [' apps/laravel/app/Services ', '', 'docs/roadmap'],
                'scope_policy' => ' allow ',
            ],
        ]);

        $context = $service->buildExecutionContext($task);

        self::assertSame(' '.base_path('..').' ', $context['repository_path']);
        self::assertTrue($context['should_run_validation']);
        self::assertSame(' null ', $context['provider_name']);
        self::assertSame(['model' => 'qwen2.5:7b'], $context['provider_options']);
        self::assertSame('block', $context['dirty_workspace_policy']);
        self::assertSame(['apps/laravel/app/Services', 'docs/roadmap'], $context['scope_paths']);
        self::assertSame('allow', $context['scope_policy']);
        self::assertSame(1, $context['follow_up_answer_summary']['answer_count'] ?? null);
    }

    /**
     * Confirm provider context payloads expose summary, repository, and follow-up data.
     *
     * @return void
     */
    public function test_it_builds_one_provider_context(): void
    {
        $service = $this->app->make(AutoCodingExecutionContextService::class);
        $task = new AutoCodingTask([
            'summary' => 'Inspect workflow context',
            'issue_key' => 'OPAS-0070',
            'context_payload' => [
                'issue_context' => [
                    'branch_name' => 'feature/opas-0070',
                    'pull_request' => [
                        'url' => 'https://github.com/example/repo/pull/70',
                    ],
                ],
            ],
        ]);

        $context = $service->buildProviderContext(
            $task,
            ['repository_path' => base_path('..')],
            ['model' => 'qwen2.5:7b'],
            [['response' => 'Focus on workflow']],
            ['answer_count' => 1]
        );

        self::assertSame('Inspect workflow context', $context['task_summary'] ?? null);
        self::assertSame('OPAS-0070', $context['issue_key'] ?? null);
        self::assertSame('feature/opas-0070', $context['issue_context']['branch_name'] ?? null);
        self::assertSame(base_path('..'), $context['repository_context']['repository_path'] ?? null);
        self::assertSame('qwen2.5:7b', $context['provider_options']['model'] ?? null);
        self::assertSame(1, $context['follow_up_answer_summary']['answer_count'] ?? null);
    }
}
