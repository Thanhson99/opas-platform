<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\AutoCodingMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAutoCodingAgentTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the agent-token issue command can generate one machine token.
     *
     * @return void
     */
    public function test_it_issues_one_agent_token_for_the_local_machine(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->artisan('opas:auto-coding:issue-token', [
            '--path' => base_path('..'),
        ])->assertExitCode(0);

        $machine = AutoCodingMachine::query()->first();

        self::assertNotNull($machine);
        self::assertNotNull($machine->access_token_hash);
    }
}
