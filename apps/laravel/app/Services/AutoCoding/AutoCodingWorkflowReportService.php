<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Enums\AutoCodingFailureCategory;
use App\Enums\AutoCodingRecommendedAction;
use App\Enums\AutoCodingWorkflowStep;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Models\AutoCodingTaskRunStep;
use Carbon\CarbonInterface;

/**
 * Build normalized workflow reports for local auto-coding task runs.
 */
class AutoCodingWorkflowReportService
{
    public function __construct(
        private readonly AutoCodingFollowUpAnswerService $followUpAnswerService,
        private readonly AutoCodingFollowUpContractService $followUpContractService,
        private readonly AutoCodingFollowUpQuestionService $followUpQuestionService,
    ) {}

    /**
     * Build one scope analysis block for the current repository snapshot.
     *
     * @param  array<string, mixed>  $repositoryContext
     * @param  array<int, string>  $scopePaths
     * @param  string  $scopePolicy
     * @return array{requested_paths: array<int, string>, policy: string, in_scope: bool, matching_files: array<int, string>, out_of_scope_files: array<int, string>}
     */
    public function buildScopeAnalysis(
        array $repositoryContext,
        array $scopePaths,
        string $scopePolicy,
    ): array {
        $changedFiles = $repositoryContext['changed_files'] ?? [];

        if (! is_array($changedFiles) || $scopePaths === []) {
            return [
                'requested_paths' => $scopePaths,
                'policy' => $scopePolicy,
                'in_scope' => true,
                'matching_files' => [],
                'out_of_scope_files' => [],
            ];
        }

        $matchingFiles = [];
        $outOfScopeFiles = [];

        foreach ($changedFiles as $changedFile) {
            $path = is_array($changedFile) && is_string($changedFile['path'] ?? null)
                ? $changedFile['path']
                : null;

            if ($path === null) {
                continue;
            }

            if ($this->pathMatchesAnyScope($path, $scopePaths)) {
                $matchingFiles[] = $path;
            } else {
                $outOfScopeFiles[] = $path;
            }
        }

        return [
            'requested_paths' => $scopePaths,
            'policy' => $scopePolicy,
            'in_scope' => $outOfScopeFiles === [],
            'matching_files' => array_values(array_unique($matchingFiles)),
            'out_of_scope_files' => array_values(array_unique($outOfScopeFiles)),
        ];
    }

    /**
     * Build one normalized preflight report from dirty-workspace and scope checks.
     *
     * @param  array<string, mixed>  $repositoryContext
     * @param  string  $dirtyWorkspacePolicy
     * @param  array{requested_paths: array<int, string>, policy: string, in_scope: bool, matching_files: array<int, string>, out_of_scope_files: array<int, string>}  $scopeAnalysis
     * @return array{
     *   overall_status: string,
     *   blocking_reason: string|null,
     *   warnings: array<int, string>,
     *   checks: array<int, array{key: string, status: string, policy: string, message: string}>
     * }
     */
    public function buildPreflightReport(
        array $repositoryContext,
        string $dirtyWorkspacePolicy,
        array $scopeAnalysis,
    ): array {
        $checks = [];
        $warnings = [];
        $blockingReason = null;
        $isDirty = ($repositoryContext['is_dirty'] ?? false) === true;

        $dirtyStatus = 'passed';
        $dirtyMessage = 'Repository workspace is clean.';

        if ($isDirty && $dirtyWorkspacePolicy === 'block') {
            $dirtyStatus = 'blocked';
            $dirtyMessage = 'Repository has local changes and the policy blocks execution.';
            $blockingReason = 'dirty_workspace';
        } elseif ($isDirty && $dirtyWorkspacePolicy === 'warn') {
            $dirtyStatus = 'warning';
            $dirtyMessage = 'Repository has local changes but execution is allowed with warning.';
            $warnings[] = 'Repository contains local changes.';
        } elseif ($isDirty && $dirtyWorkspacePolicy === 'allow') {
            $dirtyStatus = 'passed';
            $dirtyMessage = 'Repository has local changes and the policy explicitly allows execution.';
        }

        $checks[] = [
            'key' => 'dirty_workspace',
            'status' => $dirtyStatus,
            'policy' => $dirtyWorkspacePolicy,
            'message' => $dirtyMessage,
        ];

        $scopeStatus = 'skipped';
        $scopeMessage = 'No changed-file scope constraints were requested.';

        if ($scopeAnalysis['requested_paths'] !== []) {
            if ($scopeAnalysis['in_scope'] === true) {
                $scopeStatus = 'passed';
                $scopeMessage = 'Changed files stayed within the requested task scope.';
            } elseif ($scopeAnalysis['policy'] === 'block') {
                $scopeStatus = 'blocked';
                $scopeMessage = 'Changed files fall outside the requested task scope and execution is blocked.';
                $blockingReason ??= 'scope_mismatch';
            } elseif ($scopeAnalysis['policy'] === 'warn') {
                $scopeStatus = 'warning';
                $scopeMessage = 'Changed files fall outside the requested task scope but execution is allowed with warning.';
                $warnings[] = 'Changed files fall outside the requested task scope.';
            } else {
                $scopeStatus = 'passed';
                $scopeMessage = 'Changed files fall outside the requested task scope and the policy explicitly allows execution.';
            }
        }

        $checks[] = [
            'key' => 'scope',
            'status' => $scopeStatus,
            'policy' => $scopeAnalysis['policy'],
            'message' => $scopeMessage,
        ];

        $overallStatus = 'passed';

        if ($blockingReason !== null) {
            $overallStatus = 'blocked';
        } elseif ($warnings !== []) {
            $overallStatus = 'warning';
        }

        return [
            'overall_status' => $overallStatus,
            'blocking_reason' => $blockingReason,
            'warnings' => array_values(array_unique($warnings)),
            'checks' => $checks,
        ];
    }

