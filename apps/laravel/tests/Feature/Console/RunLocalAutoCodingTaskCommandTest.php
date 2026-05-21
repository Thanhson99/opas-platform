<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingRunArtifact;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\AutoCodingProviderResolver;
use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use App\Services\AutoCoding\RepositoryContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunLocalAutoCodingTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local auto-coding command stores machine, task, and run reports.
     *
     * @return void
     */
    public function test_it_runs_the_local_auto_coding_command_and_persists_the_report(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();
        $run = AutoCodingTaskRun::query()->first();
        $machine = AutoCodingMachine::query()->first();
        $artifacts = AutoCodingRunArtifact::query()->orderBy('type')->get();

        self::assertNotNull($task);
        self::assertNotNull($run);
        self::assertNotNull($machine);
        self::assertSame('OPAS-0070', $task->issue_key);
        self::assertIsArray($task->latest_report);
        self::assertSame($task->getKey(), $run->task_id);
        self::assertSame($machine->getKey(), $run->machine_id);
        self::assertIsString($task->latest_report['github']['repository_slug'] ?? null);
        self::assertStringStartsWith('Thanhson99/', $task->latest_report['github']['repository_slug']);
        self::assertCount(5, $artifacts);
        self::assertSame(
            ['final_report', 'github_context', 'provider_result', 'repository_snapshot', 'validation_result'],
            $artifacts->pluck('type')->all()
        );
        self::assertSame(5, $task->latest_report['summary']['artifact_count']);
        self::assertSame(
            [
                'inspect_repository',
                'prepare_prompt',
                'provider_plan',
                'collect_github_context',
                'run_validation',
                'completion_check',
            ],
            $run->steps()->orderBy('sequence')->get()->map(
                static fn ($step): string => $step->step_key->value
            )->all()
        );
    }

    /**
     * Confirm the local auto-coding workflow blocks when the provider requires follow-up input.
     *
     * @return void
     */
    public function test_it_blocks_and_resumes_one_local_auto_coding_task_with_follow_up_input(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'follow-up-fake';
                    }

                    public function plan(array $context): array
                    {
                        $answers = $context['follow_up_answers'] ?? [];

                        if (is_array($answers) && $answers !== []) {
                            return [
                                'status' => 'completed',
                                'provider' => 'follow-up-fake',
                                'message' => 'Follow-up resolved.',
                            ];
                        }

                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'follow-up-fake',
                            'message' => 'Need clarification.',
                            'follow_up' => [
                                'required' => true,
                                'questions' => [
                                    'Which module should this task focus on?',
                                ],
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect blocked workflow',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();
        $blockedRun = AutoCodingTaskRun::query()->orderBy('id')->first();

        self::assertNotNull($task);
        self::assertNotNull($blockedRun);
        self::assertSame('blocked', $task->status->value);
        self::assertSame('blocked', $blockedRun->status->value);
        self::assertTrue($task->latest_report['follow_up']['required'] ?? false);
        self::assertSame('free_text', $task->latest_report['follow_up']['input_contract']['type'] ?? null);
        $resumeToken = $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null;
        self::assertIsString($resumeToken);
        self::assertSame(
            sprintf('task:%d:run:%d:blocked', $task->getKey(), $blockedRun->getKey()),
            $resumeToken
        );

        $this->artisan('opas:auto-coding:resume', [
            'taskId' => (string) $task->getKey(),
            'response' => 'Focus on the auto-coding module.',
            '--token' => $resumeToken,
        ])->assertExitCode(0);

        $task->refresh();
        $resumedRun = AutoCodingTaskRun::query()->latest('id')->first();

        self::assertNotNull($resumedRun);
        self::assertSame('completed', $task->status->value);
        self::assertSame('completed', $resumedRun->status->value);
        self::assertCount(2, AutoCodingTaskRun::query()->get());
        self::assertTrue($task->latest_report['follow_up']['answered'] ?? false);
        self::assertSame(1, $task->latest_report['follow_up']['answer_count'] ?? null);
    }

    /**
     * Confirm the workflow retries retryable validation groups before failing the run.
     *
     * @return void
     */
    public function test_it_retries_retryable_validation_before_marking_the_task_failed(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.validation_commands', [
            'tests' => [
                'commands' => ['false'],
                'retryable' => true,
                'required' => true,
            ],
        ]);
        config()->set('opas.auto_coding.workflow.validation_retry_limit', 2);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect failing validation workflow',
            '--validate' => true,
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();
        $run = AutoCodingTaskRun::query()->first();

        self::assertNotNull($task);
        self::assertNotNull($run);
        self::assertSame('failed', $task->status->value);
        self::assertSame('failed', $run->status->value);
        self::assertSame(2, $run->steps()->where('step_key', 'run_validation')->count());
    }

    /**
     * Confirm the workflow blocks on a dirty workspace when the strict policy is requested,
     * then continues after an explicit resume confirmation.
     *
     * @return void
     */
    public function test_it_blocks_on_dirty_workspace_and_can_resume_with_confirmation(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->app->instance(RepositoryContextService::class, new class extends RepositoryContextService
        {
            public function __construct() {}

            public function inspect(?string $repositoryPath = null): array
            {
                return [
                    'repository_path' => base_path('..'),
                    'branch_name' => 'main',
                    'is_dirty' => true,
                    'changed_files' => [
                        [
                            'path' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                            'status' => 'M',
                        ],
                    ],
                    'raw_status' => ['M apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php'],
                ];
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect dirty workspace workflow',
            '--dirty-policy' => 'block',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();
        $blockedRun = AutoCodingTaskRun::query()->first();

        self::assertNotNull($task);
        self::assertNotNull($blockedRun);
        self::assertSame('blocked', $task->status->value);
        self::assertSame('dirty_workspace', $task->latest_report['follow_up']['reason'] ?? null);
        self::assertSame('confirmation', $task->latest_report['follow_up']['input_contract']['type'] ?? null);
        $resumeToken = $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null;
        self::assertIsString($resumeToken);

        $this->artisan('opas:auto-coding:resume', [
            'taskId' => (string) $task->getKey(),
            'response' => 'allow',
            '--token' => $resumeToken,
        ])->assertExitCode(0);

        $task->refresh();
        $resumedRun = AutoCodingTaskRun::query()->latest('id')->first();

        self::assertNotNull($resumedRun);
        self::assertSame('completed', $task->status->value);
        self::assertSame('allow', $task->context_payload['dirty_workspace_policy'] ?? null);
    }

    /**
     * Confirm the workflow blocks when changed files fall outside the requested scope,
     * then can continue after an explicit confirmation.
     *
     * @return void
     */
    public function test_it_blocks_on_scope_mismatch_and_can_resume_with_confirmation(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->app->instance(RepositoryContextService::class, new class extends RepositoryContextService
        {
            public function __construct() {}

            public function inspect(?string $repositoryPath = null): array
            {
                return [
                    'repository_path' => base_path('..'),
                    'branch_name' => 'main',
                    'is_dirty' => true,
                    'changed_files' => [
                        [
                            'path' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                            'status' => 'M',
                        ],
                        [
                            'path' => 'docs/roadmap/out-of-scope.md',
                            'status' => '??',
                        ],
                    ],
                    'raw_status' => [
                        'M apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                        '?? docs/roadmap/out-of-scope.md',
                    ],
                ];
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect scope mismatch workflow',
            '--scope' => 'apps/laravel/app/Services',
            '--scope-policy' => 'block',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('blocked', $task->status->value);
        self::assertSame('scope_mismatch', $task->latest_report['follow_up']['reason'] ?? null);
        self::assertSame('confirmation', $task->latest_report['follow_up']['input_contract']['type'] ?? null);
        $resumeToken = $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null;
        self::assertIsString($resumeToken);
        self::assertSame(
            ['docs/roadmap/out-of-scope.md'],
            $task->latest_report['scope']['out_of_scope_files'] ?? null
        );

        $this->artisan('opas:auto-coding:resume', [
            'taskId' => (string) $task->getKey(),
            'response' => 'allow',
            '--token' => $resumeToken,
        ])->assertExitCode(0);

        $task->refresh();

        self::assertSame('completed', $task->status->value);
        self::assertSame('allow', $task->context_payload['scope_policy'] ?? null);
    }

    /**
     * Confirm the resume command rejects stale or missing blocked-state tokens.
     *
     * @return void
     */
    public function test_it_rejects_resume_attempts_with_invalid_resume_tokens(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'follow-up-fake';
                    }

                    public function plan(array $context): array
                    {
                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'follow-up-fake',
                            'message' => 'Need clarification.',
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Reject invalid resume token',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $this->artisan('opas:auto-coding:resume', [
            'taskId' => (string) $task->getKey(),
            'response' => 'Focus on the auto-coding module.',
        ])
            ->expectsOutput('A numeric task id, non-empty response, and non-empty resume token are required.')
            ->assertExitCode(1);
    }

    /**
     * Confirm confirmation-gated CLI resumes reject arbitrary free-text responses.
     *
     * @return void
     */
    public function test_it_rejects_invalid_confirmation_responses_when_resuming_from_cli(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->app->instance(RepositoryContextService::class, new class extends RepositoryContextService
        {
            public function __construct() {}

            public function inspect(?string $repositoryPath = null): array
            {
                return [
                    'repository_path' => base_path('..'),
                    'branch_name' => 'main',
                    'is_dirty' => true,
                    'changed_files' => [
                        [
                            'path' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                            'status' => 'M',
                        ],
                    ],
                    'raw_status' => ['M apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php'],
                ];
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Reject invalid confirmation response from cli',
            '--dirty-policy' => 'block',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $this->artisan('opas:auto-coding:resume', [
            'taskId' => (string) $task->getKey(),
            'response' => 'maybe later',
            '--token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
        ])
            ->expectsOutput('This blocked task expects an explicit confirmation response. Allowed values: allow, continue, proceed, yes.')
            ->assertExitCode(1);
    }
}
