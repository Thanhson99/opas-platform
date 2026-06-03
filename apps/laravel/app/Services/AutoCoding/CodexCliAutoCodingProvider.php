<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\AutoCodingProviderInterface;
use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use Throwable;

class CodexCliAutoCodingProvider implements AutoCodingProviderInterface
{
    public function __construct(
        private readonly PromptContextAssembler $promptContextAssembler,
        private readonly CommandRunnerInterface $commandRunner,
    ) {}

    /**
     * Return the internal provider key used for reporting.
     *
     * @return string
     */
    public function name(): string
    {
        return 'codex';
    }

    /**
     * Prepare a provider response by delegating one task to Codex CLI.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function plan(array $context): array
    {
        $promptPackage = $this->isTelegramDirectChatContext($context)
            ? $this->buildDirectChatPromptPackage($context)
            : $this->promptContextAssembler->assemble($context);
        $repositoryPath = $this->resolveRepositoryPath($context);
        $model = $this->resolveModel($context);
        $command = $this->buildCommand($promptPackage, $model, $context);

        try {
            $result = $this->commandRunner->run($command, $repositoryPath, $this->resolveTimeoutSeconds());

            return [
                'status' => $result['successful'] ? 'completed' : 'failed',
                'provider' => $this->name(),
                'model' => $model,
                'command' => $command,
                'repository_path' => $repositoryPath,
                'exit_code' => $result['exit_code'],
                'content' => $result['output'],
                'error_output' => $result['error_output'],
                'prompt_package' => $promptPackage,
            ];
        } catch (Throwable $throwable) {
            return [
                'status' => 'failed',
                'provider' => $this->name(),
                'model' => $model,
                'command' => $command,
                'repository_path' => $repositoryPath,
                'message' => $throwable->getMessage(),
                'prompt_package' => $promptPackage,
            ];
        }
    }

    /**
     * Build the Codex CLI command used for one non-interactive task.
     *
     * @param  array<string, mixed>  $promptPackage
     * @param  string|null  $model
     * @param  array<string, mixed>  $context
     * @return string
     */
    protected function buildCommand(array $promptPackage, ?string $model, array $context): string
    {
        $parts = [
            $this->resolveExecutable(),
            ...$this->resolvePrefixArguments($model),
            'exec',
            ...$this->resolveExecArguments(),
            $this->buildPrompt($promptPackage, $context),
        ];

        return implode(' ', array_map(
            static fn (string $part): string => escapeshellarg($part),
            array_values(array_filter($parts, static fn (string $part): bool => trim($part) !== ''))
        ));
    }

    /**
     * Build the full prompt sent to Codex CLI.
     *
     * @param  array<string, mixed>  $promptPackage
     * @param  array<string, mixed>  $context
     * @return string
     */
    protected function buildPrompt(array $promptPackage, array $context): string
    {
        if ($this->isTelegramDirectChatContext($context)) {
            return $this->buildDirectChatPrompt($context);
        }

        $systemPrompt = is_string($promptPackage['system_prompt'] ?? null)
            ? trim((string) $promptPackage['system_prompt'])
            : '';
        $userPrompt = is_string($promptPackage['user_prompt'] ?? null)
            ? trim((string) $promptPackage['user_prompt'])
            : '';

        return trim(implode("\n\n", array_filter([
            $systemPrompt,
            'Remote Telegram task payload:',
            $userPrompt,
            'Return a concise completion summary, changed files, risks, and validation notes.',
        ], static fn (string $part): bool => trim($part) !== '')));
    }

    /**
     * Build a conversational prompt for Telegram direct chat messages.
     *
     * @param  array<string, mixed>  $context
     * @return string
     */
    protected function buildDirectChatPrompt(array $context): string
    {
        $message = is_string($context['task_summary'] ?? null) ? trim((string) $context['task_summary']) : '';

        return trim(implode("\n\n", array_filter([
            'You are Codex replying inside a Telegram direct chat session.',
            'Answer the user directly and concisely in the same language as their message.',
            'Do not edit files, run validation, create a coding report, mention task ids, or show queue/task lifecycle details.',
            'You may run read-only inspection commands when the user asks about repository state.',
            'For repository facts such as remotes, branch, dirty files, or changed-file counts, verify with git commands before answering.',
            'When counting changed files, use git status --short -uall and count file paths, including files inside untracked directories.',
            'If you include command output, keep it as plain text or a complete fenced block; never leave an unclosed code fence.',
            'If the user asks for a code change, answer normally in chat unless they explicitly ask to create a coding task.',
            'User message:',
            $message,
        ], static fn (string $part): bool => trim($part) !== '')));
    }

