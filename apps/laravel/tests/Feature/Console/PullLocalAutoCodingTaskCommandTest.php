<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AutoCodingTask;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PullLocalAutoCodingTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local auto-coding pull command can claim the next pending task.
     *
     * @return void
     */
    public function test_it_claims_the_next_pending_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $service = $this->app->make(LocalAutoCodingTaskService::class);
        $service->createPendingTask('Claim pending local task', 'OPAS-0070');

        $this->artisan('opas:auto-coding:pull')->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Claim pending local task', $task->summary);
        self::assertSame('running', $task->status->value);
    }
}
