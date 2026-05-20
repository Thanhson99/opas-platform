<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\LocalAutoCodingTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalAutoCodingTaskServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local auto-coding service can create one pending queued task payload.
     *
     * @return void
     */
    public function test_it_creates_one_pending_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $service = $this->app->make(LocalAutoCodingTaskService::class);

        $task = $service->createPendingTask(
            'Queue local auto coding task',
            'OPAS-0070',
            null,
            true,
            'null',
            ['model' => null],
        );

        self::assertSame('Queue local auto coding task', $task->summary);
        self::assertSame('OPAS-0070', $task->issue_key);
        self::assertSame('pending', $task->status->value);
        self::assertSame('queued', $task->latest_report['queue']['status'] ?? null);
        self::assertSame(base_path('..'), $task->repository_path);
    }
}
