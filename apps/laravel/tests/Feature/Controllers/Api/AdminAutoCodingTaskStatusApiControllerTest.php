<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AutoCodingTask;
use App\Models\User;
use App\Services\AutoCoding\AutoCodingProviderResolver;
use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use App\Services\AutoCoding\RepositoryContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutoCodingTaskStatusApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated admin can poll one compact local auto-coding task status payload.
     *
     * @return void
     */
    public function test_admin_can_poll_one_local_auto_coding_task_status(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/status');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Inspect local auto coding foundation',
                'issue_key' => 'OPAS-0070',
                'status' => 'completed',
            ])
            ->assertJsonPath('data.progress.validation_attempts_used', 1)
            ->assertJsonPath('data.progress.validation_retry_remaining', 1)
            ->assertJsonPath('data.progress.failure_category', 'none')
            ->assertJsonPath('data.progress.recommended_action', 'task_complete')
            ->assertJsonPath('data.progress.follow_up_type', null)
            ->assertJsonPath('data.progress.last_failed_step', null)
            ->assertJsonPath('data.progress.last_blocked_step', null)
            ->assertJsonPath('data.progress.last_retryable_step', null)
            ->assertJsonPath('data.recommended_action.action', 'task_complete')
            ->assertJsonPath('data.workflow.current_decision_point.type', 'completed')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'summary',
                    'issue_key',
                    'status',
                    'latest_run' => [
                        'id',
                        'machine_id',
                        'status',
                        'artifact_count',
                    ],
                    'machine' => [
                        'id',
                        'machine_key',
                        'hostname',
                        'operating_system',
                    ],
                    'progress' => [
                        'artifact_count',
                        'validation_status',
                        'provider_status',
                        'preflight_status',
                        'validation_attempts_used',
                        'validation_retry_remaining',
                        'failure_category',
                        'recommended_action',
                        'current_step',
                        'last_failed_step',
                        'last_blocked_step',
                        'last_retryable_step',
                        'follow_up_required',
                        'follow_up_type',
                    ],
                    'preflight' => [
                        'overall_status',
                        'blocking_reason',
                        'warnings',
                        'checks',
                    ],
                    'retry' => [
                        'overall_retryable',
                        'validation' => [
                            'attempts_used',
                            'max_attempts',
                            'remaining_attempts',
                            'exhausted',
                        ],
                        'retryable_steps',
                    ],
                    'failure' => [
                        'category',
                        'source',
                        'retryable',
                        'message',
                    ],
                    'recommended_action' => [
                        'action',
                        'reason',
                        'message',
                    ],
                    'workflow' => [
                        'current_step',
                        'last_failed_step',
                        'last_blocked_step',
                        'last_retryable_step',
                        'current_decision_point',
                        'steps',
                    ],
                    'follow_up' => [
                        'required',
                        'reason',
                        'message',
                        'questions',
                        'question_contracts',
                        'answered',
                        'answer_count',
                        'last_answered_at',
                        'last_answer',
                        'input_contract',
                    ],
                ],
            ]);
    }

    /**
     * Confirm non-admin users cannot poll the local auto-coding task status API.
     *
     * @return void
     */
    public function test_non_admin_cannot_poll_local_auto_coding_task_status(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->getJson('/api/admin/auto-coding/tasks/1/status');

        $response->assertForbidden();
    }

    /**
     * Confirm the status payload exposes follow-up detail when one task is blocked.
     *
     * @return void
     */
    public function test_status_payload_includes_follow_up_questions_for_blocked_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->app->instance(RepositoryContextService::class, new class extends RepositoryContextService
        {
            public function __construct() {}

            public function inspect(?string $repositoryPath = null): array
            {
                return [
                    'repository_path' => base_path('..'),
                    'branch_name' => 'main',
                    'is_dirty' => false,
                    'changed_files' => [],
                    'raw_status' => [],
                ];
            }
        });

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'blocked-provider';
                    }

                    public function plan(array $context): array
                    {
                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'blocked-provider',
                            'follow_up' => [
                                'required' => true,
                                'message' => 'Need scope confirmation.',
                                'questions' => [
                                    [
                                        'id' => 'first_file',
                                        'prompt' => 'Which file should be edited first?',
                                        'input_type' => 'text',
                                        'required' => true,
                                        'placeholder' => 'apps/laravel/app/Services/AutoCoding/...',
                                        'help_text' => 'Reply with the file path that should be changed first.',
                                    ],
                                ],
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect blocked status payload',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/status');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.progress.follow_up_required', true)
            ->assertJsonPath('data.progress.preflight_status', 'passed')
            ->assertJsonPath('data.progress.failure_category', 'provider_follow_up')
            ->assertJsonPath('data.progress.recommended_action', 'provide_follow_up')
            ->assertJsonPath('data.progress.follow_up_type', 'free_text')
            ->assertJsonPath('data.progress.last_blocked_step', 'completion_check')
            ->assertJsonPath('data.failure.category', 'provider_follow_up')
            ->assertJsonPath('data.failure.source', 'provider')
            ->assertJsonPath('data.failure.retryable', true)
            ->assertJsonPath('data.recommended_action.action', 'provide_follow_up')
            ->assertJsonPath('data.recommended_action.reason', 'provider_follow_up')
            ->assertJsonPath('data.workflow.current_decision_point.type', 'blocked')
            ->assertJsonPath('data.follow_up.required', true)
            ->assertJsonPath('data.follow_up.reason', null)
            ->assertJsonPath('data.follow_up.message', 'Need scope confirmation.')
            ->assertJsonPath('data.follow_up.questions.0', 'Which file should be edited first?')
            ->assertJsonPath('data.follow_up.question_contracts.0.id', 'first_file')
            ->assertJsonPath('data.follow_up.question_contracts.0.input_type', 'text')
            ->assertJsonPath('data.follow_up.question_contracts.0.placeholder', 'apps/laravel/app/Services/AutoCoding/...')
            ->assertJsonPath('data.follow_up.input_contract.response_transport.2', 'question_answer_list')
            ->assertJsonPath('data.follow_up.answered', false)
            ->assertJsonPath('data.follow_up.answer_count', 0)
            ->assertJsonPath('data.follow_up.input_contract.type', 'free_text')
            ->assertJsonPath('data.follow_up.input_contract.schema_version', 1)
            ->assertJsonPath('data.follow_up.input_contract.expected_input', 'provide_clarification')
            ->assertJsonPath('data.follow_up.input_contract.validation_mode', 'any_non_empty_text')
            ->assertJsonPath('data.follow_up.input_contract.resume_strategy', 'restart_from_new_run')
            ->assertJsonPath('data.follow_up.input_contract.resume_target.task_id', $task->getKey())
            ->assertJsonPath('data.follow_up.input_contract.resume_target.run_id', $task->runs()->latest('id')->value('id'))
            ->assertJsonPath('data.follow_up.input_contract.safe_to_retry', true)
            ->assertJsonPath('data.follow_up.input_contract.idempotent_while_blocked', true);
    }

    /**
     * Confirm the status payload exposes blocked preflight detail for a dirty workspace gate.
     *
     * @return void
     */
    public function test_status_payload_includes_preflight_detail_for_dirty_workspace_blocks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

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
            'summary' => 'Inspect dirty workspace status payload',
            '--dirty-policy' => 'block',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/status');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.progress.preflight_status', 'blocked')
            ->assertJsonPath('data.progress.failure_category', 'preflight_block')
            ->assertJsonPath('data.progress.recommended_action', 'resume_with_confirmation')
            ->assertJsonPath('data.progress.follow_up_type', 'confirmation')
            ->assertJsonPath('data.progress.last_blocked_step', 'completion_check')
            ->assertJsonPath('data.preflight.overall_status', 'blocked')
            ->assertJsonPath('data.preflight.blocking_reason', 'dirty_workspace')
            ->assertJsonPath('data.failure.category', 'preflight_block')
            ->assertJsonPath('data.failure.source', 'dirty_workspace')
            ->assertJsonPath('data.failure.retryable', true)
            ->assertJsonPath('data.recommended_action.action', 'resume_with_confirmation')
            ->assertJsonPath('data.recommended_action.reason', 'dirty_workspace')
            ->assertJsonPath('data.workflow.current_decision_point.type', 'blocked')
            ->assertJsonPath('data.preflight.checks.0.key', 'dirty_workspace')
            ->assertJsonPath('data.preflight.checks.0.status', 'blocked')
            ->assertJsonPath('data.follow_up.question_contracts.0.id', 'workspace_confirmation')
            ->assertJsonPath('data.follow_up.question_contracts.0.input_type', 'confirmation')
            ->assertJsonPath('data.follow_up.question_contracts.0.options.0.value', 'allow')
            ->assertJsonPath('data.follow_up.input_contract.type', 'confirmation')
            ->assertJsonPath('data.follow_up.input_contract.schema_version', 1)
            ->assertJsonPath('data.follow_up.input_contract.expected_input', 'confirm_to_continue')
            ->assertJsonPath('data.follow_up.input_contract.accepted_values.0', 'allow')
            ->assertJsonPath('data.follow_up.input_contract.free_text_allowed', false)
            ->assertJsonPath('data.follow_up.input_contract.validation_mode', 'accepted_values_only')
            ->assertJsonPath('data.follow_up.input_contract.resume_target.task_id', $task->getKey())
            ->assertJsonPath('data.follow_up.input_contract.safe_to_retry', true)
            ->assertJsonPath('data.follow_up.input_contract.response_transport.2', 'question_answer_list');
    }

    /**
     * Confirm status payload exposes the latest structured follow-up answer after resume.
     *
     * @return void
     */
    public function test_status_payload_exposes_last_structured_follow_up_answer(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->app->instance(RepositoryContextService::class, new class extends RepositoryContextService
        {
            public function __construct() {}

            public function inspect(?string $repositoryPath = null): array
            {
                return [
                    'repository_path' => base_path('..'),
                    'branch_name' => 'main',
                    'is_dirty' => false,
                    'changed_files' => [],
                    'raw_status' => [],
                ];
            }
        });

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'blocked-provider';
                    }

                    public function plan(array $context): array
                    {
                        $answers = $context['follow_up_answers'] ?? [];

                        if (is_array($answers) && $answers !== []) {
                            return [
                                'status' => 'completed',
                                'provider' => 'blocked-provider',
                                'message' => 'Follow-up resolved.',
                            ];
                        }

                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'blocked-provider',
                            'follow_up' => [
                                'required' => true,
                                'message' => 'Need scope confirmation.',
                                'questions' => [
                                    'Which file should be edited first?',
                                ],
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect structured follow up status payload',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'resume_token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
            'response_payload' => [
                'type' => 'free_text',
                'value' => 'Focus on the auto-coding module.',
                'metadata' => [
                    'source' => 'admin-ui',
                ],
            ],
        ])->assertOk();

        $task->refresh();

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/status');

        $response->assertOk()
            ->assertJsonPath('data.follow_up.answer_count', 1)
            ->assertJsonPath('data.follow_up.last_answer.response', 'Focus on the auto-coding module.')
            ->assertJsonPath('data.follow_up.last_answer.response_type', 'free_text')
            ->assertJsonPath('data.follow_up.last_answer.response_payload.metadata.source', 'admin-ui')
            ->assertJsonPath('data.follow_up.input_contract', null);
    }

    /**
     * Confirm the status payload exposes retry usage and budget after validation retries are exhausted.
     *
     * @return void
     */
    public function test_status_payload_includes_retry_budget_detail_for_validation_failures(): void
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

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect retry status payload',
            '--validate' => true,
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/status');

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.progress.validation_attempts_used', 2)
            ->assertJsonPath('data.progress.validation_retry_remaining', 0)
            ->assertJsonPath('data.progress.last_failed_step', 'completion_check')
            ->assertJsonPath('data.progress.last_retryable_step', 'run_validation')
            ->assertJsonPath('data.retry.validation.attempts_used', 2)
            ->assertJsonPath('data.retry.validation.max_attempts', 2)
            ->assertJsonPath('data.retry.validation.remaining_attempts', 0)
            ->assertJsonPath('data.retry.validation.exhausted', true)
            ->assertJsonPath('data.retry.retryable_steps.0.step', 'run_validation')
            ->assertJsonPath('data.retry.retryable_steps.0.attempts_used', 2)
            ->assertJsonPath('data.workflow.current_decision_point.type', 'failure');
    }
}
