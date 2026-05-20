<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use RuntimeException;

class PromptContextAssembler
{
    /**
     * Build a provider-ready prompt package for the current local coding task.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function assemble(array $context): array
    {
        $promptPath = $this->resolvePromptPath();
        $systemPrompt = @file_get_contents($promptPath);

        if (! is_string($systemPrompt) || trim($systemPrompt) === '') {
            throw new RuntimeException('Unable to load the local auto-coding system prompt.');
        }

        $repositoryContext = $context['repository_context'] ?? [];
        $taskSummary = is_string($context['task_summary'] ?? null) ? $context['task_summary'] : '';
        $issueKey = is_string($context['issue_key'] ?? null) ? $context['issue_key'] : null;

        return [
            'system_prompt' => trim($systemPrompt),
            'user_prompt' => $this->buildUserPrompt($taskSummary, $issueKey, $repositoryContext),
            'goal' => $taskSummary,
            'input_payload' => [
                'issue_key' => $issueKey,
                'repository_context' => $repositoryContext,
            ],
            'available_services' => [
                'laravel',
                'ollama:'.$this->resolveOllamaModel(),
                'local-git',
            ],
            'expected_output' => [
                'workflow_name',
                'steps',
                'risks',
                'validation_rules',
            ],
        ];
    }

    /**
     * Resolve the configured local prompt file path.
     *
     * @return string
     */
    protected function resolvePromptPath(): string
    {
        $promptPath = config('opas.auto_coding.providers.ollama.prompt_path');

        return is_string($promptPath) && $promptPath !== ''
            ? $promptPath
            : base_path('../../ai-local/agents/laravel-n8n-orchestrator.md');
    }

    /**
     * Resolve the configured Ollama model name for prompt metadata.
     *
     * @return string
     */
    protected function resolveOllamaModel(): string
    {
        $model = config('opas.auto_coding.providers.ollama.model', 'qwen2.5:7b');

        return is_string($model) && $model !== '' ? $model : 'qwen2.5:7b';
    }

    /**
     * Build the user prompt content for the current task context.
     *
     * @param  string  $taskSummary
     * @param  string|null  $issueKey
     * @param  mixed  $repositoryContext
     * @return string
     */
    protected function buildUserPrompt(string $taskSummary, ?string $issueKey, mixed $repositoryContext): string
    {
        $normalizedRepositoryContext = is_array($repositoryContext) ? $repositoryContext : [];
        $payload = [
            'task_summary' => $taskSummary,
            'issue_key' => $issueKey,
            'repository_context' => $normalizedRepositoryContext,
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
