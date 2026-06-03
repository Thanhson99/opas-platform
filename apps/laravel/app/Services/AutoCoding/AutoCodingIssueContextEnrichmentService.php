<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;

/**
 * Enrich issue-linked task payloads with reusable local GitHub context.
 */
class AutoCodingIssueContextEnrichmentService
{
    public function __construct(
        private readonly AutoCodingGitHubStatusQueryService $gitHubStatusQueryService,
        private readonly AutoCodingTaskQueryService $taskQueryService,
        private readonly GitHubContextService $gitHubContextService,
    ) {}

    /**
     * Resolve one issue-linked payload into either a ready payload or a clarification prompt.
     *
     * @param  array<string, mixed>  $taskPayload
     * @return array{task_payload: array<string, mixed>, clarification?: array<string, mixed>}
     */
    public function resolveTaskPayload(array $taskPayload): array
    {
        $issueKey = $this->normalizeOptionalString($taskPayload['issue_key'] ?? null);

        if ($issueKey === null) {
            return [
                'task_payload' => $taskPayload,
            ];
        }

        $taskType = $this->resolveTaskType($taskPayload);
        $issueTasks = $this->taskQueryService->getLatest(10, null, $issueKey);
        $sameTypeIssueTasks = $this->filterIssueTasksByType($issueTasks, $taskType);
        $selectedSourceTaskId = $this->resolveSelectedSourceTaskId($taskPayload);
        $clarification = $selectedSourceTaskId === null
            ? $this->resolveIssueContextClarification($taskPayload, $issueKey, $taskType, $sameTypeIssueTasks)
            : null;

        if ($clarification !== null) {
            return [
                'task_payload' => $taskPayload,
                'clarification' => $clarification,
            ];
        }

        $preferredIssueTask = $this->resolvePreferredIssueTask(
            $issueTasks,
            $sameTypeIssueTasks,
            $taskType,
            $selectedSourceTaskId
        );
        $issueContext = $this->buildIssueContext($issueKey, $taskPayload, $preferredIssueTask);
        $summary = $this->resolveEnrichedSummary(
            $this->normalizeOptionalString($taskPayload['summary'] ?? null),
            $issueKey,
            $preferredIssueTask
        );
        $reusedFields = [];

        if ($summary !== null) {
            $taskPayload['summary'] = $summary;
        }

        if ($preferredIssueTask instanceof AutoCodingTask) {
            [$taskPayload, $reusedFields] = $this->inheritExecutionHints($taskPayload, $preferredIssueTask, $taskType);
        }

        $existingMetadata = is_array($taskPayload['context_metadata'] ?? null)
            ? $taskPayload['context_metadata']
            : [];
        $existingIssueEnrichment = is_array($existingMetadata['issue_enrichment'] ?? null)
            ? $existingMetadata['issue_enrichment']
            : [];
        $taskPayload['context_metadata'] = array_merge($existingMetadata, [
            'issue_context' => $issueContext,
            'issue_enrichment' => array_merge($existingIssueEnrichment, [
                'task_type' => $taskType,
                'reused_fields' => $reusedFields,
            ]),
        ]);

        return [
            'task_payload' => $taskPayload,
        ];
    }

    /**
     * Enrich one normalized task payload with issue-derived context when possible.
     *
     * @param  array<string, mixed>  $taskPayload
     * @return array<string, mixed>
     */
    public function enrichTaskPayload(array $taskPayload): array
    {
        return $this->resolveTaskPayload($taskPayload)['task_payload'];
    }

