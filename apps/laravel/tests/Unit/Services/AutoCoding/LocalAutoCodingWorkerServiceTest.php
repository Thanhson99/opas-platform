<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use App\Services\AutoCoding\LocalAutoCodingWorkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAutoCodingWorkerServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local worker service can heartbeat without claiming any task when the queue is empty.
     *
     * @return void
     */
    public function test_it_heartbeats_without_claiming_when_no_pending_task_exists(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $service = $this->app->make(LocalAutoCodingWorkerService::class);

        $payload = $service->runCycle(null, false);

        self::assertTrue(is_array($payload['machine']));
        self::assertFalse($payload['claimed']);
        self::assertFalse($payload['executed']);
        self::assertNull($payload['task']);
        self::assertNull($payload['run']);
    }

    /**
     * Confirm the local worker service can claim and execute one pending local auto-coding task.
     *
     * @return void
     */
    public function test_it_claims_and_executes_one_pending_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $taskService->createPendingTask('Worker executes pending task', 'OPAS-0070');

        $service = $this->app->make(LocalAutoCodingWorkerService::class);

        $payload = $service->runCycle(null, true);

        $task = AutoCodingTask::query()->first();
        $run = AutoCodingTaskRun::query()->first();

        self::assertNotNull($task);
        self::assertNotNull($run);
        self::assertTrue($payload['claimed']);
        self::assertTrue($payload['executed']);
        self::assertSame('completed', $task->status->value);
        self::assertSame('completed', $run->status->value);
    }
}
