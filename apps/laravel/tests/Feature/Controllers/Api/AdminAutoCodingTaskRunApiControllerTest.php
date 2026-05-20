<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutoCodingTaskRunApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated admin can inspect one detailed local auto-coding task run.
     *
     * @return void
     */
    public function test_admin_can_show_one_local_auto_coding_task_run(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect run detail contract',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/runs/1');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => 1,
                'status' => 'completed',
                'summary' => 'Inspect run detail contract',
                'issue_key' => 'OPAS-0070',
            ]);
    }

    /**
     * Confirm an authenticated admin can list artifacts for one local auto-coding task run.
     *
     * @return void
     */
    public function test_admin_can_list_local_auto_coding_task_run_artifacts(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect run artifact contract',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/runs/1/artifacts');

        $response
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonFragment([
                'task_run_id' => 1,
                'type' => 'final_report',
            ]);
    }

    /**
     * Confirm non-admin users cannot inspect the local auto-coding task run APIs.
     *
     * @return void
     */
    public function test_non_admin_cannot_show_local_auto_coding_task_run(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->getJson('/api/admin/auto-coding/runs/1');

        $response->assertForbidden();
    }
}