    /**
     * Resolve the best reusable issue task for the requested task type.
     *
     * Prefer the latest task of the same inferred type, then fall back to the
     * latest issue-linked task overall so Phase 3 stays local-first.
     *
     * @param  array<int, AutoCodingTask>  $issueTasks
     * @param  array<int, AutoCodingTask>  $sameTypeIssueTasks
     * @param  string  $taskType
     * @param  int|null  $selectedSourceTaskId
     * @return AutoCodingTask|null
     */
    protected function resolvePreferredIssueTask(
        array $issueTasks,
        array $sameTypeIssueTasks,
        string $taskType,
        ?int $selectedSourceTaskId = null,
    ): ?AutoCodingTask {
        if ($selectedSourceTaskId !== null) {
            foreach ($sameTypeIssueTasks as $issueTask) {
                if ($issueTask->getKey() === $selectedSourceTaskId) {
                    return $issueTask;
                }
            }

            foreach ($issueTasks as $issueTask) {
                if ($issueTask->getKey() === $selectedSourceTaskId) {
                    return $issueTask;
                }
            }
        }

        foreach ($sameTypeIssueTasks as $issueTask) {
            if ($this->inferExistingTaskType($issueTask) === $taskType) {
                return $issueTask;
            }
        }

        return $issueTasks[0] ?? null;
    }

    /**
     * Filter one issue-task list down to the entries that match the requested task type.
     *
     * @param  array<int, AutoCodingTask>  $issueTasks
     * @param  string  $taskType
     * @return array<int, AutoCodingTask>
     */
    protected function filterIssueTasksByType(array $issueTasks, string $taskType): array
    {
        return array_values(array_filter(
            $issueTasks,
            fn (AutoCodingTask $issueTask): bool => $this->inferExistingTaskType($issueTask) === $taskType
        ));
    }

    /**
     * Resolve one optional source-task selection carried from Telegram clarification flow.
     *
     * @param  array<string, mixed>  $taskPayload
     * @return int|null
     */
    protected function resolveSelectedSourceTaskId(array $taskPayload): ?int
    {
        $metadata = is_array($taskPayload['context_metadata'] ?? null) ? $taskPayload['context_metadata'] : [];
        $issueEnrichment = is_array($metadata['issue_enrichment'] ?? null) ? $metadata['issue_enrichment'] : [];
        $selectedSourceTaskId = $issueEnrichment['selected_source_task_id'] ?? null;

        return is_numeric($selectedSourceTaskId) && (int) $selectedSourceTaskId > 0
            ? (int) $selectedSourceTaskId
            : null;
    }

    /**
     * Determine whether one issue-linked request needs a clarification step before reuse.
     *
     * Ask the operator only when multiple same-type candidates would contribute
     * different reusable hints for the current payload.
     *
     * @param  array<string, mixed>  $taskPayload
     * @param  string  $issueKey
     * @param  string  $taskType
     * @param  array<int, AutoCodingTask>  $sameTypeIssueTasks
     * @return array<string, mixed>|null
     */
    protected function resolveIssueContextClarification(
        array $taskPayload,
        string $issueKey,
        string $taskType,
        array $sameTypeIssueTasks,
    ): ?array {
        if (count($sameTypeIssueTasks) < 2) {
            return null;
        }

        $uniqueCandidates = [];

        foreach ($sameTypeIssueTasks as $issueTask) {
            $signature = $this->buildReusableHintSignature($taskPayload, $issueKey, $taskType, $issueTask);

            if ($signature === []) {
                continue;
            }

            $signatureKey = json_encode($signature);

            if (! is_string($signatureKey) || array_key_exists($signatureKey, $uniqueCandidates)) {
                continue;
            }

            $uniqueCandidates[$signatureKey] = $this->buildClarificationCandidate($issueTask, $signature);
        }

        if (count($uniqueCandidates) < 2) {
            return null;
        }

        return [
            'type' => 'issue_context',
            'issue_key' => $issueKey,
            'task_type' => $taskType,
            'candidates' => array_slice(array_values($uniqueCandidates), 0, 3),
        ];
    }

