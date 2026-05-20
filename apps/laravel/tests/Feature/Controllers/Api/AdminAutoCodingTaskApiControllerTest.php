<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AutoCodingTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutoCodingTaskApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated admin can list local auto-coding tasks.
     *
     * @return void
     */
    public function test_admin_can_list_local_auto_coding_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Inspect local auto coding foundation',
                'issue_key' => 'OPAS-0070',
            ]);
    }

    /**
     * Confirm an authenticated admin can inspect one detailed local auto-coding task.
     *
     * @return void
     */
    public function test_admin_can_show_one_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/tasks/1');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Inspect local auto coding foundation',
            ]);
    }

    /**
     * Confirm an authenticated admin can create and enqueue a local auto-coding task over the API.
     *
     * @return void
     */
    public function test_admin_can_create_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks', [
            'summary' => 'Create local auto coding task from API',
            'issue_key' => 'OPAS-0070',
            'validate' => false,
            'provider' => 'null',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonFragment([
                'summary' => 'Create local auto coding task from API',
                'issue_key' => 'OPAS-0070',
                'status' => 'pending',
            ]);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('pending', $task->status->value);
        self::assertSame('queued', $task->latest_report['queue']['status'] ?? null);
    }

    /**
     * Confirm an authenticated admin can claim the next pending local auto-coding task.
     *
     * @return void
     */
    public function test_admin_can_claim_the_next_pending_local_auto_coding_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks', [
            'summary' => 'Claim local auto coding task from API',
            'issue_key' => 'OPAS-0070',
            'validate' => false,
            'provider' => 'null',
        ])->assertAccepted();

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/tasks/claim');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'summary' => 'Claim local auto coding task from API',
                'issue_key' => 'OPAS-0070',
                'status' => 'running',
            ]);
    }

    /**
     * Confirm non-admin users cannot access the local auto-coding admin APIs.
     *
     * @return void
     */
    public function test_non_admin_cannot_list_local_auto_coding_tasks(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->getJson('/api/admin/auto-coding/tasks');

        $response->assertForbidden();
    }
}
