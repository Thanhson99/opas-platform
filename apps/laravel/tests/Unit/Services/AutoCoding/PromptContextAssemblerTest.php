<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\PromptContextAssembler;
use Tests\TestCase;

class PromptContextAssemblerTest extends TestCase
{
    /**
     * Confirm the prompt assembler loads the orchestrator prompt and builds user context.
     *
     * @return void
     */
    public function test_it_builds_a_prompt_package_from_local_context(): void
    {
        config()->set(
            'opas.auto_coding.providers.ollama.prompt_path',
            base_path('../../ai-local/agents/laravel-n8n-orchestrator.md')
        );

        $assembler = new PromptContextAssembler;
        $package = $assembler->assemble([
            'task_summary' => 'Plan local auto coding task',
            'issue_key' => 'OPAS-0070',
            'repository_context' => [
                'repository_path' => '/tmp/example-repo',
                'branch_name' => 'feature/opas-0070',
            ],
        ]);

        self::assertStringContainsString('orchestration assistant', $package['system_prompt']);
        self::assertStringContainsString('"issue_key": "OPAS-0070"', $package['user_prompt']);
        self::assertSame('Plan local auto coding task', $package['goal']);
    }
}