    /**
     * Build one comparable signature of the reusable hints contributed by one issue task.
     *
     * @param  array<string, mixed>  $taskPayload
     * @param  string  $issueKey
     * @param  string  $taskType
     * @param  AutoCodingTask  $issueTask
     * @return array<string, mixed>
     */
    protected function buildReusableHintSignature(
        array $taskPayload,
        string $issueKey,
        string $taskType,
        AutoCodingTask $issueTask,
    ): array {
        $taskContext = is_array($issueTask->context_payload) ? $issueTask->context_payload : [];
        $signature = [];

        if ($this->shouldReplaceSummary($this->normalizeOptionalString($taskPayload['summary'] ?? null), $issueKey)) {
            $signature['summary'] = trim($issueTask->summary);
        }

        if ($this->shouldInheritField('repository_path', $taskType)
            && $this->normalizeOptionalString($taskPayload['repository_path'] ?? null) === null
            && $this->normalizeOptionalString($issueTask->repository_path) !== null
        ) {
            $signature['repository_path'] = trim($issueTask->repository_path);
        }

        if ($this->shouldInheritField('provider', $taskType)
            && $this->normalizeOptionalString($taskPayload['provider'] ?? null) === null
        ) {
            $providerName = $this->normalizeOptionalString($taskContext['provider_name'] ?? null);

            if ($providerName !== null) {
                $signature['provider'] = $providerName;
            }
        }

        if ($this->shouldInheritField('provider_options', $taskType)
            && $this->normalizeProviderOptions($taskPayload['provider_options'] ?? null) === []
        ) {
            $providerOptions = $this->normalizeProviderOptions($taskContext['provider_options'] ?? null);

            if ($providerOptions !== []) {
                $signature['provider_options'] = $providerOptions;
            }
        }

        if ($this->shouldInheritField('dirty_workspace_policy', $taskType)
            && $this->normalizeOptionalString($taskPayload['dirty_workspace_policy'] ?? null) === null
        ) {
            $dirtyWorkspacePolicy = $this->normalizeOptionalString($taskContext['dirty_workspace_policy'] ?? null);

            if ($dirtyWorkspacePolicy !== null) {
                $signature['dirty_workspace_policy'] = $dirtyWorkspacePolicy;
            }
        }

        if ($this->shouldInheritField('scope_paths', $taskType)
            && $this->normalizeScopePaths($taskPayload['scope_paths'] ?? null) === []
        ) {
            $scopePaths = $this->normalizeScopePaths($taskContext['scope_paths'] ?? null);

            if ($scopePaths !== []) {
                $signature['scope_paths'] = $scopePaths;
            }
        }

        if ($this->shouldInheritField('scope_policy', $taskType)
            && $this->normalizeOptionalString($taskPayload['scope_policy'] ?? null) === null
        ) {
            $scopePolicy = $this->normalizeOptionalString($taskContext['scope_policy'] ?? null);

            if ($scopePolicy !== null) {
                $signature['scope_policy'] = $scopePolicy;
            }
        }

        return $signature;
    }

    /**
     * Build one Telegram-facing clarification candidate from a reusable issue task.
     *
     * @param  AutoCodingTask  $issueTask
     * @param  array<string, mixed>  $signature
     * @return array<string, mixed>
     */
    protected function buildClarificationCandidate(AutoCodingTask $issueTask, array $signature): array
    {
        $taskId = $issueTask->getKey();

        $candidate = [
            'task_id' => is_numeric($taskId) ? (int) $taskId : 0,
            'summary' => trim($issueTask->summary),
            'branch_name' => $this->normalizeOptionalString($issueTask->branch_name),
            'signature_fields' => array_keys($signature),
        ];

        foreach (['repository_path', 'provider', 'dirty_workspace_policy', 'scope_policy'] as $field) {
            if (array_key_exists($field, $signature)) {
                $candidate[$field] = $signature[$field];
            }
        }

        if (array_key_exists('scope_paths', $signature)) {
            $candidate['scope_paths'] = $signature['scope_paths'];
        }

        if (array_key_exists('provider_options', $signature)) {
            $candidate['provider_options'] = $signature['provider_options'];
        }

        return $candidate;
    }

    /**
     * Build one reusable issue-context payload from the best local source.
     *
     * @param  string  $issueKey
     * @param  array<string, mixed>  $taskPayload
     * @param  AutoCodingTask|null  $latestIssueTask
     * @return array<string, mixed>
     */
    protected function buildIssueContext(string $issueKey, array $taskPayload, ?AutoCodingTask $latestIssueTask): array
    {
        if ($latestIssueTask instanceof AutoCodingTask) {
            $context = $this->gitHubStatusQueryService->resolveForTask($latestIssueTask);

            return array_merge($context, [
                'source_task_id' => $latestIssueTask->getKey(),
                'source_summary' => $latestIssueTask->summary,
            ]);
        }

        $repositoryPath = $this->resolveRepositoryPath($taskPayload);

        return $this->gitHubContextService->inspect($repositoryPath, null, $issueKey);
    }

