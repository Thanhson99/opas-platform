<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkLocalAutoCodingTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local auto-coding worker command can claim and execute one pending task in one iteration.
     *
     * @return void
     */
    public function test_it_runs_one_local_auto_coding_worker_iteration(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $taskService->createPendingTask('Run worker loop task', 'OPAS-0070');

        $this->artisan('opas:auto-coding:work', [
            '--execute' => true,
            '--max-iterations' => 1,
            '--interval' => 0,
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();
        $run = AutoCodingTaskRun::query()->first();

        self::assertNotNull($task);
        self::assertNotNull($run);
        self::assertSame('completed', $task->status->value);
        self::assertSame('completed', $run->status->value);
    }
}