    /**
     * Hydrate one follow-up payload with answer history and a resume contract for the current blocked run.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $followUp
     * @return array<string, mixed>
     */
    public function buildFollowUpReport(AutoCodingTask $task, AutoCodingTaskRun $run, array $followUp): array
    {
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $followUpAnswers = is_array($taskContext['follow_up_answers'] ?? null)
            ? $this->followUpAnswerService->normalizeAnswers($taskContext['follow_up_answers'])
            : [];
        $questionContractsSource = $followUp['question_contracts'] ?? $followUp['questions'] ?? [];
        $questionContracts = $this->followUpQuestionService->normalizeQuestionContracts($questionContractsSource);
        $latestAnswer = $followUpAnswers === [] ? null : $followUpAnswers[array_key_last($followUpAnswers)];
        $latestAnsweredAt = is_array($latestAnswer) && is_string($latestAnswer['submitted_at'] ?? null)
            ? $latestAnswer['submitted_at']
            : null;

        return [
            'required' => (bool) ($followUp['required'] ?? false),
            'reason' => is_string($followUp['reason'] ?? null)
                ? $followUp['reason']
                : null,
            'message' => is_string($followUp['message'] ?? null)
                ? $followUp['message']
                : null,
            'questions' => $this->followUpQuestionService->normalizeQuestionPrompts(
                $followUp['questions'] ?? [],
                $questionContracts
            ),
            'question_contracts' => $questionContracts,
            'answered' => $followUpAnswers !== [],
            'answer_count' => count($followUpAnswers),
            'last_answered_at' => $latestAnsweredAt,
            'last_answer' => is_array($latestAnswer)
                ? $latestAnswer
                : null,
            'input_contract' => $this->followUpContractService->buildResolvedInputContract($task, $run, $followUp),
        ];
    }

    /**
     * Build one normalized retry summary from persisted workflow steps.
     *
     * @param  AutoCodingTaskRun  $run
     * @return array{
     *   overall_retryable: bool,
     *   validation: array{attempts_used: int, max_attempts: int, remaining_attempts: int, exhausted: bool},
     *   retryable_steps: array<int, array{step: string, attempts_used: int, last_attempt: int}>
     * }
     */
    public function buildRetryReport(AutoCodingTaskRun $run): array
    {
        $steps = $run->relationLoaded('steps') ? $run->steps : $run->steps()->get();
        $validationMaxAttempts = $this->resolveValidationRetryLimit();
        $validationAttemptsUsed = $steps
            ->where('step_key', AutoCodingWorkflowStep::RunValidation)
            ->count();
        $retryableSteps = $steps
            ->filter(static fn (AutoCodingTaskRunStep $step): bool => $step->is_retryable)
            ->groupBy(static fn (AutoCodingTaskRunStep $step): string => $step->step_key->value)
            ->map(static function ($group, string $stepKey): array {
                /** @var \Illuminate\Support\Collection<int, AutoCodingTaskRunStep> $group */
                $lastStep = $group->sortByDesc('attempt')->first();

                return [
                    'step' => $stepKey,
                    'attempts_used' => $group->count(),
                    'last_attempt' => $lastStep instanceof AutoCodingTaskRunStep ? $lastStep->attempt : 0,
                ];
            })
            ->values()
            ->all();

        return [
            'overall_retryable' => $retryableSteps !== [],
            'validation' => [
                'attempts_used' => $validationAttemptsUsed,
                'max_attempts' => $validationMaxAttempts,
                'remaining_attempts' => max(0, $validationMaxAttempts - $validationAttemptsUsed),
                'exhausted' => $validationAttemptsUsed >= $validationMaxAttempts,
            ],
            'retryable_steps' => $retryableSteps,
        ];
    }

