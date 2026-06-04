<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use App\Services\AutoCoding\LocalAutoCodingWorkerService;
use App\Services\AutoCoding\LocalMachineService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

    /**
     * Confirm local workers only claim tasks routed to their own machine identity.
     *
     * @return void
     */
    public function test_it_does_not_claim_task_assigned_to_another_machine(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machineService = $this->app->make(LocalMachineService::class);
        $machineService->recordHeartbeat([
            'machine_key' => 'first-worker',
            'hostname' => 'first.local',
            'operating_system' => 'Darwin',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
        ]);
        $secondMachine = $machineService->recordHeartbeat([
            'machine_key' => 'second-worker',
            'hostname' => 'second.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
        ]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Only second worker should claim this task',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'second-worker',
            'required_capabilities' => ['codex'],
        ]);

        config()->set('opas.auto_coding.machine_key', 'first-worker');
        $service = $this->app->make(LocalAutoCodingWorkerService::class);
        $firstPayload = $service->runCycle(null, false);

        config()->set('opas.auto_coding.machine_key', 'second-worker');
        $secondPayload = $service->runCycle(null, false);
        $task->refresh();

        self::assertSame($secondMachine->id, $task->assigned_machine_id);
        self::assertFalse($firstPayload['claimed']);
        self::assertTrue($secondPayload['claimed']);
        self::assertSame('running', $task->status->value);
        self::assertSame('claimed', $task->latest_report['routing']['status'] ?? null);
        self::assertSame('second-worker', $task->latest_report['routing']['preferred_machine_key'] ?? null);
        self::assertSame(['codex'], $task->latest_report['routing']['required_capabilities'] ?? null);
        self::assertSame(2, $task->latest_report['routing']['candidate_machine_count'] ?? null);
    }

    /**
     * Confirm pending tasks assigned to stale machines can be rerouted to an available worker.
     *
     * @return void
     */
    public function test_it_reroutes_pending_task_from_stale_machine_to_current_worker(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.machine_stale_seconds', 60);
        config()->set('opas.auto_coding.machine_key', 'stale-worker');

        $machineService = $this->app->make(LocalMachineService::class);
        $staleMachine = $machineService->recordHeartbeat([
            'machine_key' => 'stale-worker',
            'hostname' => 'stale.local',
            'operating_system' => 'Darwin',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
        ]);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Reroute this stale assignment',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'stale-worker',
            'required_capabilities' => ['codex'],
        ]);
        $staleMachine->update([
            'last_seen_at' => now()->subMinutes(5),
        ]);

        config()->set('opas.auto_coding.machine_key', 'fresh-worker');
        $service = $this->app->make(LocalAutoCodingWorkerService::class);
        $payload = $service->runCycle(null, false, [
            'capabilities' => ['codex'],
        ]);
        $task->refresh();

        self::assertTrue($payload['claimed']);
        self::assertSame('fresh-worker', $payload['machine']['machine_key'] ?? null);
        self::assertNotSame($staleMachine->id, $task->assigned_machine_id);
        self::assertSame('running', $task->status->value);
    }

    /**
     * Confirm repository-specific worker cycles do not claim tasks from another bound workspace.
     *
     * @return void
     */
    public function test_it_respects_repository_constraint_for_multi_workspace_machine_claims(): void
    {
        $primaryRepository = base_path('..');
        $secondaryRepository = '/srv/workspaces/secondary-repository';
        config()->set('opas.auto_coding.default_repository_path', $primaryRepository);
        config()->set('opas.auto_coding.machine_key', 'multi-workspace-worker');

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $secondaryTask = $taskService->createPendingTask(
            'Do not claim secondary repository task',
            null,
            $secondaryRepository,
        );
        $primaryTask = $taskService->createPendingTask(
            'Claim primary repository task',
            null,
            $primaryRepository,
        );

        $service = $this->app->make(LocalAutoCodingWorkerService::class);
        $payload = $service->runCycle($primaryRepository, false, [
            'capabilities' => ['codex'],
            'workspace_bindings' => [
                [
                    'repository_path' => $secondaryRepository,
                    'workspace_path' => $secondaryRepository,
                    'active_branch' => 'feature/secondary',
                ],
            ],
        ]);
        $primaryTask->refresh();
        $secondaryTask->refresh();

        self::assertTrue($payload['claimed']);
        self::assertSame($primaryTask->id, $payload['task']['id'] ?? null);
        self::assertSame('running', $primaryTask->status->value);
        self::assertSame('pending', $secondaryTask->status->value);
    }

    /**
     * Confirm local worker claims respect machine parallel capacity.
     *
     * @return void
     */
    public function test_it_does_not_claim_when_machine_is_at_running_capacity(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.machine_key', 'capacity-worker');

        $machine = $this->app->make(LocalMachineService::class)->recordHeartbeat([
            'machine_key' => 'capacity-worker',
            'hostname' => 'capacity.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
            'max_parallel_tasks' => 1,
        ]);
        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $runningTask = $taskService->createPendingTask('Already running task', null, base_path('..'));
        $runningTask->update([
            'assigned_machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Running,
        ]);
        $queuedTask = $taskService->createPendingTask('Do not claim while busy', null, base_path('..'));

        $payload = $this->app->make(LocalAutoCodingWorkerService::class)->runCycle(null, false, [
            'capabilities' => ['codex'],
            'max_parallel_tasks' => 1,
        ]);
        $queuedTask->refresh();

        self::assertFalse($payload['claimed']);
        self::assertSame('pending', $queuedTask->status->value);
        self::assertNull($queuedTask->assigned_machine_id);
    }

    /**
     * Confirm claim capacity is rechecked from the locked machine row, not a stale machine object.
     *
     * @return void
     */
    public function test_it_rechecks_capacity_from_locked_machine_row(): void
    {
        $repositoryPath = base_path('..');
        config()->set('opas.auto_coding.default_repository_path', $repositoryPath);

        $machine = $this->app->make(LocalMachineService::class)->recordHeartbeat([
            'machine_key' => 'stale-capacity-worker',
            'hostname' => 'stale-capacity.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => $repositoryPath,
            'capabilities' => [
                'codex' => true,
            ],
            'max_parallel_tasks' => 2,
        ]);

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $runningTask = $taskService->createPendingTask('Already running after capacity changed', null, $repositoryPath);
        $runningTask->update([
            'assigned_machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Running,
        ]);
        $queuedTask = $taskService->createPendingTask('Do not claim with stale capacity object', null, $repositoryPath);
        $machine->newQuery()
            ->whereKey($machine->id)
            ->update([
                'max_parallel_tasks' => 1,
            ]);

        $claimedTask = $taskService->claimNextPendingTask($repositoryPath, $machine);
        $queuedTask->refresh();

        self::assertNull($claimedTask);
        self::assertSame('pending', $queuedTask->status->value);
        self::assertNull($queuedTask->claimed_at);
    }

    /**
     * Confirm worker claims match Windows repository paths across slash variants.
     *
     * @return void
     */
    public function test_it_claims_windows_repository_paths_across_slash_variants(): void
    {
        $workerRepositoryPath = 'C:\\workspaces\\opas';
        $taskRepositoryPath = 'c:/workspaces/opas';
        config()->set('opas.auto_coding.default_repository_path', $workerRepositoryPath);
        config()->set('opas.auto_coding.machine_key', 'windows-worker');

        $task = $this->app->make(LocalAutoCodingTaskService::class)->createPendingTask(
            'Claim Windows slash variant task',
            null,
            $taskRepositoryPath,
        );

        $payload = $this->app->make(LocalAutoCodingWorkerService::class)->runCycle($workerRepositoryPath, false, [
            'capabilities' => ['codex'],
        ]);
        $task->refresh();

        self::assertTrue($payload['claimed']);
        self::assertSame($task->id, $payload['task']['id'] ?? null);
        self::assertSame('running', $task->status->value);
    }

    /**
     * Confirm worker claims match Windows repository paths across directory casing.
     *
     * @return void
     */
    public function test_it_claims_windows_repository_paths_across_directory_casing(): void
    {
        $workerRepositoryPath = 'C:\\Workspaces\\OPAS';
        $taskRepositoryPath = 'c:/workspaces/opas';
        config()->set('opas.auto_coding.default_repository_path', $workerRepositoryPath);
        config()->set('opas.auto_coding.machine_key', 'windows-case-worker');

        $task = $this->app->make(LocalAutoCodingTaskService::class)->createPendingTask(
            'Claim Windows directory case variant task',
            null,
            $taskRepositoryPath,
        );

        $payload = $this->app->make(LocalAutoCodingWorkerService::class)->runCycle($workerRepositoryPath, false, [
            'capabilities' => ['codex'],
        ]);
        $task->refresh();

        self::assertTrue($payload['claimed']);
        self::assertSame($task->id, $payload['task']['id'] ?? null);
        self::assertSame('running', $task->status->value);
    }

    /**
     * Confirm Windows repository matching preserves oldest-task ordering across directory casing.
     *
     * @return void
     */
    public function test_it_claims_oldest_windows_repository_path_case_variant(): void
    {
        $workerRepositoryPath = 'c:/workspaces/opas';
        config()->set('opas.auto_coding.default_repository_path', $workerRepositoryPath);
        config()->set('opas.auto_coding.machine_key', 'windows-oldest-case-worker');

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $olderTask = $taskService->createPendingTask(
            'Claim older Windows directory case variant',
            null,
            'C:\\Workspaces\\OPAS',
        );
        $newerTask = $taskService->createPendingTask(
            'Leave newer exact Windows task pending',
            null,
            $workerRepositoryPath,
        );

        $payload = $this->app->make(LocalAutoCodingWorkerService::class)->runCycle($workerRepositoryPath, false, [
            'capabilities' => ['codex'],
        ]);
        $olderTask->refresh();
        $newerTask->refresh();

        self::assertTrue($payload['claimed']);
        self::assertSame($olderTask->id, $payload['task']['id'] ?? null);
        self::assertSame('running', $olderTask->status->value);
        self::assertSame('pending', $newerTask->status->value);
    }

    /**
     * Confirm worker heartbeat returns to idle when execution throws outside normal task failure handling.
     *
     * @return void
     */
    public function test_it_restores_idle_heartbeat_when_execution_throws(): void
    {
        $repositoryPath = base_path('..');
        config()->set('opas.auto_coding.default_repository_path', $repositoryPath);
        config()->set('opas.auto_coding.machine_key', 'throwing-worker');

        $task = $this->app->make(LocalAutoCodingTaskService::class)->createPendingTask(
            'Throw during worker execution',
            null,
            $repositoryPath,
        );

        $this->app->instance(LocalAutoCodingTaskService::class, new class($task) extends LocalAutoCodingTaskService
        {
            public function __construct(private readonly AutoCodingTask $task) {}

            public function claimNextPendingTask(?string $repositoryPath = null, ?AutoCodingMachine $machine = null): ?AutoCodingTask
            {
                return $this->task;
            }

            public function executePendingTask(int $taskId, ?AutoCodingMachine $executionMachine = null): AutoCodingTaskRun
            {
                throw new RuntimeException('Execution failed before task service could mark a failed run.');
            }
        });

        try {
            $this->app->make(LocalAutoCodingWorkerService::class)->runCycle($repositoryPath, true, [
                'capabilities' => ['codex'],
            ]);
            self::fail('Expected worker cycle to rethrow the execution exception.');
        } catch (RuntimeException $exception) {
            self::assertSame('Execution failed before task service could mark a failed run.', $exception->getMessage());
        }

        /** @var AutoCodingMachine|null $machine */
        $machine = AutoCodingMachine::query()
            ->where('machine_key', 'throwing-worker')
            ->first();

        self::assertNotNull($machine);
        self::assertSame('idle', $machine->availability_status);
    }

    /**
     * Confirm claim revalidates the locked task repository before marking it running.
     *
     * @return void
     */
    public function test_it_rejects_claim_when_locked_task_repository_changed(): void
    {
        $repositoryPath = base_path('..');
        config()->set('opas.auto_coding.default_repository_path', $repositoryPath);

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $task = $taskService->createPendingTask(
            'Reject stale repository claim',
            null,
            $repositoryPath,
        );
        $staleTask = $task->fresh();
        self::assertInstanceOf(AutoCodingTask::class, $staleTask);

        $task->update([
            'repository_path' => '/srv/workspaces/other-repository',
        ]);

        $this->app->instance(AutoCodingTaskRepositoryInterface::class, new class($staleTask) implements AutoCodingTaskRepositoryInterface
        {
            public function __construct(private readonly AutoCodingTask $staleTask) {}

            public function paginateForAdmin(?string $status, ?string $issueKey, int $perPage): LengthAwarePaginator
            {
                throw new RuntimeException('Not used by this test.');
            }

            public function getLatest(int $limit, ?string $status, ?string $issueKey): array
            {
                return [];
            }

            public function findDetailedById(int $taskId): ?AutoCodingTask
            {
                return AutoCodingTask::query()->find($taskId);
            }

            public function findOldestPending(?string $repositoryPath = null): ?AutoCodingTask
            {
                return $this->staleTask;
            }

            public function findOldestPendingForMachine(AutoCodingMachine $machine, ?string $repositoryPath = null): ?AutoCodingTask
            {
                return $this->staleTask;
            }

            public function getOldestPendingAssignedOutsideMachine(
                AutoCodingMachine $machine,
                ?string $repositoryPath = null,
                int $limit = 10,
            ): array {
                return [];
            }

            public function findLatestDetailed(): ?AutoCodingTask
            {
                return null;
            }

            public function findLatestDetailedByBranchName(string $branchName): ?AutoCodingTask
            {
                return null;
            }
        });

        $claimedTask = $this->app->make(LocalAutoCodingTaskService::class)->claimNextPendingTask($repositoryPath);
        $task->refresh();

        self::assertNull($claimedTask);
        self::assertSame('pending', $task->status->value);
        self::assertNull($task->claimed_at);
    }
}
