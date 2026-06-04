<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Services\AutoCoding\AutoCodingAgentAuthService;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\LocalAutoCodingTaskService;
use App\Services\AutoCoding\LocalMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentAutoCodingApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated machine agent can send heartbeat updates.
     *
     * @return void
     */
    public function test_authenticated_agent_can_send_heartbeat(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machineService = $this->app->make(LocalMachineService::class);
        $machine = $machineService->resolve(base_path('..'));
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/agent/auto-coding/heartbeat', [
                'availability_status' => 'idle',
                'repository_path' => base_path('..'),
                'capabilities' => [
                    'Codex',
                    'PHP',
                ],
                'workspace_bindings' => [
                    [
                        'repository_path' => base_path('..'),
                        'workspace_path' => base_path('..'),
                        'active_branch' => 'feature/opas-0073',
                    ],
                ],
                'metadata' => [
                    'editor' => 'vscode',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'machine_key' => $machine->machine_key,
                'repository_path' => base_path('..'),
                'availability_status' => 'idle',
            ]);

        $machine->refresh();

        self::assertTrue($machine->capabilities['codex'] ?? false);
        self::assertTrue($machine->capabilities['php'] ?? false);
    }

    /**
     * Confirm an authenticated machine agent can claim and execute one pending task.
     *
     * @return void
     */
    public function test_authenticated_agent_can_claim_and_execute_one_pending_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machineService = $this->app->make(LocalMachineService::class);
        $machine = $machineService->resolve(base_path('..'));
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $taskService->createPendingTask('Agent executes pending task', 'OPAS-0070');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/agent/auto-coding/tasks/claim', [
                'execute' => true,
            ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Agent executes pending task',
                'status' => 'completed',
            ]);
    }

    /**
     * Confirm agent task claiming respects machine routing assignment.
     *
     * @return void
     */
    public function test_authenticated_agent_claims_only_tasks_routed_to_its_machine(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machineService = $this->app->make(LocalMachineService::class);
        $firstMachine = $machineService->recordHeartbeat([
            'machine_key' => 'first-machine',
            'hostname' => 'first.local',
            'operating_system' => 'Darwin',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
        ]);
        $secondMachine = $machineService->recordHeartbeat([
            'machine_key' => 'second-machine',
            'hostname' => 'second.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
        ]);
        $firstToken = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($firstMachine);
        $secondToken = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($secondMachine);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Route this task to the second machine',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'second-machine',
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($secondMachine->id, $task->assigned_machine_id);

        $firstResponse = $this->withHeader('Authorization', 'Bearer '.$firstToken)
            ->postJson('/api/agent/auto-coding/tasks/claim');

        $firstResponse
            ->assertOk()
            ->assertJsonPath('data', null);

        $secondResponse = $this->withHeader('Authorization', 'Bearer '.$secondToken)
            ->postJson('/api/agent/auto-coding/tasks/claim');

        $secondResponse
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Route this task to the second machine',
                'assigned_machine_id' => $secondMachine->id,
                'status' => 'running',
            ]);
    }

    /**
     * Confirm claim refreshes heartbeat freshness before capacity checks.
     *
     * @return void
     */
    public function test_authenticated_agent_claim_refreshes_stale_idle_machine_heartbeat(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.machine_stale_seconds', 60);

        $machine = $this->app->make(LocalMachineService::class)->recordHeartbeat([
            'machine_key' => 'stale-claim-agent',
            'hostname' => 'stale-claim.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => base_path('..'),
            'capabilities' => [
                'codex' => true,
            ],
        ]);
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);
        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Claim after stale heartbeat',
            'repository_path' => base_path('..'),
            'preferred_machine_key' => 'stale-claim-agent',
            'required_capabilities' => ['codex'],
        ]);
        $machine->update([
            'last_seen_at' => now()->subMinutes(5),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/agent/auto-coding/tasks/claim');
        $task->refresh();
        $machine->refresh();

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Claim after stale heartbeat',
                'status' => 'running',
            ]);
        self::assertSame($machine->id, $task->assigned_machine_id);
        self::assertSame('running', $task->status->value);
        self::assertTrue($machine->last_seen_at !== null && $machine->last_seen_at->diffInSeconds(now()) < 60);
    }

    /**
     * Confirm an agent can claim work for a repository exposed through its workspace bindings.
     *
     * @return void
     */
    public function test_authenticated_agent_can_claim_task_for_bound_workspace_repository(): void
    {
        $primaryRepository = base_path('..');
        $secondaryRepository = '/srv/workspaces/agent-secondary';
        config()->set('opas.auto_coding.default_repository_path', $primaryRepository);

        $machine = $this->app->make(LocalMachineService::class)->recordHeartbeat([
            'machine_key' => 'workspace-agent',
            'hostname' => 'workspace-agent.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => $primaryRepository,
            'capabilities' => [
                'codex' => true,
            ],
            'workspace_bindings' => [
                [
                    'repository_path' => $secondaryRepository,
                    'workspace_path' => $secondaryRepository,
                    'active_branch' => 'feature/workspace-agent',
                ],
            ],
        ]);
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Claim workspace-bound repository task',
            'repository_path' => $secondaryRepository,
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($machine->id, $task->assigned_machine_id);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/agent/auto-coding/tasks/claim', [
                'repository_path' => $secondaryRepository,
            ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Claim workspace-bound repository task',
                'repository_path' => $secondaryRepository,
                'status' => 'running',
            ]);
    }

    /**
     * Confirm an agent claim without repository constraint searches all bound repositories.
     *
     * @return void
     */
    public function test_authenticated_agent_can_claim_bound_workspace_task_without_repository_path(): void
    {
        $primaryRepository = base_path('..');
        $secondaryRepository = '/srv/workspaces/agent-secondary-default';
        config()->set('opas.auto_coding.default_repository_path', $primaryRepository);

        $machine = $this->app->make(LocalMachineService::class)->recordHeartbeat([
            'machine_key' => 'workspace-agent-default',
            'hostname' => 'workspace-agent-default.local',
            'operating_system' => 'Linux',
            'availability_status' => 'idle',
            'repository_path' => $primaryRepository,
            'capabilities' => [
                'codex' => true,
            ],
            'workspace_bindings' => [
                [
                    'repository_path' => $secondaryRepository,
                    'workspace_path' => $secondaryRepository,
                    'active_branch' => 'feature/workspace-agent-default',
                ],
            ],
        ]);
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $task = $this->app->make(AutoCodingTaskDispatchService::class)->createPendingTaskFromPayload([
            'summary' => 'Claim workspace task without repository path',
            'repository_path' => $secondaryRepository,
            'required_capabilities' => ['codex'],
        ]);

        self::assertSame($machine->id, $task->assigned_machine_id);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/agent/auto-coding/tasks/claim');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Claim workspace task without repository path',
                'repository_path' => $secondaryRepository,
                'status' => 'running',
            ]);
    }

    /**
     * Confirm an agent cannot claim work for repositories outside its machine bindings.
     *
     * @return void
     */
    public function test_authenticated_agent_cannot_claim_task_for_unbound_repository(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machine = $this->app->make(LocalMachineService::class)->resolve(base_path('..'));
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/agent/auto-coding/tasks/claim', [
                'repository_path' => '/srv/workspaces/not-bound',
            ]);

        $response->assertUnprocessable();
    }

    /**
     * Confirm an authenticated machine agent can poll task status only for its own repository scope.
     *
     * @return void
     */
    public function test_authenticated_agent_can_poll_task_status_for_its_repository(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machineService = $this->app->make(LocalMachineService::class);
        $machine = $machineService->resolve(base_path('..'));
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $task = $taskService->createPendingTask('Agent polls status', 'OPAS-0070');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/agent/auto-coding/tasks/'.$task->id.'/status');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Agent polls status',
                'status' => 'pending',
            ]);
    }

    /**
     * Confirm an authenticated machine agent cannot poll task status outside its repository scope.
     *
     * @return void
     */
    public function test_authenticated_agent_cannot_poll_task_status_for_another_repository(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $machineService = $this->app->make(LocalMachineService::class);
        $machine = $machineService->resolve(base_path('..'));
        $token = $this->app->make(AutoCodingAgentAuthService::class)->issueToken($machine);

        $taskService = $this->app->make(LocalAutoCodingTaskService::class);
        $task = $taskService->createPendingTask(
            'Agent cannot read foreign task',
            'OPAS-0070',
            '/tmp/another-repository'
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/agent/auto-coding/tasks/'.$task->id.'/status');

        $response->assertNotFound();
    }

    /**
     * Confirm requests without a valid machine token are rejected from the agent APIs.
     *
     * @return void
     */
    public function test_invalid_agent_token_is_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/agent/auto-coding/tasks/claim');

        $response->assertForbidden();
    }

    /**
     * Confirm requests without a valid machine token are rejected from the agent status API.
     *
     * @return void
     */
    public function test_invalid_agent_token_is_rejected_from_status_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/agent/auto-coding/tasks/1/status');

        $response->assertUnauthorized();
    }
}
