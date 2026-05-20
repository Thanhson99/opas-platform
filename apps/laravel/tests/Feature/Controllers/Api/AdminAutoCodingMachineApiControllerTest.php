<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AutoCodingMachine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAutoCodingMachineApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm an authenticated admin can list local auto-coding machines.
     *
     * @return void
     */
    public function test_admin_can_list_local_auto_coding_machines(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/machines');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'online',
            ]);
    }

    /**
     * Confirm an authenticated admin can inspect one local auto-coding machine.
     *
     * @return void
     */
    public function test_admin_can_show_one_local_auto_coding_machine(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/machines/1');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'online',
            ]);
    }

    /**
     * Confirm an authenticated admin can store one local auto-coding machine heartbeat.
     *
     * @return void
     */
    public function test_admin_can_store_one_local_auto_coding_machine_heartbeat(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/admin/auto-coding/machines/heartbeat', [
            'machine_key' => 'mac-studio-main',
            'hostname' => 'mac-studio.local',
            'operating_system' => 'Darwin',
            'repository_path' => '/Users/hopee/Downloads/laravel-n8n-automation',
            'metadata' => [
                'editor' => 'vscode',
                'runtime' => 'codex',
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonFragment([
                'machine_key' => 'mac-studio-main',
                'hostname' => 'mac-studio.local',
                'operating_system' => 'Darwin',
                'status' => 'online',
            ]);

        $machine = AutoCodingMachine::query()->where('machine_key', 'mac-studio-main')->first();

        self::assertNotNull($machine);
        self::assertSame('/Users/hopee/Downloads/laravel-n8n-automation', $machine->repository_path);
        self::assertSame('vscode', $machine->metadata['editor'] ?? null);
    }

    /**
     * Confirm non-admin users cannot access the local auto-coding machine APIs.
     *
     * @return void
     */
    public function test_non_admin_cannot_list_local_auto_coding_machines(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->getJson('/api/admin/auto-coding/machines');

        $response->assertForbidden();
    }

    /**
     * Confirm non-admin users cannot store one local auto-coding machine heartbeat.
     *
     * @return void
     */
    public function test_non_admin_cannot_store_local_auto_coding_machine_heartbeat(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this->actingAs($member)->postJson('/api/admin/auto-coding/machines/heartbeat', [
            'machine_key' => 'mac-studio-main',
            'hostname' => 'mac-studio.local',
            'operating_system' => 'Darwin',
        ]);

        $response->assertForbidden();
    }
}
