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
     *   provider_options?:array<string, mixed>
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
}
