<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingMachineRoutingService;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingMachineRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm routing skips stale machines and assigns the task to a fresh idle machine.
     *
     * @return void
     */
    public function test_it_skips_stale_machines_when_routing_pending_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.machine_stale_seconds', 60);

        $staleMachine = $this->createMachine('stale-worker', base_path('..'), ['codex' => true]);
        $staleMachine->update([
            'last_seen_at' => now()->subMinutes(5),
        ]);
        $freshMachine = $this->createMachine('fresh-worker', base_path('..'), ['codex' => true]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route only to fresh workers',
            'repository_path' => base_path('..'),
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($freshMachine->id, $task->assigned_machine_id);
    }

    /**
     * Confirm busy machines are not selected for new work.
     *
     * @return void
     */
    public function test_it_skips_busy_machines_when_routing_pending_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $busyMachine = $this->createMachine('busy-worker', base_path('..'), ['codex' => true], 'busy');
        $idleMachine = $this->createMachine('idle-worker', base_path('..'), ['codex' => true]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route only to idle workers',
            'repository_path' => base_path('..'),
            'required_capabilities' => ['codex'],
        ]);

        self::assertNotSame($busyMachine->id, $task->assigned_machine_id);
        self::assertSame($idleMachine->id, $task->assigned_machine_id);
    }

    /**
     * Confirm workspace bindings let a machine serve repositories beyond its primary path.
     *
     * @return void
     */
    public function test_it_routes_to_machine_workspace_bindings(): void
    {
        $repositoryPath = '/srv/workspaces/secondary-repository';
        $machine = $this->createMachine('workspace-worker', base_path('..'), ['codex' => true]);
        $machine->update([
            'workspace_bindings' => [
                [
                    'repository_path' => $repositoryPath,
                    'workspace_path' => $repositoryPath,
                    'active_branch' => 'feature/opas-0073',
                ],
            ],
        ]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route through workspace binding',
            'repository_path' => $repositoryPath,
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($machine->id, $task->assigned_machine_id);
    }

    /**
     * Confirm routing matches Windows repository paths across slash variants.
     *
     * @return void
     */
    public function test_it_routes_windows_repository_paths_across_slash_variants(): void
    {
        $machineRepositoryPath = 'C:\\workspaces\\opas';
        $taskRepositoryPath = 'c:/workspaces/opas';
        $machine = $this->createMachine('windows-worker', $machineRepositoryPath, ['codex' => true]);
        $machine->update([
            'workspace_bindings' => null,
        ]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route Windows slash variant',
            'repository_path' => $taskRepositoryPath,
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($machine->id, $task->assigned_machine_id);
    }

    /**
     * Confirm routing matches Windows repository paths across directory casing.
     *
     * @return void
     */
    public function test_it_routes_windows_repository_paths_across_directory_casing(): void
    {
        $machineRepositoryPath = 'C:\\Workspaces\\OPAS';
        $taskRepositoryPath = 'c:/workspaces/opas';
        $machine = $this->createMachine('windows-case-worker', $machineRepositoryPath, ['codex' => true]);
        $machine->update([
            'workspace_bindings' => null,
        ]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route Windows directory case variant',
            'repository_path' => $taskRepositoryPath,
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($machine->id, $task->assigned_machine_id);
    }

    /**
     * Confirm Windows routing still honors newest heartbeat when exact prefilter misses a casing variant.
     *
     * @return void
     */
    public function test_it_routes_windows_repository_paths_to_newest_case_variant_candidate(): void
    {
        $taskRepositoryPath = 'c:/workspaces/opas';
        $olderExactMachine = $this->createMachine('windows-older-exact-worker', $taskRepositoryPath, ['codex' => true]);
        $olderExactMachine->update([
            'workspace_bindings' => null,
            'last_seen_at' => now()->subMinutes(2),
        ]);
        $newerCaseMachine = $this->createMachine('windows-newer-case-worker', 'C:\\Workspaces\\OPAS', ['codex' => true]);
        $newerCaseMachine->update([
            'workspace_bindings' => null,
            'last_seen_at' => now(),
        ]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route Windows newest case variant',
            'repository_path' => $taskRepositoryPath,
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($newerCaseMachine->id, $task->assigned_machine_id);
    }

    /**
     * Confirm capability mismatch keeps a task pending and unassigned.
     *
     * @return void
     */
    public function test_it_leaves_task_unassigned_when_required_capabilities_do_not_match(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        $this->createMachine('php-only-worker', base_path('..'), ['php' => true]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Needs Codex capability',
            'repository_path' => base_path('..'),
            'required_capabilities' => ['codex'],
        ]);

        self::assertNull($task->assigned_machine_id);
        self::assertSame('unassigned', $task->context_payload['routing']['status'] ?? null);
        self::assertSame('missing_capability', $task->context_payload['routing']['unassigned_reason'] ?? null);
        self::assertSame(1, $task->context_payload['routing']['candidate_machine_count'] ?? null);
    }

    /**
     * Confirm capability matching is case-insensitive across machine and task payloads.
     *
     * @return void
     */
    public function test_it_matches_capabilities_case_insensitively(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        $machine = $this->createMachine('mixed-case-worker', base_path('..'), ['Codex' => true]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route mixed case capability',
            'repository_path' => base_path('..'),
            'required_capabilities' => ['CODEX'],
        ]);

        self::assertSame($machine->id, $task->assigned_machine_id);
        self::assertSame(['codex'], $task->context_payload['routing']['required_capabilities'] ?? null);
    }

    /**
     * Confirm direct routing calls normalize capability names.
     *
     * @return void
     */
    public function test_it_normalizes_capabilities_on_direct_routing_calls(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        $machine = $this->createMachine('direct-route-worker', base_path('..'), ['codex' => true]);

        /** @var AutoCodingTask $task */
        $task = AutoCodingTask::query()->create([
            'summary' => 'Direct route mixed capability case',
            'repository_path' => base_path('..'),
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $task = $this->app->make(AutoCodingMachineRoutingService::class)->routePendingTask(
            $task,
            ['CODEX', ' codex '],
        );

        self::assertSame($machine->id, $task->assigned_machine_id);
        self::assertSame(['codex'], $task->context_payload['routing']['required_capabilities'] ?? null);
    }

    /**
     * Confirm routing capacity includes pending assignments, not only running work.
     *
     * @return void
     */
    public function test_it_routes_around_pending_assignments_at_machine_capacity(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $firstMachine = $this->createMachine('first-worker', base_path('..'), ['codex' => true]);
        $secondMachine = $this->createMachine('second-worker', base_path('..'), ['codex' => true]);

        $firstTask = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Reserve the newest worker slot',
            'repository_path' => base_path('..'),
            'required_capabilities' => ['codex'],
        ]);
        $secondTask = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route around reserved pending slot',
            'repository_path' => base_path('..'),
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($secondMachine->id, $firstTask->assigned_machine_id);
        self::assertSame($firstMachine->id, $secondTask->assigned_machine_id);
    }

    /**
     * Confirm routing cannot mutate tasks that are no longer pending.
     *
     * @return void
     */
    public function test_it_does_not_reroute_non_pending_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $assignedMachine = $this->createMachine('assigned-worker', base_path('..'), ['codex' => true]);
        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Do not reroute running task',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'assigned-worker',
            'required_capabilities' => ['codex'],
        ]);
        $task->update([
            'status' => AutoCodingExecutionStatus::Running,
        ]);
        $this->createMachine('newer-worker', base_path('..'), ['codex' => true]);

        $reroutedTask = $this->app->make(AutoCodingMachineRoutingService::class)->routePendingTask(
            $task->refresh(),
            ['codex'],
            null,
        );
        $task->refresh();

        self::assertSame($assignedMachine->id, $reroutedTask->assigned_machine_id);
        self::assertSame($assignedMachine->id, $task->assigned_machine_id);
        self::assertSame('running', $task->status->value);
        self::assertSame('assigned-worker', $task->context_payload['routing']['assigned_machine_key'] ?? null);
    }

    /**
     * Confirm routing cannot mutate a task that became running after the model was loaded.
     *
     * @return void
     */
    public function test_it_does_not_reroute_stale_pending_model_after_database_status_changes(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $assignedMachine = $this->createMachine('stale-model-worker', base_path('..'), ['codex' => true]);
        $staleTask = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Do not reroute stale pending model',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'stale-model-worker',
            'required_capabilities' => ['codex'],
        ]);
        $staleTask->newQuery()
            ->whereKey($staleTask->id)
            ->update([
                'status' => AutoCodingExecutionStatus::Running,
            ]);
        $this->createMachine('newer-stale-model-worker', base_path('..'), ['codex' => true]);

        $reroutedTask = $this->app->make(AutoCodingMachineRoutingService::class)->routePendingTask(
            $staleTask,
            ['codex'],
            null,
        );

        self::assertSame($assignedMachine->id, $reroutedTask->assigned_machine_id);
        self::assertSame('running', $reroutedTask->status->value);
        self::assertSame('stale-model-worker', $reroutedTask->context_payload['routing']['assigned_machine_key'] ?? null);
    }

    /**
     * Confirm routing cannot overwrite an assignment changed after the model was loaded.
     *
     * @return void
     */
    public function test_it_does_not_reroute_stale_pending_model_after_assignment_changes(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $originalMachine = $this->createMachine('original-assignment-worker', base_path('..'), ['codex' => true]);
        $replacementMachine = $this->createMachine('replacement-assignment-worker', base_path('..'), ['codex' => true]);
        $staleTask = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Do not overwrite changed assignment',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'original-assignment-worker',
            'required_capabilities' => ['codex'],
        ]);
        $staleTask->newQuery()
            ->whereKey($staleTask->id)
            ->update([
                'assigned_machine_id' => $replacementMachine->id,
            ]);
        $this->createMachine('newest-assignment-worker', base_path('..'), ['codex' => true]);

        $reroutedTask = $this->app->make(AutoCodingMachineRoutingService::class)->routePendingTask(
            $staleTask,
            ['codex'],
            null,
        );

        self::assertSame($originalMachine->id, $staleTask->assigned_machine_id);
        self::assertSame($replacementMachine->id, $reroutedTask->assigned_machine_id);
        self::assertSame('pending', $reroutedTask->status->value);
    }

    /**
     * Confirm routing merges into the locked task payload instead of stale model payload.
     *
     * @return void
     */
    public function test_it_preserves_payload_changes_when_routing_stale_pending_model(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        $machine = $this->createMachine('payload-lock-worker', base_path('..'), ['codex' => true]);

        /** @var AutoCodingTask $staleTask */
        $staleTask = AutoCodingTask::query()->create([
            'summary' => 'Preserve payload during route',
            'repository_path' => base_path('..'),
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [
                'repository_path' => base_path('..'),
            ],
            'latest_report' => [
                'status' => 'pending',
            ],
        ]);
        $staleTask->newQuery()
            ->whereKey($staleTask->id)
            ->update([
                'context_payload' => [
                    'repository_path' => base_path('..'),
                    'metadata' => [
                        'source' => 'telegram',
                    ],
                ],
                'latest_report' => [
                    'status' => 'pending',
                    'message' => 'fresh report detail',
                ],
            ]);

        $routedTask = $this->app->make(AutoCodingMachineRoutingService::class)->routePendingTask(
            $staleTask,
            ['codex'],
        );

        self::assertSame($machine->id, $routedTask->assigned_machine_id);
        self::assertSame('telegram', $routedTask->context_payload['metadata']['source'] ?? null);
        self::assertSame('fresh report detail', $routedTask->latest_report['message'] ?? null);
        self::assertSame('assigned', $routedTask->latest_report['routing']['status'] ?? null);
    }

    /**
     * Create one auto-coding machine heartbeat fixture.
     *
     * @param  string  $machineKey
     * @param  string  $repositoryPath
     * @param  array<string, bool>  $capabilities
     * @param  string  $availabilityStatus
     * @return AutoCodingMachine
     */
    protected function createMachine(
        string $machineKey,
        string $repositoryPath,
        array $capabilities,
        string $availabilityStatus = 'idle',
    ): AutoCodingMachine {
        /** @var AutoCodingMachine $machine */
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => $machineKey,
            'hostname' => $machineKey.'.local',
            'operating_system' => 'Darwin',
            'availability_status' => $availabilityStatus,
            'repository_path' => $repositoryPath,
            'capabilities' => $capabilities,
            'workspace_bindings' => [
                [
                    'repository_path' => $repositoryPath,
                    'workspace_path' => $repositoryPath,
                    'active_branch' => null,
                ],
            ],
            'max_parallel_tasks' => 1,
            'last_seen_at' => now(),
        ]);

        return $machine;
    }
}
