<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;

/**
 * Normalize execution and provider context payloads for auto-coding workflow runs.
 */
class AutoCodingExecutionContextService
{
    public function __construct(
        private readonly AutoCodingFollowUpAnswerService $followUpAnswerService,
    ) {}

    /**
     * Build the normalized execution context used to run one pending task.
     *
     * @param  AutoCodingTask  $task
     * @return array{
     *   task_context: array<string, mixed>,
     *   repository_path: string,
     *   should_run_validation: bool,
     *   provider_name: string|null,
     *   provider_options: array<string, mixed>,
     *   follow_up_answers: array<int, array<string, mixed>>,
     *   follow_up_answer_summary: array<string, mixed>,
     *   dirty_workspace_policy: string,
     *   scope_paths: array<int, string>,
     *   scope_policy: string
     * }
     */
    public function buildExecutionContext(AutoCodingTask $task): array
    {
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $providerOptions = is_array($taskContext['provider_options'] ?? null)
            ? $taskContext['provider_options']
            : [];
        $followUpAnswers = is_array($taskContext['follow_up_answers'] ?? null)
            ? $this->followUpAnswerService->normalizeAnswers($taskContext['follow_up_answers'])
            : [];
        $followUpAnswerSummary = $this->followUpAnswerService->buildSummary($followUpAnswers);

        return [
            'task_context' => $taskContext,
            'repository_path' => is_string($taskContext['repository_path'] ?? null)
                ? $taskContext['repository_path']
                : $task->repository_path,
            'should_run_validation' => (bool) ($taskContext['should_run_validation'] ?? false),
            'provider_name' => is_string($taskContext['provider_name'] ?? null)
                ? $taskContext['provider_name']
                : null,
            'provider_options' => $this->normalizeProviderOptions($providerOptions),
            'follow_up_answers' => $followUpAnswers,
            'follow_up_answer_summary' => $followUpAnswerSummary,
            'dirty_workspace_policy' => $this->normalizeDirtyWorkspacePolicy($taskContext['dirty_workspace_policy'] ?? null),
            'scope_paths' => $this->normalizeScopePaths($taskContext['scope_paths'] ?? []),
            'scope_policy' => $this->normalizeScopePolicy($taskContext['scope_policy'] ?? null),
        ];
    }

    /**
     * Build the provider context payload for one local auto-coding execution.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $repositoryContext
     * @param  array<string, mixed>  $providerOptions
     * @param  array<int, array<string, mixed>>  $followUpAnswers
     * @param  array<string, mixed>  $followUpAnswerSummary
     * @return array<string, mixed>
     */
    public function buildProviderContext(
        AutoCodingTask $task,
        array $repositoryContext,
        array $providerOptions,
        array $followUpAnswers,
        array $followUpAnswerSummary,
    ): array {
        return [
            'task_summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'repository_context' => $repositoryContext,
            'provider_options' => $providerOptions,
            'follow_up_answers' => $followUpAnswers,
            'follow_up_answer_summary' => $followUpAnswerSummary,
        ];
    }

    /**
     * Normalize one provider-options payload into a string-keyed array.
     *
     * @param  array<int|string, mixed>  $providerOptions
     * @return array<string, mixed>
     */
    public function normalizeProviderOptions(array $providerOptions): array
    {
        $normalizedOptions = [];

        foreach ($providerOptions as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $normalizedOptions[$key] = $value;
        }

        return $normalizedOptions;
    }

    /**
     * Normalize one mixed dirty-workspace policy into a supported mode.
     *
     * @param  mixed  $policy
     * @return string
     */
    public function normalizeDirtyWorkspacePolicy(mixed $policy): string
    {
        if (! is_string($policy)) {
            return 'warn';
        }

        $normalizedPolicy = trim($policy);

        return in_array($normalizedPolicy, ['warn', 'block', 'allow'], true)
            ? $normalizedPolicy
            : 'warn';
    }

    /**
     * Normalize one mixed scope-path payload into trimmed path prefixes.
     *
     * @param  mixed  $scopePaths
     * @return array<int, string>
     */
    public function normalizeScopePaths(mixed $scopePaths): array
    {
        if (! is_array($scopePaths)) {
            return [];
        }

        $normalizedPaths = [];

        foreach ($scopePaths as $scopePath) {
            if (! is_string($scopePath) || trim($scopePath) === '') {
                continue;
            }

            $normalizedPaths[] = trim($scopePath);
        }

        return array_values(array_unique($normalizedPaths));
    }

    /**
     * Normalize one mixed scope policy into a supported mode.
     *
     * @param  mixed  $policy
     * @return string
     */
    public function normalizeScopePolicy(mixed $policy): string
    {
        if (! is_string($policy)) {
            return 'warn';
        }

        $normalizedPolicy = trim($policy);

        return in_array($normalizedPolicy, ['warn', 'block', 'allow'], true)
            ? $normalizedPolicy
            : 'warn';
    }
}
