<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingRunArtifact;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunLocalAutoCodingTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local auto-coding command stores machine, task, and run reports.
     *
     * @return void
     */
    public function test_it_runs_the_local_auto_coding_command_and_persists_the_report(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();
        $run = AutoCodingTaskRun::query()->first();
        $machine = AutoCodingMachine::query()->first();
        $artifacts = AutoCodingRunArtifact::query()->orderBy('type')->get();

        self::assertNotNull($task);
        self::assertNotNull($run);
        self::assertNotNull($machine);
        self::assertSame('OPAS-0070', $task->issue_key);
        self::assertIsArray($task->latest_report);
        self::assertSame($task->getKey(), $run->task_id);
        self::assertSame($machine->getKey(), $run->machine_id);
        self::assertIsString($task->latest_report['github']['repository_slug'] ?? null);
        self::assertStringStartsWith('Thanhson99/', $task->latest_report['github']['repository_slug']);
        self::assertCount(5, $artifacts);
        self::assertSame(
            ['final_report', 'github_context', 'provider_result', 'repository_snapshot', 'validation_result'],
            $artifacts->pluck('type')->all()
        );
        self::assertSame(5, $task->latest_report['summary']['artifact_count']);
    }
}