    /**
     * Build the compact workflow report block from persisted run steps.
     *
     * @param  AutoCodingTaskRun  $run
     * @return array<string, mixed>
     */
    public function buildWorkflowReport(AutoCodingTaskRun $run): array
    {
        $steps = $run->relationLoaded('steps') ? $run->steps : $run->steps()->get();
        $stepPayload = $steps->map(static function (AutoCodingTaskRunStep $step): array {
            return [
                'step' => $step->step_key->value,
                'sequence' => $step->sequence,
                'attempt' => $step->attempt,
                'status' => $step->status->value,
                'retryable' => $step->is_retryable,
                'error' => $step->error_message,
                'started_at' => $step->started_at?->toIso8601String(),
                'completed_at' => $step->completed_at?->toIso8601String(),
            ];
        })->values()->all();

        $latestStep = $steps->sortByDesc('sequence')->first();
        $lastFailedStep = $steps->first(
            static fn (AutoCodingTaskRunStep $step): bool => $step->status->value === 'failed'
        );
        $lastBlockedStep = $steps->first(
            static fn (AutoCodingTaskRunStep $step): bool => $step->status->value === 'blocked'
        );
        $lastRetryableStep = $steps
            ->filter(static fn (AutoCodingTaskRunStep $step): bool => $step->is_retryable)
            ->sortByDesc('sequence')
            ->first();

        return [
            'current_step' => $latestStep instanceof AutoCodingTaskRunStep
                ? $latestStep->step_key->value
                : null,
            'last_failed_step' => $lastFailedStep instanceof AutoCodingTaskRunStep
                ? $lastFailedStep->step_key->value
                : null,
            'last_blocked_step' => $lastBlockedStep instanceof AutoCodingTaskRunStep
                ? $lastBlockedStep->step_key->value
                : null,
            'last_retryable_step' => $lastRetryableStep instanceof AutoCodingTaskRunStep
                ? $lastRetryableStep->step_key->value
                : null,
            'current_decision_point' => $this->buildWorkflowDecisionPoint($run),
            'steps' => $stepPayload,
        ];
    }

    /**
     * Resolve one normalized failure classification block for the final report.
     *
     * @param  AutoCodingExecutionStatus  $status
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, mixed>  $validationResults
     * @param  array<string, mixed>  $followUp
     * @param  array<string, mixed>  $preflight
     * @param  string|null  $errorMessage
     * @return array{category: string, source: string|null, retryable: bool, message: string|null}
     */
    public function buildFailureClassification(
        AutoCodingExecutionStatus $status,
        array $providerResult,
        array $validationResults,
        array $followUp,
        array $preflight,
        ?string $errorMessage,
    ): array {
        if ($status === AutoCodingExecutionStatus::Completed) {
            return [
                'category' => AutoCodingFailureCategory::None->value,
                'source' => null,
                'retryable' => false,
                'message' => null,
            ];
        }

        if ($status === AutoCodingExecutionStatus::Cancelled) {
            return [
                'category' => AutoCodingFailureCategory::None->value,
                'source' => 'user_cancelled',
                'retryable' => false,
                'message' => 'Task was cancelled by operator request.',
            ];
        }

        if (($preflight['overall_status'] ?? null) === 'blocked') {
            $blockingReason = is_string($preflight['blocking_reason'] ?? null)
                ? $preflight['blocking_reason']
                : 'preflight';

            return [
                'category' => AutoCodingFailureCategory::PreflightBlock->value,
                'source' => $blockingReason,
                'retryable' => true,
                'message' => is_string($followUp['message'] ?? null)
                    ? $followUp['message']
                    : $errorMessage,
            ];
        }

        if (($followUp['required'] ?? false) === true) {
            return [
                'category' => AutoCodingFailureCategory::ProviderFollowUp->value,
                'source' => 'provider',
                'retryable' => true,
                'message' => is_string($followUp['message'] ?? null)
                    ? $followUp['message']
                    : $errorMessage,
            ];
        }

        if (($providerResult['status'] ?? null) === 'failed') {
            return [
                'category' => AutoCodingFailureCategory::ProviderFailure->value,
                'source' => 'provider',
                'retryable' => false,
                'message' => is_string($providerResult['message'] ?? null) ? $providerResult['message'] : $errorMessage,
            ];
        }

        if (($validationResults['overall_status'] ?? null) === 'failed') {
            return [
                'category' => AutoCodingFailureCategory::ValidationFailure->value,
                'source' => 'validation',
                'retryable' => ($validationResults['can_retry'] ?? false) === true,
                'message' => is_string($validationResults['summary'] ?? null) ? $validationResults['summary'] : $errorMessage,
            ];
        }

        return [
            'category' => AutoCodingFailureCategory::ExecutionException->value,
            'source' => 'execution',
            'retryable' => false,
            'message' => $errorMessage,
        ];
    }

