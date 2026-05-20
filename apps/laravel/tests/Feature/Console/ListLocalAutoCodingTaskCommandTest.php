<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListLocalAutoCodingTaskCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the list command returns compact summaries for recent local auto-coding tasks.
     *
     * @return void
     */
    public function test_it_lists_recent_local_auto_coding_tasks(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        $this->artisan('opas:auto-coding:run', [
            'summary' => 'Inspect local auto coding foundation',
            '--issue' => 'OPAS-0070',
        ])->assertExitCode(0);

        $this->artisan('opas:auto-coding:list', [
            '--limit' => '5',
        ])
            ->expectsOutputToContain('"summary": "Inspect local auto coding foundation"')
            ->assertExitCode(0);
    }
}
