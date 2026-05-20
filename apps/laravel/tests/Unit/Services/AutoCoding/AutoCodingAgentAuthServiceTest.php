<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Services\AutoCoding\AutoCodingAgentAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingAgentAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the agent auth service can issue and authenticate one machine token.
     *
     * @return void
     */
    public function test_it_issues_and_authenticates_one_machine_token(): void
    {
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'mac-main',
            'hostname' => 'mac-main.local',
            'operating_system' => 'Darwin',
            'repository_path' => '/Users/hopee/Downloads/laravel-n8n-automation',
        ]);

        $service = $this->app->make(AutoCodingAgentAuthService::class);
        $token = $service->issueToken($machine);
        $authenticatedMachine = $service->authenticate($token);

        self::assertNotEmpty($token);
        self::assertNotNull($authenticatedMachine);
        self::assertSame($machine->id, $authenticatedMachine->id);
        self::assertNotNull($authenticatedMachine->access_token_last_used_at);
    }
}