    /**
     * Resolve the best summary for one issue-linked task payload.
     *
     * @param  string|null  $summary
     * @param  string  $issueKey
     * @param  AutoCodingTask|null  $latestIssueTask
     * @return string|null
     */
    protected function resolveEnrichedSummary(?string $summary, string $issueKey, ?AutoCodingTask $latestIssueTask): ?string
    {
        if (! $this->shouldReplaceSummary($summary, $issueKey)) {
            return $summary;
        }

        if ($latestIssueTask instanceof AutoCodingTask && trim($latestIssueTask->summary) !== '') {
            return trim($latestIssueTask->summary);
        }

        return $summary;
    }

    /**
     * Inherit reusable execution hints from the latest issue-linked task.
     *
     * Only copy fields when the new payload did not already specify them.
     *
     * @param  array<string, mixed>  $taskPayload
     * @param  AutoCodingTask  $latestIssueTask
     * @param  string  $taskType
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    protected function inheritExecutionHints(
        array $taskPayload,
        AutoCodingTask $latestIssueTask,
        string $taskType,
    ): array {
        $taskContext = is_array($latestIssueTask->context_payload) ? $latestIssueTask->context_payload : [];
        $reusedFields = [];

        if ($this->shouldInheritField('repository_path', $taskType)
            && $this->normalizeOptionalString($taskPayload['repository_path'] ?? null) === null
        ) {
            $taskPayload['repository_path'] = $latestIssueTask->repository_path;
            $reusedFields[] = 'repository_path';
        }

        if ($this->shouldInheritField('provider', $taskType)
            && $this->normalizeOptionalString($taskPayload['provider'] ?? null) === null
        ) {
            $providerName = $this->normalizeOptionalString($taskContext['provider_name'] ?? null);

            if ($providerName !== null) {
                $taskPayload['provider'] = $providerName;
                $reusedFields[] = 'provider';
            }
        }

        if ($this->shouldInheritField('provider_options', $taskType)
            && $this->normalizeProviderOptions($taskPayload['provider_options'] ?? null) === []
        ) {
            $providerOptions = $this->normalizeProviderOptions($taskContext['provider_options'] ?? null);

            if ($providerOptions !== []) {
                $taskPayload['provider_options'] = $providerOptions;
                $reusedFields[] = 'provider_options';
            }
        }

        if ($this->shouldInheritField('dirty_workspace_policy', $taskType)
            && $this->normalizeOptionalString($taskPayload['dirty_workspace_policy'] ?? null) === null
        ) {
            $dirtyPolicy = $this->normalizeOptionalString($taskContext['dirty_workspace_policy'] ?? null);

            if ($dirtyPolicy !== null) {
                $taskPayload['dirty_workspace_policy'] = $dirtyPolicy;
                $reusedFields[] = 'dirty_workspace_policy';
            }
        }

        if ($this->shouldInheritField('scope_paths', $taskType)
            && $this->normalizeScopePaths($taskPayload['scope_paths'] ?? null) === []
        ) {
            $scopePaths = $this->normalizeScopePaths($taskContext['scope_paths'] ?? null);

            if ($scopePaths !== []) {
                $taskPayload['scope_paths'] = $scopePaths;
                $reusedFields[] = 'scope_paths';
            }
        }

        if ($this->shouldInheritField('scope_policy', $taskType)
            && $this->normalizeOptionalString($taskPayload['scope_policy'] ?? null) === null
        ) {
            $scopePolicy = $this->normalizeOptionalString($taskContext['scope_policy'] ?? null);

            if ($scopePolicy !== null) {
                $taskPayload['scope_policy'] = $scopePolicy;
                $reusedFields[] = 'scope_policy';
            }
        }

        return [$taskPayload, $reusedFields];
    }

    /**
     * Determine whether one issue-linked summary is only a generic placeholder.
     *
     * @param  string|null  $summary
     * @param  string  $issueKey
     * @return bool
     */
    protected function shouldReplaceSummary(?string $summary, string $issueKey): bool
    {
        if (! is_string($summary) || trim($summary) === '') {
            return true;
        }

        $normalizedSummary = trim($summary);

        if ($this->isTerseIssueLinkedSummary($normalizedSummary)) {
            return true;
        }

        return in_array($normalizedSummary, [
            'Review the latest repository changes.',
            'Review request: Review the latest repository changes.',
            'Validate the current repository state.',
            'Validation request: Validate the current repository state.',
            sprintf('Review GitHub issue %s and implement the requested changes.', $issueKey),
            sprintf('Review GitHub issue %s and assess the requested changes.', $issueKey),
            sprintf('Validate the current work for GitHub issue %s.', $issueKey),
            sprintf('Validation request: Validate the current work for GitHub issue %s.', $issueKey),
        ], true);
    }

