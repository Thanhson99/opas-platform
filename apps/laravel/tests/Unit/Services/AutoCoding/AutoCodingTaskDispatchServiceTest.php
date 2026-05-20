<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $taskService->createPendingTask('Dispatch executes task', 'OPAS-0070');

        $service = $this->app->make(AutoCodingTaskDispatchService::class);
        $task = $service->claimAndOptionallyExecute(base_path('..'), true);

        self::assertNotNull($task);
        self::assertSame('Dispatch executes task', $task->summary);
        self::assertSame('completed', $task->status->value);
    }
}
