<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;

class AutoCodingTaskDispatchService
{
    public function __construct(
        private readonly LocalAutoCodingTaskService $localAutoCodingTaskService,
        private readonly AutoCodingTaskQueryService $taskQueryService,
    ) {}

    /**
     * Create one pending local auto-coding task from a normalized payload.
     *
     * @param  array{
     *   summary:string,
     *   issue_key?:string,
     *   repository_path?:string,
     *   validate?:bool,
     *   provider?:string,
     *   provider_options?:array<string, mixed>,
     *   dirty_workspace_policy?:string,
     *   scope_paths?:array<int, string>,
     *   scope_policy?:string
     * }  $payload
     * @return AutoCodingTask
     */
    public function createPendingTaskFromPayload(array $payload): AutoCodingTask
    {
        return $this->localAutoCodingTaskService->createPendingTask(
            $payload['summary'],
            $this->normalizeOptionalString($payload['issue_key'] ?? null),
            $this->normalizeOptionalString($payload['repository_path'] ?? null),
            (bool) ($payload['validate'] ?? false),
            $this->normalizeOptionalString($payload['provider'] ?? null),
            $this->normalizeProviderOptions($payload['provider_options'] ?? []),
            $this->normalizeDirtyWorkspacePolicy($payload['dirty_workspace_policy'] ?? null),
            $this->normalizeScopePaths($payload['scope_paths'] ?? []),
            $this->normalizeScopePolicy($payload['scope_policy'] ?? null),
        );
    }

    /**
     * Claim the next pending task for one repository and optionally execute it.
     *
     * @param  string|null  $repositoryPath
     * @param  bool  $shouldExecute
     * @return AutoCodingTask|null
     */
    public function claimAndOptionallyExecute(?string $repositoryPath, bool $shouldExecute): ?AutoCodingTask
    {
        $task = $this->localAutoCodingTaskService->claimNextPendingTask($repositoryPath);

        if (! $task instanceof AutoCodingTask) {
            return null;
        }

        if ($shouldExecute) {
            $this->localAutoCodingTaskService->executePendingTask($task->id);

            return $this->taskQueryService->findDetailedById($task->id);
        }

        return $task;
    }

    /**
     * Resume one blocked local auto-coding task with additional follow-up input.
     *
     * @param  int  $taskId
     * @param  string  $response
     * @param  string  $resumeToken
     * @param  array<string, mixed>|null  $responsePayload
     * @return AutoCodingTask
     */
    public function resumeBlockedTask(
        int $taskId,
        string $response,
        string $resumeToken,
        ?array $responsePayload = null,
    ): AutoCodingTask {
        $this->localAutoCodingTaskService->resumeBlockedTask($taskId, $response, $resumeToken, $responsePayload);

        /** @var AutoCodingTask $task */
        $task = $this->taskQueryService->findDetailedById($taskId);

        return $task;
    }

    /**
     * Normalize one optional string field from a transport payload.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    /**
     * Normalize one provider-options payload into a string-keyed array.
     *
     * @param  mixed  $providerOptions
     * @return array<string, mixed>
     */
    protected function normalizeProviderOptions(mixed $providerOptions): array
    {
        if (! is_array($providerOptions)) {
            return [];
        }

        $normalizedOptions = [];

        foreach ($providerOptions as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $normalizedOptions[trim($key)] = $value;
        }

        return $normalizedOptions;
    }

    /**
     * Normalize one optional dirty-workspace policy value.
     *
     * @param  mixed  $policy
     * @return string
     */
    protected function normalizeDirtyWorkspacePolicy(mixed $policy): string
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
     * Normalize one changed-file scope list into trimmed path prefixes.
     *
     * @param  mixed  $scopePaths
     * @return array<int, string>
     */
    protected function normalizeScopePaths(mixed $scopePaths): array
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
     * Normalize one optional changed-file scope policy value.
     *
     * @param  mixed  $policy
     * @return string
     */
    protected function normalizeScopePolicy(mixed $policy): string
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