    /**
     * Determine whether one issue-linked summary is too terse to be useful.
     *
     * @param  string  $summary
     * @return bool
     */
    protected function isTerseIssueLinkedSummary(string $summary): bool
    {
        return in_array(mb_strtolower(trim($summary)), [
            'fix',
            'implement',
            'review',
            'validate',
            'validation',
            'check',
            'test',
            'lint',
            'sua',
            'sửa',
        ], true);
    }

    /**
     * Resolve the repository path used for local issue-context inspection.
     *
     * @param  array<string, mixed>  $taskPayload
     * @return string
     */
    protected function resolveRepositoryPath(array $taskPayload): string
    {
        $payloadPath = $this->normalizeOptionalString($taskPayload['repository_path'] ?? null);
        $defaultPath = config('opas.auto_coding.default_repository_path');

        if ($payloadPath !== null) {
            return $payloadPath;
        }

        return is_string($defaultPath) && trim($defaultPath) !== ''
            ? trim($defaultPath)
            : base_path('..');
    }

    /**
     * Resolve the task type used to decide safe issue-hint inheritance.
     *
     * @param  array<string, mixed>  $taskPayload
     * @return string
     */
    protected function resolveTaskType(array $taskPayload): string
    {
        $metadata = is_array($taskPayload['context_metadata'] ?? null) ? $taskPayload['context_metadata'] : [];
        $transportContext = is_array($metadata['transport_context'] ?? null) ? $metadata['transport_context'] : [];
        $intent = $this->normalizeOptionalString($transportContext['intent'] ?? null);
        $command = $this->normalizeOptionalString($transportContext['command'] ?? null);

        return match (true) {
            $intent !== null => $intent,
            $command !== null && $command !== 'conversation' => $command,
            ($taskPayload['validate'] ?? false) === true => 'validate',
            default => 'code',
        };
    }

    /**
     * Determine whether one field should be inherited for the current task type.
     *
     * @param  string  $field
     * @param  string  $taskType
     * @return bool
     */
    protected function shouldInheritField(string $field, string $taskType): bool
    {
        return match ($taskType) {
            'review' => in_array($field, ['repository_path', 'scope_paths', 'scope_policy'], true),
            'validate' => in_array($field, ['repository_path', 'dirty_workspace_policy', 'scope_paths', 'scope_policy'], true),
            default => in_array($field, [
                'repository_path',
                'provider',
                'provider_options',
                'dirty_workspace_policy',
                'scope_paths',
                'scope_policy',
            ], true),
        };
    }

    /**
     * Infer the reusable task type from one persisted issue-linked task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    protected function inferExistingTaskType(AutoCodingTask $task): string
    {
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $transportContext = is_array($taskContext['transport_context'] ?? null) ? $taskContext['transport_context'] : [];
        $intent = $this->normalizeOptionalString($transportContext['intent'] ?? null);
        $command = $this->normalizeOptionalString($transportContext['command'] ?? null);
        $summary = trim((string) $task->summary);

        return match (true) {
            $intent !== null => $intent,
            $command !== null && $command !== 'conversation' => $command,
            str_starts_with($summary, 'Review request:') => 'review',
            str_starts_with($summary, 'Validation request:') => 'validate',
            default => 'code',
        };
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
     * Normalize one scope-path payload into trimmed repository prefixes.
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
     * Normalize one optional string value.
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
}