    /**
     * Build a lightweight prompt package for Telegram direct chat.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildDirectChatPromptPackage(array $context): array
    {
        $message = is_string($context['task_summary'] ?? null) ? trim((string) $context['task_summary']) : '';

        return [
            'system_prompt' => '',
            'user_prompt' => $message,
            'goal' => $message,
            'input_payload' => [
                'message' => $message,
                'mode' => 'telegram_direct_chat',
            ],
            'available_services' => [
                'codex',
            ],
            'expected_output' => [
                'direct_answer',
            ],
        ];
    }

    /**
     * Determine whether this provider run should produce a chat reply instead of a coding report.
     *
     * @param  array<string, mixed>  $context
     * @return bool
     */
    protected function isTelegramDirectChatContext(array $context): bool
    {
        $providerOptions = is_array($context['provider_options'] ?? null) ? $context['provider_options'] : [];

        return ($providerOptions['mode'] ?? null) === 'telegram_direct_chat';
    }

    /**
     * Resolve the Codex CLI executable path.
     *
     * @return string
     */
    protected function resolveExecutable(): string
    {
        $executable = config('opas.auto_coding.providers.codex.executable');

        return is_string($executable) && trim($executable) !== '' ? trim($executable) : '';
    }

    /**
     * Resolve prefix arguments placed before the `exec` subcommand.
     *
     * @param  string|null  $model
     * @return array<int, string>
     */
    protected function resolvePrefixArguments(?string $model): array
    {
        $arguments = [];

        if (is_string($model) && trim($model) !== '') {
            $arguments[] = '-m';
            $arguments[] = trim($model);
        }

        $arguments = [
            ...$arguments,
            ...$this->resolveApprovalArguments(),
        ];

        return $arguments;
    }

    /**
     * Resolve exec-subcommand arguments.
     *
     * @return array<int, string>
     */
    protected function resolveExecArguments(): array
    {
        $arguments = $this->normalizeArgumentList(config('opas.auto_coding.providers.codex.exec_args'));

        $sandbox = config('opas.auto_coding.providers.codex.sandbox');

        if (is_string($sandbox) && trim($sandbox) !== '') {
            $arguments[] = '-s';
            $arguments[] = trim($sandbox);
        }

        return $arguments;
    }

    /**
     * Resolve the configured approval mode into Codex CLI arguments.
     *
     * @return array<int, string>
     */
    protected function resolveApprovalArguments(): array
    {
        $mode = config('opas.auto_coding.providers.codex.approval_mode');
        $normalizedMode = is_string($mode) ? trim(strtolower($mode)) : '';

        return match ($normalizedMode) {
            'suggest', 'on-request', 'on_request' => ['-a', 'on-request'],
            'auto-edit', 'auto_edit', 'never' => ['-a', 'never'],
            'full-auto', 'full_auto' => ['--dangerously-bypass-approvals-and-sandbox'],
            'untrusted' => ['-a', 'untrusted'],
            default => [],
        };
    }

    /**
     * Resolve the configured Codex model.
     *
     * @param  array<string, mixed>  $context
     * @return string|null
     */
    protected function resolveModel(array $context): ?string
    {
        $providerOptions = is_array($context['provider_options'] ?? null) ? $context['provider_options'] : [];
        $overrideModel = is_string($providerOptions['model'] ?? null) ? trim((string) $providerOptions['model']) : '';

        if ($overrideModel !== '') {
            return $overrideModel;
        }

        $model = config('opas.auto_coding.providers.codex.model');

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    /**
     * Resolve the task repository path.
     *
     * @param  array<string, mixed>  $context
     * @return string|null
     */
    protected function resolveRepositoryPath(array $context): ?string
    {
        $repositoryContext = is_array($context['repository_context'] ?? null) ? $context['repository_context'] : [];
        $repositoryPath = is_string($repositoryContext['repository_path'] ?? null)
            ? trim((string) $repositoryContext['repository_path'])
            : '';

        return $repositoryPath !== '' ? $repositoryPath : null;
    }

    /**
     * Resolve the configured process timeout in seconds.
     *
     * @return int
     */
    protected function resolveTimeoutSeconds(): int
    {
        $timeout = config('opas.auto_coding.providers.codex.timeout_seconds');

        return is_numeric($timeout) && (int) $timeout > 0 ? (int) $timeout : 0;
    }

    /**
     * Normalize one argument-list config value.
     *
     * @param  mixed  $value
     * @return array<int, string>
     */
    protected function normalizeArgumentList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value)) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $argument): string => is_string($argument) ? trim($argument) : '',
            $value
        ), static fn (string $argument): bool => $argument !== ''));
    }
}