    /**
     * Build one normalized next-action recommendation from workflow outcome data.
     *
     * @param  array<string, mixed>  $failure
     * @param  array<string, mixed>  $preflight
     * @param  array<string, mixed>  $followUp
     * @param  array<string, mixed>  $validationResults
     * @return array{action: string, reason: string|null, message: string}
     */
    public function buildActionRecommendation(
        array $failure,
        array $preflight,
        array $followUp,
        array $validationResults,
    ): array {
        $failureCategory = is_string($failure['category'] ?? null)
            ? $failure['category']
            : AutoCodingFailureCategory::ExecutionException->value;
        $failureMessage = is_string($failure['message'] ?? null)
            ? $failure['message']
            : 'Inspect the workflow outcome before continuing.';

        return match ($failureCategory) {
            AutoCodingFailureCategory::None->value => [
                'action' => AutoCodingRecommendedAction::TaskComplete->value,
                'reason' => is_string($failure['source'] ?? null) ? $failure['source'] : null,
                'message' => ($failure['source'] ?? null) === 'user_cancelled'
                    ? 'Task was cancelled by operator request.'
                    : 'Task completed successfully. No further workflow action is required.',
            ],
            AutoCodingFailureCategory::PreflightBlock->value => [
                'action' => AutoCodingRecommendedAction::ResumeWithConfirmation->value,
                'reason' => is_string($preflight['blocking_reason'] ?? null) ? $preflight['blocking_reason'] : 'preflight_block',
                'message' => 'Review the preflight blocker and resume only after confirming the workspace is safe.',
            ],
            AutoCodingFailureCategory::ProviderFollowUp->value => [
                'action' => AutoCodingRecommendedAction::ProvideFollowUp->value,
                'reason' => is_string($followUp['reason'] ?? null) ? $followUp['reason'] : 'provider_follow_up',
                'message' => 'Provide the requested follow-up input so the workflow can continue.',
            ],
            AutoCodingFailureCategory::ValidationFailure->value => [
                'action' => AutoCodingRecommendedAction::RerunValidation->value,
                'reason' => 'validation_failure',
                'message' => ($validationResults['can_retry'] ?? false) === true
                    ? 'Fix the validation issue or rerun the validation workflow after adjustments.'
                    : 'Validation failed and needs manual fixes before the workflow should continue.',
            ],
            AutoCodingFailureCategory::ProviderFailure->value => [
                'action' => AutoCodingRecommendedAction::FixProviderConfig->value,
                'reason' => 'provider_failure',
                'message' => $failureMessage,
            ],
            default => [
                'action' => AutoCodingRecommendedAction::InspectExecutionError->value,
                'reason' => 'execution_exception',
                'message' => $failureMessage,
            ],
        };
    }

