<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\AutoCodingProviderResolver;
use App\Services\AutoCoding\AutoCodingWorkflowStepRunnerService;
use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingWorkflowStepRunnerServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the workflow step runner executes one provider step and persists its result.
     *
     * @return void
     */
    public function test_it_runs_one_provider_step(): void
    {
        $this->app->instance(AutoCodingProviderResolver::class, new class extends AutoCodingProviderResolver
        {
            public function __construct() {}

            public function resolve(?string $providerName = null): AutoCodingProviderInterface
            {
                return new class implements AutoCodingProviderInterface
                {
                    public function name(): string
                    {
                        return 'test-provider';
                    }

                    public function plan(array $context): array
                    {
                        return [
                            'status' => 'completed',
                            'provider' => 'test-provider',
                            'message' => (string) ($context['task_summary'] ?? ''),
                        ];
                    }
                };
            }
        });

        $service = $this->app->make(AutoCodingWorkflowStepRunnerService::class);
        $run = $this->createRun();

        $result = $service->runProviderStep($run, [
            'task_summary' => 'Inspect provider step',
        ], 'test-provider');

        self::assertSame('completed', $result['status'] ?? null);
        self::assertSame('Inspect provider step', $result['message'] ?? null);
        self::assertSame(1, $run->steps()->where('step_key', 'provider_plan')->count());
    }

    /**
     * Confirm the workflow step runner returns a skipped payload when validation is not requested.
     *
     * @return void
     */
    public function test_it_skips_validation_when_not_requested(): void
    {
        $service = $this->app->make(AutoCodingWorkflowStepRunnerService::class);
        $run = $this->createRun();

        $result = $service->runValidationStep($run, [
            'repository_path' => base_path('..'),
        ], false);

        self::assertSame('skipped', $result['overall_status'] ?? null);
        self::assertSame(1, $run->steps()->where('step_key', 'run_validation')->count());
    }

    /**
     * Create one minimal running task run for workflow-step tests.
     *
     * @return AutoCodingTaskRun
     */
    protected function createRun(): AutoCodingTaskRun
    {
        $task = AutoCodingTask::query()->create([
            'summary' => 'Workflow runner test',
            'repository_path' => base_path('..'),
            'status' => AutoCodingExecutionStatus::Pending,
        ]);
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'runner-test-machine',
            'hostname' => 'localhost',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        return AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Running,
            'repository_snapshot' => [
                'repository_path' => base_path('..'),
                'branch_name' => 'main',
                'is_dirty' => false,
                'changed_files' => [],
                'raw_status' => [],
            ],
            'started_at' => now(),
        ]);
    }
}
