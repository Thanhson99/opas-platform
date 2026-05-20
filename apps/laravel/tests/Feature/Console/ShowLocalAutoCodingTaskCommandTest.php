<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowLocalAutoCodingTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the show command prints the latest local task when requested.
     *
     * @return void
     */
    public function test_it_shows_the_latest_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->latest('id')->first();
        $run = AutoCodingTaskRun::query()->latest('id')->with('artifacts')->first();

        $this->artisan('opas:auto-coding:show', [
            '--latest' => true,
        ])
            ->expectsOutputToContain('"summary": "Inspect local auto coding foundation"')
            ->assertExitCode(0);

        self::assertNotNull($task);
        self::assertNotNull($run);
        self::assertCount(5, $run->artifacts);
    }

    /**
     * Confirm the show command reports a missing task id clearly.
     *
     * @return void
     */
    public function test_it_reports_when_a_local_auto_coding_task_is_missing(): void
    {
        $this->artisan('opas:auto-coding:show', [
            'taskId' => '9999',
        ])->assertExitCode(1);
    }
}
