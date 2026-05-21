<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AutoCodingTask;
use App\Models\User;
use App\Services\AutoCoding\AutoCodingProviderResolver;
use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutoCodingTaskApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated admin can list local auto-coding tasks.
     *
     * @return void
     */
    public function test_admin_can_list_local_auto_coding_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Inspect local auto coding foundation',
                'issue_key' => 'OPAS-0070',
            ]);
    }

    /**
     * Confirm an authenticated admin can inspect one detailed local auto-coding task.
     *
     * @return void
     */
    public function test_admin_can_show_one_local_auto_coding_task(): void
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

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey());

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Inspect local auto coding foundation',
            ]);
    }

    /**
     * Confirm the admin detail API exposes follow-up contract metadata for blocked tasks.
     *
     * @return void
     */
    public function test_admin_can_show_follow_up_contract_for_blocked_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->bindFollowUpProviderResolver();

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect blocked auto coding task detail',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey());

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked')
            ->assertJsonPath('data.follow_up.required', true)
            ->assertJsonPath('data.follow_up.input_contract.type', 'free_text')
            ->assertJsonPath('data.follow_up.input_contract.expected_input', 'provide_clarification')
            ->assertJsonPath('data.follow_up.input_contract.schema_version', 1);
    }

    /**
     * Confirm an authenticated admin can create and enqueue a local auto-coding task over the API.
     *
     * @return void
     */
    public function test_admin_can_create_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks', [
            'summary' => 'Create local auto coding task from API',
            'issue_key' => 'OPAS-0070',
            'validate' => false,
            'provider' => 'null',
            'scope_paths' => ['apps/laravel/app/Services/AutoCoding'],
            'scope_policy' => 'block',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonFragment([
                'summary' => 'Create local auto coding task from API',
                'issue_key' => 'OPAS-0070',
                'status' => 'pending',
            ]);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('pending', $task->status->value);
        self::assertSame('queued', $task->latest_report['queue']['status'] ?? null);
        self::assertSame(['apps/laravel/app/Services/AutoCoding'], $task->context_payload['scope_paths'] ?? null);
        self::assertSame('block', $task->context_payload['scope_policy'] ?? null);
        $response->assertJsonPath('data.failure', null);
        $response->assertJsonPath('data.recommended_action', null);
    }

    /**
     * Confirm an authenticated admin can claim the next pending local auto-coding task.
     *
     * @return void
     */
    public function test_admin_can_claim_the_next_pending_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks', [
            'summary' => 'Claim local auto coding task from API',
            'issue_key' => 'OPAS-0070',
            'validate' => false,
            'provider' => 'null',
        ])->assertAccepted();

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/claim');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Claim local auto coding task from API',
                'issue_key' => 'OPAS-0070',
                'status' => 'running',
            ]);
    }

    /**
     * Confirm an authenticated admin can resume one blocked local auto-coding task over the API.
     *
     * @return void
     */
    public function test_admin_can_resume_one_blocked_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->bindFollowUpProviderResolver();

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Resume blocked auto coding task from API',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('blocked', $task->status->value);
        $resumeToken = $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null;
        self::assertIsString($resumeToken);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'response' => 'Focus on the auto-coding module.',
            'resume_token' => $resumeToken,
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Resume blocked auto coding task from API',
                'status' => 'completed',
            ]);

        $task->refresh();

        self::assertSame('completed', $task->status->value);
        self::assertTrue($task->runs()->count() >= 2);
    }

    /**
     * Confirm the resume API accepts structured follow-up answer payloads.
     *
     * @return void
     */
    public function test_admin_can_resume_one_blocked_task_with_structured_follow_up_payload(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->bindFollowUpProviderResolver();

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Resume blocked auto coding task with payload',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'resume_token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
            'response_payload' => [
                'type' => 'free_text',
                'value' => 'Focus on the auto-coding module.',
                'metadata' => [
                    'source' => 'admin-ui',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $task->refresh();

        self::assertSame('completed', $task->status->value);
        self::assertSame(
            'free_text',
            $task->context_payload['follow_up_answers'][0]['response_type'] ?? null
        );
        self::assertSame(
            'admin-ui',
            $task->context_payload['follow_up_answers'][0]['response_payload']['metadata']['source'] ?? null
        );
    }

    /**
     * Confirm the resume API can bind answers by question id for multi-question follow-up payloads.
     *
     * @return void
     */
    public function test_admin_can_resume_blocked_task_with_question_answer_list_payload(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'follow-up-multi-question-fake';
                    }

                    public function plan(array $context): array
                    {
                        $answers = $context['follow_up_answers'] ?? [];

                        if (is_array($answers) && $answers !== []) {
                            return [
                                'status' => 'completed',
                                'provider' => 'follow-up-multi-question-fake',
                                'message' => 'Follow-up resolved.',
                            ];
                        }

                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'follow-up-multi-question-fake',
                            'follow_up' => [
                                'required' => true,
                                'message' => 'Need routing details.',
                                'questions' => [
                                    [
                                        'id' => 'target_file',
                                        'prompt' => 'Which file should be edited first?',
                                        'input_type' => 'text',
                                        'required' => true,
                                    ],
                                    [
                                        'id' => 'change_scope',
                                        'prompt' => 'What change scope should be applied?',
                                        'input_type' => 'text',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Resume blocked task with answer list payload',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'resume_token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
            'response_payload' => [
                'type' => 'question_answer_list',
                'answers' => [
                    [
                        'question_id' => 'target_file',
                        'type' => 'text',
                        'value' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                    ],
                    [
                        'question_id' => 'change_scope',
                        'type' => 'text',
                        'value' => 'workflow',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $task->refresh();

        self::assertSame('completed', $task->status->value);
        self::assertSame(
            'question_answer_list',
            $task->context_payload['follow_up_answers'][0]['response_payload']['type'] ?? null
        );
        self::assertSame(
            'target_file',
            $task->context_payload['follow_up_answers'][0]['response_payload']['answers'][0]['question_id'] ?? null
        );
    }

    /**
     * Confirm provider integrations can consume the normalized follow-up answer summary map.
     *
     * @return void
     */
    public function test_provider_can_consume_follow_up_answer_summary_map(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'follow-up-summary-fake';
                    }

                    public function plan(array $context): array
                    {
                        $summary = is_array($context['follow_up_answer_summary'] ?? null)
                            ? $context['follow_up_answer_summary']
                            : [];
                        $answersByQuestionId = is_array($summary['latest_answers_by_question_id'] ?? null)
                            ? $summary['latest_answers_by_question_id']
                            : [];
                        $targetFile = $answersByQuestionId['target_file']['value'] ?? null;
                        $changeScope = $answersByQuestionId['change_scope']['value'] ?? null;

                        if (is_string($targetFile) && is_string($changeScope)) {
                            return [
                                'status' => 'completed',
                                'provider' => 'follow-up-summary-fake',
                                'message' => sprintf('Resolved %s with scope %s.', $targetFile, $changeScope),
                            ];
                        }

                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'follow-up-summary-fake',
                            'follow_up' => [
                                'required' => true,
                                'message' => 'Need routing details.',
                                'questions' => [
                                    [
                                        'id' => 'target_file',
                                        'prompt' => 'Which file should be edited first?',
                                        'input_type' => 'text',
                                        'required' => true,
                                    ],
                                    [
                                        'id' => 'change_scope',
                                        'prompt' => 'What change scope should be applied?',
                                        'input_type' => 'text',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Use follow-up answer summary map',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'resume_token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
            'response_payload' => [
                'type' => 'question_answer_list',
                'answers' => [
                    [
                        'question_id' => 'target_file',
                        'type' => 'text',
                        'value' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                    ],
                    [
                        'question_id' => 'change_scope',
                        'type' => 'text',
                        'value' => 'workflow',
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $task->refresh();

        self::assertSame('completed', $task->status->value);
        self::assertSame(
            'Resolved apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php with scope workflow.',
            $task->latest_report['provider_result']['message'] ?? null
        );
    }

    /**
     * Confirm the resume API rejects question-answer payloads that miss required questions.
     *
     * @return void
     */
    public function test_admin_cannot_resume_blocked_task_with_incomplete_question_answer_list_payload(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'follow-up-multi-question-fake';
                    }

                    public function plan(array $context): array
                    {
                        return [
                            'status' => 'needs_follow_up',
                            'provider' => 'follow-up-multi-question-fake',
                            'follow_up' => [
                                'required' => true,
                                'message' => 'Need routing details.',
                                'questions' => [
                                    [
                                        'id' => 'target_file',
                                        'prompt' => 'Which file should be edited first?',
                                        'input_type' => 'text',
                                        'required' => true,
                                    ],
                                    [
                                        'id' => 'change_scope',
                                        'prompt' => 'What change scope should be applied?',
                                        'input_type' => 'text',
                                        'required' => true,
                                    ],
                                ],
                            ],
                        ];
                    }
                };
            }
        });

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Reject incomplete answer list payload',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'resume_token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
            'response_payload' => [
                'type' => 'question_answer_list',
                'answers' => [
                    [
                        'question_id' => 'target_file',
                        'type' => 'text',
                        'value' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                    ],
                ],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['response_payload.answers']);
    }

    /**
     * Confirm the resume API rejects requests with stale or missing blocked-state tokens.
     *
     * @return void
     */
    public function test_admin_cannot_resume_blocked_task_without_matching_resume_token(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->bindFollowUpProviderResolver();

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Reject blocked auto coding resume without token',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'response' => 'Focus on the auto-coding module.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['resume_token']);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'response' => 'Focus on the auto-coding module.',
            'resume_token' => 'task:999:run:999:blocked',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['resume_token']);
    }

    /**
     * Confirm confirmation-gated resumes reject arbitrary free-text responses.
     *
     * @return void
     */
    public function test_admin_cannot_resume_confirmation_gated_task_with_invalid_response_text(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->app->instance(\App\Services\AutoCoding\RepositoryContextService::class, new class extends \App\Services\AutoCoding\RepositoryContextService
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
            'summary' => 'Reject invalid confirmation response',
            '--dirty-policy' => 'block',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/resume', [
            'response' => 'maybe later',
            'resume_token' => $task->latest_report['follow_up']['input_contract']['resume_token'] ?? null,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['response']);
    }

    /**
     * Confirm non-admin users cannot access the local auto-coding admin APIs.
     *
     * @return void
     */
    public function test_non_admin_cannot_list_local_auto_coding_tasks(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->getJson('/api/admin/auto-coding/tasks');

        $response->assertForbidden();
    }

    /**
     * Bind one fake provider resolver that requires follow-up on the first run.
     *
     * @return void
     */
    protected function bindFollowUpProviderResolver(): void
    {
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
                                'message' => 'Clarify the exact coding scope.',
                                'questions' => [
                                    'Which module should this task focus on?',
                                ],
                            ],
                        ];
                    }
                };
            }
        });
    }
}
