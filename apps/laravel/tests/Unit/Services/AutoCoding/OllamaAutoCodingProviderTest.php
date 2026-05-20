<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\OllamaAutoCodingProvider;
use App\Services\AutoCoding\PromptContextAssembler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaAutoCodingProviderTest extends TestCase
{
    /**
     * Confirm the Ollama provider returns a normalized completed response.
     *
     * @return void
     */
    public function test_it_calls_ollama_and_returns_a_normalized_response(): void
    {
        config()->set('opas.auto_coding.providers.ollama.base_url', 'http://127.0.0.1:11434');
        config()->set('opas.auto_coding.providers.ollama.model', 'qwen2.5:7b');
        config()->set(
            'opas.auto_coding.providers.ollama.prompt_path',
            base_path('../../ai-local/agents/laravel-n8n-orchestrator.md')
        );

        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'message' => [
                    'content' => '{"workflow_name":"local-auto-coding"}',
                ],
            ], 200),
        ]);

        $provider = new OllamaAutoCodingProvider(new PromptContextAssembler);
        $result = $provider->plan([
            'task_summary' => 'Plan local auto coding task',
            'issue_key' => 'OPAS-0070',
            'repository_context' => [
                'repository_path' => '/tmp/example-repo',
            ],
        ]);

        self::assertSame('completed', $result['status']);
        self::assertSame('ollama', $result['provider']);
        self::assertSame('qwen2.5:7b', $result['model']);
        self::assertSame('{"workflow_name":"local-auto-coding"}', $result['content']);
        self::assertIsArray($result['prompt_package']);
    }
}
