<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingExecutionStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingExecutionStateServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the execution state service creates one running run record for a task.
     *
     * @return void
     */
    public function test_it_creates_one_running_task_run(): void
    {
        $service = $this->app->make(AutoCodingExecutionStateService::class);
        $task = AutoCodingTask::query()->create([
            'summary' => 'Create running run',
            'repository_path' => base_path('..'),
            'status' => AutoCodingExecutionStatus::Pending,
        ]);
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'test-machine',
            'hostname' => 'localhost',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        $run = $service->createRunningTaskRun($task, $machine->id, [
            'repository_path' => base_path('..'),
        ]);

        self::assertSame($task->id, $run->task_id);
        self::assertSame($machine->id, $run->machine_id);
        self::assertSame('running', $run->status->value);
    }

    /**
     * Confirm the execution state service marks one task as running with repository context.
     *
     * @return void
     */
    public function test_it_marks_one_task_as_running(): void
    {
        $service = $this->app->make(AutoCodingExecutionStateService::class);
        $task = AutoCodingTask::query()->create([
            'summary' => 'Mark running task',
            'repository_path' => base_path('..'),
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [
                'provider_name' => 'null',
            ],
        ]);

        $service->markTaskAsRunning($task, $task->context_payload ?? [], [
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
        ]);

        $task->refresh();

        self::assertSame('running', $task->status->value);
        self::assertSame('main', $task->branch_name);
        self::assertSame(base_path('..'), $task->context_payload['repository_context']['repository_path'] ?? null);
    }
}
