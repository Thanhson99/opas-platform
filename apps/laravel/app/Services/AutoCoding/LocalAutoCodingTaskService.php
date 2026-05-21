<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class LocalAutoCodingTaskService
{
    /**
     * Create one pending local auto-coding task that can be executed later by a queue worker.
     *
     * @param  string  $summary
     * @param  string|null  $issueKey
     * @param  string|null  $repositoryPath
     * @param  bool  $shouldRunValidation
     * @param  string|null  $providerName
     * @param  array<string, mixed>  $providerOptions
     * @param  string  $dirtyWorkspacePolicy
     * @param  array<int, string>  $scopePaths
     * @param  string  $scopePolicy
     * @return AutoCodingTask
     */
    public function createPendingTask(
        string $summary,
        ?string $issueKey = null,
        ?string $repositoryPath = null,
        bool $shouldRunValidation = false,
        ?string $providerName = null,
        array $providerOptions = [],
        string $dirtyWorkspacePolicy = 'warn',
        array $scopePaths = [],
        string $scopePolicy = 'warn',
    ): AutoCodingTask {
        $effectiveRepositoryPath = $this->resolveRequestedRepositoryPath($repositoryPath);
        $pendingReport = $this->queueStateService->buildPendingReport($summary, $issueKey, $effectiveRepositoryPath);

        /** @var AutoCodingTask $task */
        $task = DB::transaction(function () use (
            $summary,
            $issueKey,
            $effectiveRepositoryPath,
            $shouldRunValidation,
            $providerName,
            $providerOptions,
            $dirtyWorkspacePolicy,
            $scopePaths,
            $scopePolicy,
            $pendingReport,
        ): AutoCodingTask {
            /** @var AutoCodingTask $createdTask */
            $createdTask = AutoCodingTask::query()->create([
                'summary' => $summary,
                'issue_key' => $issueKey,
                'repository_path' => $effectiveRepositoryPath,
                'branch_name' => null,
                'status' => AutoCodingExecutionStatus::Pending,
                'context_payload' => [
                    'repository_path' => $effectiveRepositoryPath,
                    'should_run_validation' => $shouldRunValidation,
                    'provider_name' => $providerName,
                    'provider_options' => $providerOptions,
                    'dirty_workspace_policy' => $this->executionContextService->normalizeDirtyWorkspacePolicy($dirtyWorkspacePolicy),
                    'scope_paths' => $this->executionContextService->normalizeScopePaths($scopePaths),
                    'scope_policy' => $this->executionContextService->normalizeScopePolicy($scopePolicy),
                    'follow_up_answers' => [],
                ],
                'latest_report' => $pendingReport,
            ]);

            return $createdTask;
        });

        return $task;
    }

    public function __construct(
        private readonly LocalMachineService $localMachineService,
        private readonly AutoCodingQueueStateService $queueStateService,
        private readonly AutoCodingTaskRepositoryInterface $taskRepository,
        private readonly AutoCodingExecutionContextService $executionContextService,
        private readonly AutoCodingExecutionStateService $executionStateService,
        private readonly AutoCodingFollowUpRequestService $followUpRequestService,
        private readonly AutoCodingFollowUpResponseService $followUpResponseService,
        private readonly AutoCodingFollowUpWorkflowService $followUpWorkflowService,
        private readonly AutoCodingWorkflowStepRunnerService $workflowStepRunnerService,
    ) {}

    /**
     * Run one local-first autonomous coding inspection task and persist its report.
     *
     * @param  string  $summary
     * @param  string|null  $issueKey
     * @param  string|null  $repositoryPath
     * @param  bool  $shouldRunValidation
     * @param  string|null  $providerName
     * @param  array<string, mixed>  $providerOptions
     * @param  string  $dirtyWorkspacePolicy
     * @param  array<int, string>  $scopePaths
     * @param  string  $scopePolicy
     * @return AutoCodingTaskRun
     */
    public function runInspectionTask(
        string $summary,
        ?string $issueKey = null,
        ?string $repositoryPath = null,
        bool $shouldRunValidation = false,
        ?string $providerName = null,
        array $providerOptions = [],
        string $dirtyWorkspacePolicy = 'warn',
        array $scopePaths = [],
        string $scopePolicy = 'warn',
    ): AutoCodingTaskRun {
        $task = $this->createPendingTask(
            $summary,
            $issueKey,
            $repositoryPath,
            $shouldRunValidation,
            $providerName,
            $providerOptions,
            $dirtyWorkspacePolicy,
            $scopePaths,
            $scopePolicy,
        );

        return $this->executePendingTask($task->id);
    }

    /**
     * Execute one previously queued local auto-coding task.
     *
     * @param  int  $taskId
     * @return AutoCodingTaskRun
     */
    public function executePendingTask(int $taskId): AutoCodingTaskRun
    {
        $task = $this->findTaskOrFail($taskId);
        $executionContext = $this->executionContextService->buildExecutionContext($task);
        $machine = $this->localMachineService->resolve($executionContext['repository_path']);
        $run = $this->executionStateService->createRunningTaskRun($task, $machine->id, [
            'repository_path' => $executionContext['repository_path'],
        ]);

        try {
            $repositoryContext = $this->workflowStepRunnerService->runRepositoryInspectionStep(
                $run,
                $executionContext['repository_path']
            );
            $this->executionStateService->markTaskAsRunning($task, $executionContext['task_context'], $repositoryContext);
            $run->update([
                'repository_snapshot' => $repositoryContext,
            ]);

            $dirtyWorkspaceFollowUp = $this->followUpRequestService->buildDirtyWorkspaceFollowUp(
                $repositoryContext,
                $executionContext['dirty_workspace_policy']
            );

            if ($dirtyWorkspaceFollowUp['required']) {
                return $this->executionStateService->finalizeTerminalExecution(
                    $task,
                    $run,
                    AutoCodingExecutionStatus::Blocked,
                    $machine->machine_key,
                    $repositoryContext,
                    [
                        'prompt_package' => [],
                        'provider_result' => [
                            'status' => 'blocked',
                            'message' => $dirtyWorkspaceFollowUp['message'],
                        ],
                        'github_context' => [],
                        'validation_results' => $this->buildSkippedValidationResult(),
                    ],
                    $dirtyWorkspaceFollowUp,
                    is_string($dirtyWorkspaceFollowUp['message'] ?? null)
                        ? $dirtyWorkspaceFollowUp['message']
                        : null
                );
            }

            $scopeFollowUp = $this->followUpRequestService->buildScopeMismatchFollowUp(
                $repositoryContext,
                $executionContext['scope_paths'],
                $executionContext['scope_policy']
            );

            if ($scopeFollowUp['required']) {
                return $this->executionStateService->finalizeTerminalExecution(
                    $task,
                    $run,
                    AutoCodingExecutionStatus::Blocked,
                    $machine->machine_key,
                    $repositoryContext,
                    [
                        'prompt_package' => [],
                        'provider_result' => [
                            'status' => 'blocked',
                            'message' => $scopeFollowUp['message'],
                        ],
                        'github_context' => [],
                        'validation_results' => $this->buildSkippedValidationResult(),
                    ],
                    $scopeFollowUp,
                    is_string($scopeFollowUp['message'] ?? null)
                        ? $scopeFollowUp['message']
                        : null
                );
            }

            $providerContext = $this->executionContextService->buildProviderContext(
                $task,
                $repositoryContext,
                $executionContext['provider_options'],
                $executionContext['follow_up_answers'],
                $executionContext['follow_up_answer_summary'],
            );
            $promptPackage = $this->workflowStepRunnerService->runPromptPreparationStep($run, $providerContext);
            $providerResult = $this->workflowStepRunnerService->runProviderStep(
                $run,
                $providerContext,
                $executionContext['provider_name']
            );

            $followUp = $this->extractFollowUpRequest($providerResult);
            if ($followUp['required']) {
                return $this->executionStateService->finalizeTerminalExecution(
                    $task,
                    $run,
                    AutoCodingExecutionStatus::Blocked,
                    $machine->machine_key,
                    $repositoryContext,
                    [
                        'prompt_package' => $promptPackage,
                        'provider_result' => $providerResult,
                        'github_context' => [],
                        'validation_results' => $this->buildSkippedValidationResult(),
                    ],
                    $followUp,
                    null
                );
            }

            $gitHubContext = $this->workflowStepRunnerService->runGithubContextStep($run, $repositoryContext, $task->issue_key);
            $validationResults = $this->workflowStepRunnerService->runValidationStep(
                $run,
                $repositoryContext,
                $executionContext['should_run_validation']
            );

            $status = $this->resolveTerminalStatus($providerResult, $validationResults);

            return $this->executionStateService->finalizeTerminalExecution(
                $task,
                $run,
                $status,
                $machine->machine_key,
                $repositoryContext,
                [
                    'prompt_package' => $promptPackage,
                    'provider_result' => $providerResult,
                    'github_context' => $gitHubContext,
                    'validation_results' => $validationResults,
                ],
                ['required' => false, 'questions' => []],
                null
            );
        } catch (Throwable $throwable) {
            return $this->markExecutionAsFailed($task, $run, $machine->machine_key, $throwable);
        }
    }

    /**
     * Resume one blocked local auto-coding task with additional follow-up input.
     *
     * @param  int  $taskId
     * @param  string  $response
     * @param  string|null  $resumeToken
     * @param  array<string, mixed>|null  $responsePayload
     * @return AutoCodingTaskRun
     */
    public function resumeBlockedTask(
        int $taskId,
        string $response,
        ?string $resumeToken = null,
        ?array $responsePayload = null,
    ): AutoCodingTaskRun {
        $task = $this->findTaskOrFail($taskId);
        $this->assertResumeGuard($task, $resumeToken);
        /** @var array{
         *   type: string,
         *   value: string,
         *   metadata: array<string, mixed>,
         *   answers: array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>
         * } $normalizedResponsePayload
         */
        $normalizedResponsePayload = $this->followUpResponseService->normalizePayload($responsePayload, $response);
        $effectiveResponse = $normalizedResponsePayload['value'];
        $this->assertResumeResponseMatchesContract($task, $normalizedResponsePayload);
        $context = is_array($task->context_payload) ? $task->context_payload : [];
        $followUpAnswers = is_array($context['follow_up_answers'] ?? null)
            ? $context['follow_up_answers']
            : [];
        $followUpAnswers[] = $this->followUpResponseService->buildAnswerRecord($effectiveResponse, $normalizedResponsePayload);
        $dirtyWorkspacePolicy = $this->followUpWorkflowService->resolveDirtyWorkspacePolicyFromResume($task, $effectiveResponse);
        $scopePolicy = $this->followUpWorkflowService->resolveScopePolicyFromResume($task, $effectiveResponse);

        $task->update([
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => array_merge($context, [
                'follow_up_answers' => $followUpAnswers,
                'dirty_workspace_policy' => $dirtyWorkspacePolicy,
                'scope_policy' => $scopePolicy,
            ]),
            'latest_report' => $this->queueStateService->buildResumedLatestReport($task),
            'completed_at' => null,
        ]);

        return $this->executePendingTask($taskId);
    }

    /**
     * Ensure one resume request still targets the latest blocked workflow state.
     *
     * @param  AutoCodingTask  $task
     * @param  string|null  $resumeToken
     * @return void
     */
    protected function assertResumeGuard(AutoCodingTask $task, ?string $resumeToken): void
    {
        if ($task->status !== AutoCodingExecutionStatus::Blocked) {
            throw ValidationException::withMessages([
                'task' => 'Only blocked auto-coding tasks can be resumed.',
            ]);
        }

        $expectedResumeToken = $this->resolveExpectedResumeToken($task);

        if ($expectedResumeToken === null) {
            throw ValidationException::withMessages([
                'resume_token' => 'The blocked task is missing a resume token. Refresh task status before retrying.',
            ]);
        }

        if (! is_string($resumeToken) || trim($resumeToken) === '') {
            throw ValidationException::withMessages([
                'resume_token' => 'A valid resume token is required to continue this blocked task.',
            ]);
        }

        if (! hash_equals($expectedResumeToken, trim($resumeToken))) {
            throw ValidationException::withMessages([
                'resume_token' => 'Resume token is stale or invalid. Refresh task status and retry with the latest blocked state.',
            ]);
        }
    }

    /**
     * Ensure one follow-up response matches the currently expected blocked-task input contract.
     *
     * @param  AutoCodingTask  $task
     * @param  array{
     *   type: string,
     *   value: string,
     *   metadata: array<string, mixed>,
     *   answers: array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>
     * }  $responsePayload
     * @return void
     */
    protected function assertResumeResponseMatchesContract(
        AutoCodingTask $task,
        array $responsePayload,
    ): void {
        /** @var array<string, mixed> $followUp */
        $followUp = is_array($task->latest_report['follow_up'] ?? null)
            ? $task->latest_report['follow_up']
            : [];
        $this->followUpResponseService->assertMatchesFollowUpContract($followUp, $responsePayload);
    }

    /**
     * Resolve the authoritative resume token for the latest blocked run.
     *
     * @param  AutoCodingTask  $task
     * @return string|null
     */
    protected function resolveExpectedResumeToken(AutoCodingTask $task): ?string
    {
        $latestRun = $task->relationLoaded('runs')
            ? $task->runs->sortByDesc('id')->first()
            : $task->runs()->latest('id')->first();

        if (! $latestRun instanceof AutoCodingTaskRun || $latestRun->status !== AutoCodingExecutionStatus::Blocked) {
            return null;
        }

        return sprintf('task:%d:run:%d:blocked', $task->id, $latestRun->id);
    }

    /**
     * Claim the oldest pending local auto-coding task for one repository path.
     *
     * @param  string|null  $repositoryPath
     * @return AutoCodingTask|null
     */
    public function claimNextPendingTask(?string $repositoryPath = null): ?AutoCodingTask
    {
        return DB::transaction(function () use ($repositoryPath): ?AutoCodingTask {
            $task = $this->taskRepository->findOldestPending($repositoryPath);

            if (! $task instanceof AutoCodingTask) {
                return null;
            }

            $updated = AutoCodingTask::query()
                ->whereKey($task->id)
                ->where('status', AutoCodingExecutionStatus::Pending->value)
                ->update([
                    'status' => AutoCodingExecutionStatus::Running,
                    'latest_report' => $this->queueStateService->buildClaimedLatestReport($task),
                ]);

            if ($updated !== 1) {
                return null;
            }

            return $this->taskRepository->findDetailedById($task->id);
        });
    }

    /**
     * Resolve the requested repository path before git inspection is executed.
     *
     * @param  string|null  $repositoryPath
     * @return string
     */
    protected function resolveRequestedRepositoryPath(?string $repositoryPath): string
    {
        if (is_string($repositoryPath) && trim($repositoryPath) !== '') {
            return trim($repositoryPath);
        }

        $configuredPath = config('opas.auto_coding.default_repository_path');

        return is_string($configuredPath) && trim($configuredPath) !== ''
            ? trim($configuredPath)
            : base_path('..');
    }

    /**
     * Find one local auto-coding task by id or fail when it does not exist.
     *
     * @param  int  $taskId
     * @return AutoCodingTask
     */
    protected function findTaskOrFail(int $taskId): AutoCodingTask
    {
        /** @var AutoCodingTask $task */
        $task = AutoCodingTask::query()->findOrFail($taskId);

        return $task;
    }

    /**
     * Mark one local auto-coding execution as failed and persist the failure report.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  string  $machineKey
     * @param  Throwable  $throwable
     * @return AutoCodingTaskRun
     */
    protected function markExecutionAsFailed(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        string $machineKey,
        Throwable $throwable,
    ): AutoCodingTaskRun {
        return $this->executionStateService->finalizeTerminalExecution(
            $task,
            $run,
            AutoCodingExecutionStatus::Failed,
            $machineKey,
            $run->repository_snapshot,
            [
                'prompt_package' => [],
                'provider_result' => [
                    'status' => 'failed',
                ],
                'github_context' => [],
                'validation_results' => $this->buildSkippedValidationResult(),
            ],
            ['required' => false, 'questions' => []],
            $throwable->getMessage()
        );
    }

    /**
     * Resolve the terminal execution status from provider and validation outputs.
     *
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, mixed>  $validationResults
     * @return AutoCodingExecutionStatus
     */
    protected function resolveTerminalStatus(array $providerResult, array $validationResults): AutoCodingExecutionStatus
    {
        $providerStatus = is_string($providerResult['status'] ?? null) ? $providerResult['status'] : 'failed';
        if (! in_array($providerStatus, ['completed', 'skipped'], true)) {
            return AutoCodingExecutionStatus::Failed;
        }

        $validationStatus = is_string($validationResults['overall_status'] ?? null)
            ? $validationResults['overall_status']
            : 'failed';

        return in_array($validationStatus, ['passed', 'skipped', 'not_configured'], true)
            ? AutoCodingExecutionStatus::Completed
            : AutoCodingExecutionStatus::Failed;
    }

    /**
     * Extract one normalized follow-up request from the provider output.
     *
     * @param  array<string, mixed>  $providerResult
     * @return array<string, mixed>
     */
    protected function extractFollowUpRequest(array $providerResult): array
    {
        return $this->followUpWorkflowService->extractFollowUpRequest($providerResult);
    }

    /**
     * Build the default skipped validation result payload.
     *
     * @return array<string, mixed>
     */
    protected function buildSkippedValidationResult(): array
    {
        return [
            'requested' => false,
            'overall_status' => 'skipped',
            'total_commands' => 0,
            'failed_commands' => 0,
            'groups' => [],
            'commands' => [],
            'summary' => 'Validation commands were not requested.',
            'can_retry' => false,
            'completion_ready' => true,
        ];
    }
}