    /**
     * Build the structured final report for one local task run.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  string  $machineKey
     * @param  AutoCodingExecutionStatus  $status
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, mixed>  $validationResults
     * @param  array<string, mixed>  $gitHubContext
     * @param  array<string, mixed>  $followUp
     * @param  string|null  $errorMessage
     * @param  string  $dirtyWorkspacePolicy
     * @param  array<int, string>  $scopePaths
     * @param  string  $scopePolicy
     * @return array<string, mixed>
     */
    public function buildFinalReport(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        string $machineKey,
        AutoCodingExecutionStatus $status,
        array $providerResult,
        array $validationResults,
        array $gitHubContext,
        array $followUp,
        ?string $errorMessage,
        string $dirtyWorkspacePolicy,
        array $scopePaths,
        string $scopePolicy,
    ): array {
        $scopeAnalysis = $this->buildScopeAnalysis(
            $run->repository_snapshot,
            $scopePaths,
            $scopePolicy,
        );
        $preflight = $this->buildPreflightReport(
            $run->repository_snapshot,
            $dirtyWorkspacePolicy,
            $scopeAnalysis,
        );
        $resolvedFollowUp = $this->buildFollowUpReport($task, $run, $followUp);
        $retry = $this->buildRetryReport($run);
        $failure = $this->buildFailureClassification(
            $status,
            $providerResult,
            $validationResults,
            $resolvedFollowUp,
            $preflight,
            $errorMessage,
        );
        $recommendation = $this->buildActionRecommendation(
            $failure,
            $preflight,
            $resolvedFollowUp,
            $validationResults,
        );

        return [
            'status' => $status->value,
            'task' => [
                'id' => $task->getKey(),
                'summary' => $task->summary,
                'issue_key' => $task->issue_key,
                'status' => $status->value,
            ],
            'run' => [
                'id' => $run->getKey(),
                'status' => $status->value,
                'started_at' => $run->started_at instanceof CarbonInterface
                    ? $run->started_at->toIso8601String()
                    : null,
                'completed_at' => now()->toIso8601String(),
            ],
            'machine' => [
                'machine_key' => $machineKey,
            ],
            'repository' => $run->repository_snapshot,
            'github' => $gitHubContext,
            'provider' => $providerResult,
            'provider_result' => $providerResult,
            'validation' => $validationResults,
            'preflight' => $preflight,
            'retry' => $retry,
            'scope' => $scopeAnalysis,
            'follow_up' => $resolvedFollowUp,
            'failure' => $failure,
            'recommended_action' => $recommendation,
            'error' => $errorMessage,
            'workflow' => $this->buildWorkflowReport($run),
            'summary' => [
                'artifact_count' => 5,
                'changed_file_count' => count($this->resolveChangedFiles($run)),
                'is_dirty' => (bool) ($run->repository_snapshot['is_dirty'] ?? false),
            ],
        ];
    }

    /**
     * Determine whether one changed path matches any requested scope prefix.
     *
     * @param  string  $path
     * @param  array<int, string>  $scopePaths
     * @return bool
     */
    protected function pathMatchesAnyScope(string $path, array $scopePaths): bool
    {
        foreach ($scopePaths as $scopePath) {
            if ($path === $scopePath || str_starts_with($path, rtrim($scopePath, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build one compact workflow decision-point summary from the latest step state.
     *
     * @param  AutoCodingTaskRun  $run
     * @return array{type: string, step: string|null}
     */
    protected function buildWorkflowDecisionPoint(AutoCodingTaskRun $run): array
    {
        $steps = $run->relationLoaded('steps') ? $run->steps : $run->steps()->get();
        $latestStep = $steps->sortByDesc('sequence')->first();

        if (! $latestStep instanceof AutoCodingTaskRunStep) {
            return [
                'type' => 'unknown',
                'step' => null,
            ];
        }

        return match ($latestStep->status->value) {
            'blocked' => [
                'type' => 'blocked',
                'step' => $latestStep->step_key->value,
            ],
            'failed' => [
                'type' => 'failure',
                'step' => $latestStep->step_key->value,
            ],
            'completed' => [
                'type' => 'completed',
                'step' => $latestStep->step_key->value,
            ],
            default => [
                'type' => 'in_progress',
                'step' => $latestStep->step_key->value,
            ],
        };
    }

    /**
     * Resolve the configured validation retry limit as one normalized integer.
     *
     * @return int
     */
    protected function resolveValidationRetryLimit(): int
    {
        $configuredRetryLimit = config('opas.auto_coding.workflow.validation_retry_limit');

        return max(1, is_numeric($configuredRetryLimit) ? (int) $configuredRetryLimit : 1);
    }

    /**
     * Resolve the changed file payload from the repository snapshot safely.
     *
     * @param  AutoCodingTaskRun  $run
     * @return array<int, array<string, string>>
     */
    protected function resolveChangedFiles(AutoCodingTaskRun $run): array
    {
        $changedFiles = $run->repository_snapshot['changed_files'] ?? [];

        if (! is_array($changedFiles)) {
            return [];
        }

        /** @var array<int, array<string, string>> $changedFiles */
        return $changedFiles;
    }
}
