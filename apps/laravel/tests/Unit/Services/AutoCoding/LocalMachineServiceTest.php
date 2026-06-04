<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\LocalMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalMachineServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the local machine service can persist one explicit heartbeat payload.
     *
     * @return void
     */
    public function test_it_persists_one_explicit_machine_heartbeat(): void
    {
        $service = $this->app->make(LocalMachineService::class);

        $machine = $service->recordHeartbeat([
            'machine_key' => 'windows-dev-box',
            'hostname' => 'windows-dev-box.local',
            'operating_system' => 'Windows',
            'repository_path' => 'C:\\workspace\\laravel-n8n-automation',
            'metadata' => [
                'editor' => 'vscode',
            ],
        ]);

        self::assertSame('windows-dev-box', $machine->machine_key);
        self::assertSame('windows-dev-box.local', $machine->hostname);
        self::assertSame('Windows', $machine->operating_system);
        self::assertSame('C:\\workspace\\laravel-n8n-automation', $machine->repository_path);
        self::assertSame('vscode', $machine->metadata['editor'] ?? null);
        self::assertNotNull($machine->last_seen_at);
    }

    /**
     * Confirm workspace binding normalization dedupes Windows path variants.
     *
     * @return void
     */
    public function test_it_dedupes_workspace_bindings_across_windows_path_variants(): void
    {
        $service = $this->app->make(LocalMachineService::class);

        $machine = $service->recordHeartbeat([
            'machine_key' => 'windows-binding-worker',
            'hostname' => 'windows-binding.local',
            'operating_system' => 'Windows',
            'repository_path' => 'C:\\Workspaces\\OPAS',
            'workspace_bindings' => [
                [
                    'repository_path' => 'c:/workspaces/opas',
                    'workspace_path' => 'c:/workspaces/opas',
                    'active_branch' => 'feature/windows-path',
                ],
                [
                    'repository_path' => 'C:\\Workspaces\\OPAS',
                    'workspace_path' => 'C:\\Workspaces\\OPAS',
                    'active_branch' => 'duplicate',
                ],
            ],
        ]);

        self::assertCount(1, $machine->workspace_bindings);
        self::assertSame('c:/workspaces/opas', $machine->workspace_bindings[0]['repository_path'] ?? null);
        self::assertSame('feature/windows-path', $machine->workspace_bindings[0]['active_branch'] ?? null);
    }

    /**
     * Confirm resolved local machines include portable resource metadata.
     *
     * @return void
     */
    public function test_it_records_portable_resource_metadata_when_resolving_local_machine(): void
    {
        $service = $this->app->make(LocalMachineService::class);

        $machine = $service->resolve(base_path());

        self::assertIsArray($machine->metadata['resources'] ?? null);
        self::assertArrayHasKey('process_memory_mb', $machine->metadata['resources']);
        self::assertArrayHasKey('process_peak_memory_mb', $machine->metadata['resources']);
        self::assertArrayHasKey('disk_percent', $machine->metadata['resources']);
    }
}
