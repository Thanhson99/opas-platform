<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\AutoCodingProviderResolver;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutoCodingTaskDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the dispatch service creates one normalized pending task from transport payload data.
     *
     * @return void
     */
    public function test_it_creates_one_pending_task_from_payload(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $service = $this->app->make(AutoCodingTaskDispatchService::class);

        $task = $service->createPendingTaskFromPayload([
            'summary' => 'Dispatch task from payload',
            'issue_key' => ' OPAS-0070 ',
            'repository_path' => ' '.base_path('..').' ',
            'validate' => true,
            'provider' => ' null ',
            'dirty_workspace_policy' => ' block ',
            'scope_paths' => [' apps/laravel/app/Services ', 'docs/roadmap', ''],
            'scope_policy' => ' block ',
            'provider_options' => [
                'model' => 'qwen2.5:7b',
                '' => 'ignored',
                123 => 'ignored',
            ],
        ]);

        self::assertSame('Dispatch task from payload', $task->summary);
        self::assertSame('OPAS-0070', $task->issue_key);
        self::assertSame(base_path('..'), $task->repository_path);
        self::assertSame('null', $task->context_payload['provider_name'] ?? null);
        self::assertSame('block', $task->context_payload['dirty_workspace_policy'] ?? null);
        self::assertSame(['apps/laravel/app/Services', 'docs/roadmap'], $task->context_payload['scope_paths'] ?? null);
        self::assertSame('block', $task->context_payload['scope_policy'] ?? null);
        self::assertSame(['model' => 'qwen2.5:7b'], $task->context_payload['provider_options'] ?? null);
    }

    /**
     * Confirm the dispatch service can claim and execute the next pending task for one repository.
     *
     * @return void
     */
    public function test_it_can_claim_and_execute_one_pending_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.provider', 'null');

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $taskService->createPendingTask('Dispatch executes task', 'OPAS-0070');

        $service = $this->app->make(AutoCodingTaskDispatchService::class);
        $task = $service->claimAndOptionallyExecute(base_path('..'), true);

        self::assertNotNull($task);
        self::assertSame('Dispatch executes task', $task->summary);
        self::assertSame('completed', $task->status->value);
    }

    /**
     * Confirm the dispatch service rejects blocked-task resumes when the token is stale.
     *
     * @return void
     */
    public function test_it_rejects_resume_when_the_resume_token_is_stale(): void
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

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $run = $taskService->runInspectionTask('Reject stale token resume');

        self::assertSame('blocked', $run->status->value);

        $service = $this->app->make(AutoCodingTaskDispatchService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Resume token is stale or invalid');

        $service->resumeBlockedTask($run->task_id, 'Focus on the auto-coding module.', 'task:999:run:999:blocked');
    }

    /**
     * Confirm confirmation-gated blocked tasks reject arbitrary response text.
     *
     * @return void
     */
    public function test_it_rejects_invalid_confirmation_responses_during_resume(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $task = $taskService->createPendingTask(
            'Reject invalid confirmation response',
            null,
            base_path('..'),
            false,
            null,
            [],
            'block',
        );

        AutoCodingTask::query()->whereKey($task->id)->update([
            'status' => \App\Enums\AutoCodingExecutionStatus::Blocked,
            'latest_report' => [
                'follow_up' => [
                    'required' => true,
                    'reason' => 'dirty_workspace',
                    'input_contract' => [
                        'type' => 'confirmation',
                        'accepted_values' => ['allow', 'continue', 'proceed', 'yes'],
                        'free_text_allowed' => false,
                    ],
                ],
            ],
        ]);

        $run = AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => AutoCodingMachine::query()->create([
                'machine_key' => 'test-machine',
                'hostname' => 'localhost',
                'operating_system' => 'macos',
                'status' => 'online',
                'last_seen_at' => now(),
            ])->id,
            'status' => \App\Enums\AutoCodingExecutionStatus::Blocked,
            'started_at' => now(),
            'completed_at' => now(),
            'repository_snapshot' => [
                'repository_path' => base_path('..'),
                'branch_name' => 'main',
                'is_dirty' => true,
                'changed_files' => [],
                'raw_status' => [],
            ],
            'changed_files' => [],
            'provider_result' => [],
            'validation_results' => [],
            'final_report' => [],
        ]);

        $service = $this->app->make(AutoCodingTaskDispatchService::class);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This blocked task expects an explicit confirmation response');

        $service->resumeBlockedTask($task->id, 'maybe later', 'task:'.$task->id.':run:'.$run->id.':blocked');
    }
}
