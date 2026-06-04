<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;

class AutoCodingTaskDispatchService
{
    public function __construct(
        private readonly LocalAutoCodingTaskService $localAutoCodingTaskService,
        private readonly AutoCodingTaskQueryService $taskQueryService,
        private readonly AutoCodingMachineRoutingService $machineRoutingService,
    ) {}

    /**
     * Create one pending local auto-coding task from a normalized payload.
     *
     * @param  array{
     *   summary:string,
     *   issue_key?:string,
     *   repository_path?:string,
     *   preferred_machine_key?:string,
     *   required_capabilities?:array<int, string>,
     *   validate?:bool,
     *   provider?:string,
     *   provider_options?:array<string, mixed>,
     *   dirty_workspace_policy?:string,
     *   scope_paths?:array<int, string>,
     *   scope_policy?:string,
     *   context_metadata?:array<string, mixed>
     * }  $payload
     * @return AutoCodingTask
     */
    public function createPendingTaskFromPayload(array $payload): AutoCodingTask
    {
        $task = $this->localAutoCodingTaskService->createPendingTask(
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

        $task = $this->machineRoutingService->routePendingTask(
            $task,
            $this->normalizeRequiredCapabilities($payload['required_capabilities'] ?? []),
            $this->normalizeOptionalString($payload['preferred_machine_key'] ?? null),
        );

        $contextMetadata = $this->normalizeContextMetadata($payload['context_metadata'] ?? []);

        if ($contextMetadata === []) {
            return $task;
        }

        $task->update([
            'context_payload' => array_merge(
                is_array($task->context_payload) ? $task->context_payload : [],
                $contextMetadata,
            ),
        ]);

        /** @var AutoCodingTask $freshTask */
        $freshTask = $task->fresh();

        return $freshTask;
    }

    /**
     * Claim the next pending task for one repository and optionally execute it.
     *
     * @param  string|null  $repositoryPath
     * @param  bool  $shouldExecute
     * @param  AutoCodingMachine|null  $machine
     * @return AutoCodingTask|null
     */
    public function claimAndOptionallyExecute(
        ?string $repositoryPath,
        bool $shouldExecute,
        ?AutoCodingMachine $machine = null,
    ): ?AutoCodingTask {
        if ($machine instanceof AutoCodingMachine && ! $this->machineRoutingService->canClaimNewTask($machine)) {
            return null;
        }

        $task = $this->localAutoCodingTaskService->claimNextPendingTask($repositoryPath, $machine);

        if (! $task instanceof AutoCodingTask) {
            return null;
        }

        if ($shouldExecute) {
            $this->localAutoCodingTaskService->executePendingTask($task->id, $machine);

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
     * Cancel one local auto-coding task by id.
     *
     * @param  int  $taskId
     * @return AutoCodingTask
     */
    public function cancelTask(int $taskId): AutoCodingTask
    {
        return $this->localAutoCodingTaskService->cancelTask($taskId);
    }

    /**
     * Cancel all active local auto-coding tasks.
     *
     * @return array{cancelled_count:int,cancellation_requested_count:int,unchanged_count:int}
     */
    public function cancelActiveTasks(): array
    {
        return $this->localAutoCodingTaskService->cancelActiveTasks();
    }

    /**
     * Permanently delete one pending local auto-coding task by id.
     *
     * @param  int  $taskId
     * @return array{id:int,summary:string}
     */
    public function deletePendingTask(int $taskId): array
    {
        return $this->localAutoCodingTaskService->deletePendingTask($taskId);
    }

    /**
     * Permanently delete all pending local auto-coding tasks.
     *
     * @return array{deleted_count:int,scope:string}
     */
    public function deletePendingTasks(): array
    {
        return $this->localAutoCodingTaskService->deletePendingTasks();
    }

    /**
     * Purge task history from persistence.
     *
     * @param  string  $scope
     * @return array{deleted_count:int,scope:string}
     */
    public function purgeTasks(string $scope = 'terminal'): array
    {
        return $this->localAutoCodingTaskService->purgeTasks($scope);
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

    /**
     * Normalize required machine capability names from a transport payload.
     *
     * @param  mixed  $requiredCapabilities
     * @return array<int, string>
     */
    protected function normalizeRequiredCapabilities(mixed $requiredCapabilities): array
    {
        if (! is_array($requiredCapabilities)) {
            return [];
        }

        $capabilities = [];

        foreach ($requiredCapabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                continue;
            }

            $capabilities[] = strtolower(trim($capability));
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Normalize one transport-level context metadata payload.
     *
     * @param  mixed  $contextMetadata
     * @return array<string, mixed>
     */
    protected function normalizeContextMetadata(mixed $contextMetadata): array
    {
        if (! is_array($contextMetadata)) {
            return [];
        }

        $normalizedMetadata = [];

        foreach ($contextMetadata as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $normalizedMetadata[trim($key)] = $value;
        }

        return $normalizedMetadata;
    }
}
