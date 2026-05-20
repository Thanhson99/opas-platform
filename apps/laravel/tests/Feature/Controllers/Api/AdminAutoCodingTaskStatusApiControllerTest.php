<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AutoCodingTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutoCodingTaskStatusApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated admin can poll one compact local auto-coding task status payload.
     *
     * @return void
     */
    public function test_admin_can_poll_one_local_auto_coding_task_status(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/'.$task->getKey().'/status');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Inspect local auto coding foundation',
                'issue_key' => 'OPAS-0070',
                'status' => 'completed',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'summary',
                    'issue_key',
                    'status',
                    'latest_run' => [
                        'id',
                        'machine_id',
                        'status',
                        'artifact_count',
                    ],
                    'machine' => [
                        'id',
                        'machine_key',
                        'hostname',
                        'operating_system',
                    ],
                    'progress' => [
                        'artifact_count',
                        'validation_status',
                        'provider_status',
                    ],
                ],
            ]);
    }

    /**
     * Confirm non-admin users cannot poll the local auto-coding task status API.
     *
     * @return void
     */
    public function test_non_admin_cannot_poll_local_auto_coding_task_status(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->getJson('/api/admin/auto-coding/tasks/1/status');

        $response->assertForbidden();
    }
}
