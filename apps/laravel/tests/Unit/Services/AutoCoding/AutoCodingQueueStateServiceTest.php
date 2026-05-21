<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingQueueStateService;
use Tests\TestCase;

class AutoCodingQueueStateServiceTest extends TestCase
{
    /**
     * Confirm the queue state service builds one pending queued report.
     *
     * @return void
     */
    public function test_it_builds_one_pending_report(): void
    {
        $service = $this->app->make(AutoCodingQueueStateService::class);

        $report = $service->buildPendingReport(
            'Queue local auto coding task',
            'OPAS-0070',
            base_path('..')
        );

        self::assertSame('pending', $report['status'] ?? null);
        self::assertSame('queued', $report['queue']['status'] ?? null);
        self::assertSame(base_path('..'), $report['repository']['repository_path'] ?? null);
    }

    /**
     * Confirm the queue state service can build claimed and resumed queue transitions.
     *
     * @return void
     */
    public function test_it_builds_claimed_and_resumed_queue_reports(): void
    {
        $service = $this->app->make(AutoCodingQueueStateService::class);
        $task = new AutoCodingTask([
            'status' => AutoCodingExecutionStatus::Pending,
            'latest_report' => [
                'queue' => [
                    'status' => 'queued',
                ],
            ],
        ]);

        $claimed = $service->buildClaimedLatestReport($task);
        $resumed = $service->buildResumedLatestReport($task);

        self::assertSame('running', $claimed['status'] ?? null);
        self::assertSame('claimed', $claimed['queue']['status'] ?? null);
        self::assertSame('pending', $resumed['status'] ?? null);
        self::assertSame('resumed', $resumed['queue']['status'] ?? null);
        self::assertTrue($resumed['follow_up']['answered'] ?? false);
    }
}
