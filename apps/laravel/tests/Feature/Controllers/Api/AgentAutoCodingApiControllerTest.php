<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Services\AutoCoding\AutoCodingAgentAuthService;
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
                'repository_path' => base_path('..'),
                'metadata' => [
                    'editor' => 'vscode',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'machine_key' => $machine->machine_key,
                'repository_path' => base_path('..'),
            ]);
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
