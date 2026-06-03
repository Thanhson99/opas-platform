<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\AutoCodingProviderResolver;
use App\Services\AutoCoding\CodexCliAutoCodingProvider;
use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use App\Services\AutoCoding\PromptContextAssembler;
use RuntimeException;
use Tests\TestCase;

class CodexCliAutoCodingProviderTest extends TestCase
{
    /**
     * Confirm the Codex CLI provider delegates one task to non-interactive Codex exec.
     *
     * @return void
     */
    public function test_it_runs_codex_exec_and_returns_a_normalized_response(): void
    {
        $this->configureCodexProvider();
        $runner = new CodexCliFakeCommandRunner([
            'successful' => true,
            'exit_code' => 0,
            'output' => 'Changed Telegram queue controls.',
            'error_output' => '',
        ]);

        $provider = new CodexCliAutoCodingProvider(new PromptContextAssembler, $runner);
        $result = $provider->plan([
            'task_summary' => 'Improve Telegram queue controls',
            'issue_key' => 'OPAS-0069',
            'provider_options' => [
                'model' => 'gpt-5',
            ],
            'repository_context' => [
                'repository_path' => '/tmp/example-repo',
            ],
        ]);

        self::assertSame('completed', $result['status']);
        self::assertSame('codex', $result['provider']);
        self::assertSame('gpt-5', $result['model']);
        self::assertSame('Changed Telegram queue controls.', $result['content']);
        self::assertSame('/tmp/example-repo', $runner->workingDirectory);
        self::assertSame(600, $runner->timeoutSeconds);
        self::assertStringContainsString("'codex'", $runner->command);
        self::assertStringContainsString("'-m' 'gpt-5'", $runner->command);
        self::assertStringContainsString("'-a' 'never'", $runner->command);
        self::assertStringContainsString("'exec'", $runner->command);
        self::assertStringContainsString("'--color' 'never'", $runner->command);
        self::assertStringContainsString("'-s' 'workspace-write'", $runner->command);
        self::assertStringContainsString('Improve Telegram queue controls', $runner->command);
        self::assertIsArray($result['prompt_package']);
    }

    /**
     * Confirm runner exceptions are reported as provider failures.
     *
     * @return void
     */
    public function test_it_returns_failed_response_when_codex_runner_throws(): void
    {
        $this->configureCodexProvider();
        $runner = new CodexCliFakeCommandRunner(exception: new RuntimeException('codex missing'));

        $provider = new CodexCliAutoCodingProvider(new PromptContextAssembler, $runner);
        $result = $provider->plan([
            'task_summary' => 'Review latest change',
            'repository_context' => [
                'repository_path' => '/tmp/example-repo',
            ],
        ]);

        self::assertSame('failed', $result['status']);
        self::assertSame('codex', $result['provider']);
        self::assertSame('codex missing', $result['message']);
    }

    /**
     * Confirm Telegram direct chat does not require the coding system prompt file.
     *
     * @return void
     */
    public function test_it_runs_telegram_direct_chat_without_loading_coding_prompt(): void
    {
        $this->configureCodexProvider();
        config()->set('opas.auto_coding.prompt_path', '/tmp/missing-opas-coding-prompt.md');
        config()->set('opas.auto_coding.providers.ollama.prompt_path', '/tmp/missing-opas-ollama-prompt.md');

        $runner = new CodexCliFakeCommandRunner([
            'successful' => true,
            'exit_code' => 0,
            'output' => '1 + 1 = 2.',
            'error_output' => '',
        ]);

        $provider = new CodexCliAutoCodingProvider(new PromptContextAssembler, $runner);
        $result = $provider->plan([
            'task_summary' => '1 + 1 bằng mấy',
            'provider_options' => [
                'mode' => 'telegram_direct_chat',
            ],
            'repository_context' => [
                'repository_path' => '/tmp/example-repo',
            ],
        ]);

        self::assertSame('completed', $result['status']);
        self::assertSame('1 + 1 = 2.', $result['content']);
        self::assertStringContainsString('You are Codex replying inside a Telegram direct chat session.', $runner->command);
        self::assertStringContainsString('verify with git commands before answering', $runner->command);
        self::assertStringContainsString('git status --short -uall', $runner->command);
        self::assertStringContainsString('1 + 1 bằng mấy', $runner->command);
        self::assertStringNotContainsString('Remote Telegram task payload', $runner->command);
    }

    /**
     * Confirm the provider resolver can resolve the Codex CLI provider key.
     *
     * @return void
     */
    public function test_resolver_returns_codex_provider(): void
    {
        $this->configureCodexProvider();
        $this->app->instance(CommandRunnerInterface::class, new CodexCliFakeCommandRunner);

        $provider = $this->app->make(AutoCodingProviderResolver::class)->resolve('codex');

        self::assertInstanceOf(CodexCliAutoCodingProvider::class, $provider);
        self::assertSame('codex', $provider->name());
    }

    /**
     * Configure a deterministic provider environment for these tests.
     *
     * @return void
     */
    protected function configureCodexProvider(): void
    {
        config()->set('opas.auto_coding.providers.ollama.prompt_path', base_path('../../ai-local/agents/laravel-n8n-orchestrator.md'));
        config()->set('opas.auto_coding.providers.codex.executable', 'codex');
        config()->set('opas.auto_coding.providers.codex.model', null);
        config()->set('opas.auto_coding.providers.codex.approval_mode', 'auto-edit');
        config()->set('opas.auto_coding.providers.codex.sandbox', 'workspace-write');
        config()->set('opas.auto_coding.providers.codex.timeout_seconds', 600);
        config()->set('opas.auto_coding.providers.codex.exec_args', ['--color', 'never', '--skip-git-repo-check']);
    }
}

final class CodexCliFakeCommandRunner implements CommandRunnerInterface
{
    public string $command = '';

    public ?string $workingDirectory = null;

    public ?int $timeoutSeconds = null;

    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        private readonly array $result = [
            'successful' => true,
            'exit_code' => 0,
            'output' => '',
            'error_output' => '',
        ],
        private readonly ?RuntimeException $exception = null,
    ) {}

    /**
     * Record the command instead of executing Codex.
     *
     * @return array<string, mixed>
     */
    public function run(string $command, ?string $workingDirectory = null, ?int $timeoutSeconds = null): array
    {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->timeoutSeconds = $timeoutSeconds;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->result;
    }
}
