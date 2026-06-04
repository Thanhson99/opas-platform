<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Enums\AutoCodingTaskPurgeScope;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
use App\Support\RepositoryPathMatcher;
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
        private readonly \App\Services\AutoCoding\Telegram\AutoCodingTelegramNotificationService $telegramNotificationService,
        private readonly AutoCodingTaskRepositoryInterface $taskRepository,
        private readonly AutoCodingExecutionContextService $executionContextService,
        private readonly AutoCodingExecutionStateService $executionStateService,
        private readonly AutoCodingFollowUpRequestService $followUpRequestService,
        private readonly AutoCodingFollowUpResponseService $followUpResponseService,
        private readonly AutoCodingFollowUpWorkflowService $followUpWorkflowService,
        private readonly AutoCodingWorkflowStepRunnerService $workflowStepRunnerService,
        private readonly AutoCodingMachineRoutingService $machineRoutingService,
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
     * @param  AutoCodingMachine|null  $executionMachine
     * @return AutoCodingTaskRun
     */
    public function executePendingTask(int $taskId, ?AutoCodingMachine $executionMachine = null): AutoCodingTaskRun
    {
        $task = $this->findTaskOrFail($taskId);
        $executionContext = $this->executionContextService->buildExecutionContext($task);
        $machine = $executionMachine instanceof AutoCodingMachine
            ? $executionMachine
            : $this->localMachineService->resolve($executionContext['repository_path']);
        $this->assertTaskCanRunOnMachine($task, $machine);
        $run = $this->executionStateService->createRunningTaskRun($task, $machine->id, [
            'repository_path' => $executionContext['repository_path'],
        ]);

        try {
            $cancellationRun = $this->finalizeCancellationIfRequested($task, $run, $machine->machine_key);

            if ($cancellationRun instanceof AutoCodingTaskRun) {
                return $cancellationRun;
            }

            $repositoryContext = $this->workflowStepRunnerService->runRepositoryInspectionStep(
                $run,
                $executionContext['repository_path']
            );
            $this->executionStateService->markTaskAsRunning($task, $executionContext['task_context'], $repositoryContext);
            $run->update([
                'repository_snapshot' => $repositoryContext,
            ]);

            $cancellationRun = $this->finalizeCancellationIfRequested(
                $task,
                $run,
                $machine->machine_key,
                $repositoryContext
            );

            if ($cancellationRun instanceof AutoCodingTaskRun) {
                return $cancellationRun;
            }

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

            $cancellationRun = $this->finalizeCancellationIfRequested(
                $task,
                $run,
                $machine->machine_key,
                $repositoryContext
            );

            if ($cancellationRun instanceof AutoCodingTaskRun) {
                return $cancellationRun;
            }

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
     * Ensure a routed task only executes on its assigned machine.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingMachine  $machine
     * @return void
     */
    protected function assertTaskCanRunOnMachine(AutoCodingTask $task, AutoCodingMachine $machine): void
    {
        if ($task->assigned_machine_id === null || (int) $task->assigned_machine_id === (int) $machine->id) {
            return;
        }

        throw ValidationException::withMessages([
            'machine' => 'This auto-coding task is assigned to another machine.',
        ]);
    }

    /**
     * Claim the oldest pending local auto-coding task for one repository path.
     *
     * @param  string|null  $repositoryPath
     * @param  AutoCodingMachine|null  $machine
     * @return AutoCodingTask|null
     */
    public function claimNextPendingTask(?string $repositoryPath = null, ?AutoCodingMachine $machine = null): ?AutoCodingTask
    {
        return DB::transaction(function () use ($repositoryPath, $machine): ?AutoCodingTask {
            $claimMachine = $this->lockClaimMachine($machine);

            if ($machine instanceof AutoCodingMachine && ! $claimMachine instanceof AutoCodingMachine) {
                return null;
            }

            $task = $this->resolveClaimablePendingTask($repositoryPath, $claimMachine);

            if (! $task instanceof AutoCodingTask) {
                return null;
            }

            $claimableTask = $this->lockClaimableTask($task, $repositoryPath, $claimMachine);

            if (! $claimableTask instanceof AutoCodingTask) {
                return null;
            }

            $assignedMachineId = $claimMachine instanceof AutoCodingMachine
                ? $claimMachine->id
                : $claimableTask->assigned_machine_id;
            $claimableTask->update([
                'status' => AutoCodingExecutionStatus::Running,
                'assigned_machine_id' => $assignedMachineId,
                'claimed_at' => now(),
                'latest_report' => $this->buildClaimedLatestReport($claimableTask, $claimMachine),
            ]);

            $claimedTask = $this->taskRepository->findDetailedById($claimableTask->id);

            if ($claimedTask instanceof AutoCodingTask) {
                $this->telegramNotificationService->notifyRunning($claimedTask);
            }

            return $claimedTask;
        });
    }

    /**
     * Lock and reload the claiming machine before capacity-sensitive task selection.
     *
     * @param  AutoCodingMachine|null  $machine
     * @return AutoCodingMachine|null
     */
    protected function lockClaimMachine(?AutoCodingMachine $machine): ?AutoCodingMachine
    {
        if (! $machine instanceof AutoCodingMachine) {
            return null;
        }

        /** @var AutoCodingMachine|null $claimMachine */
        $claimMachine = AutoCodingMachine::query()
            ->whereKey($machine->id)
            ->lockForUpdate()
            ->first();

        if (! $claimMachine instanceof AutoCodingMachine) {
            return null;
        }

        return $this->machineRoutingService->canClaimNewTask($claimMachine)
            ? $claimMachine
            : null;
    }

    /**
     * Lock and revalidate the selected task before claiming it.
     *
     * @param  AutoCodingTask  $task
     * @param  string|null  $repositoryPath
     * @param  AutoCodingMachine|null  $machine
     * @return AutoCodingTask|null
     */
    protected function lockClaimableTask(
        AutoCodingTask $task,
        ?string $repositoryPath,
        ?AutoCodingMachine $machine,
    ): ?AutoCodingTask {
        /** @var AutoCodingTask|null $claimableTask */
        $claimableTask = AutoCodingTask::query()
            ->whereKey($task->id)
            ->lockForUpdate()
            ->first();

        if (! $claimableTask instanceof AutoCodingTask) {
            return null;
        }

        if ($claimableTask->status !== AutoCodingExecutionStatus::Pending) {
            return null;
        }

        if (! $this->claimAssignmentMatches($claimableTask, $machine)) {
            return null;
        }

        if (! $this->claimRepositoryMatches($claimableTask, $repositoryPath, $machine)) {
            return null;
        }

        return $claimableTask;
    }

    /**
     * Determine whether the locked task assignment still permits this claim.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingMachine|null  $machine
     * @return bool
     */
    protected function claimAssignmentMatches(AutoCodingTask $task, ?AutoCodingMachine $machine): bool
    {
        if ($task->assigned_machine_id === null) {
            return true;
        }

        return $machine instanceof AutoCodingMachine
            && (int) $task->assigned_machine_id === (int) $machine->id;
    }

    /**
     * Determine whether the locked task repository still permits this claim.
     *
     * @param  AutoCodingTask  $task
     * @param  string|null  $repositoryPath
     * @param  AutoCodingMachine|null  $machine
     * @return bool
     */
    protected function claimRepositoryMatches(
        AutoCodingTask $task,
        ?string $repositoryPath,
        ?AutoCodingMachine $machine,
    ): bool {
        $requestedRepositoryPath = is_string($repositoryPath) ? trim($repositoryPath) : '';

        if ($requestedRepositoryPath !== '' && ! RepositoryPathMatcher::matches($task->repository_path, $requestedRepositoryPath)) {
            return false;
        }

        if (! $machine instanceof AutoCodingMachine) {
            return true;
        }

        return $this->machineRoutingService->machineMatchesRepository($machine, $task->repository_path);
    }

    /**
     * Resolve the next pending task a machine can claim, rerouting stale assignments when possible.
     *
     * @param  string|null  $repositoryPath
     * @param  AutoCodingMachine|null  $machine
     * @return AutoCodingTask|null
     */
    protected function resolveClaimablePendingTask(
        ?string $repositoryPath,
        ?AutoCodingMachine $machine,
    ): ?AutoCodingTask {
        if (! $machine instanceof AutoCodingMachine) {
            return $this->taskRepository->findOldestPending($repositoryPath);
        }

        $task = $this->taskRepository->findOldestPendingForMachine($machine, $repositoryPath);

        if ($task instanceof AutoCodingTask) {
            return $task;
        }

        foreach ($this->taskRepository->getOldestPendingAssignedOutsideMachine($machine, $repositoryPath) as $assignedTask) {
            $reroutedTask = $this->machineRoutingService->rerouteIfAssignedMachineUnavailable($assignedTask);

            if ((int) $reroutedTask->assigned_machine_id === (int) $machine->id) {
                return $reroutedTask;
            }
        }

        return null;
    }

    /**
     * Build a claimed report and include machine routing context when available.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingMachine|null  $machine
     * @return array<string, mixed>
     */
    protected function buildClaimedLatestReport(AutoCodingTask $task, ?AutoCodingMachine $machine): array
    {
        $latestReport = $this->queueStateService->buildClaimedLatestReport($task);

        if (! $machine instanceof AutoCodingMachine) {
            return $latestReport;
        }

        $existingRouting = is_array($latestReport['routing'] ?? null)
            ? $latestReport['routing']
            : [];
        $contextRouting = is_array($task->context_payload['routing'] ?? null)
            ? $task->context_payload['routing']
            : [];

        return array_merge($latestReport, [
            'routing' => array_merge($contextRouting, $existingRouting, [
                'status' => 'claimed',
                'assigned_machine_id' => $machine->id,
                'assigned_machine_key' => $machine->machine_key,
                'claimed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Cancel one queued or active task by id.
     *
     * Running tasks are marked as cancellation-requested and will stop at the next safe checkpoint.
     *
     * @param  int  $taskId
     * @return AutoCodingTask
     */
    public function cancelTask(int $taskId): AutoCodingTask
    {
        $task = $this->findTaskOrFail($taskId);
        $status = $task->status;
        $contextPayload = is_array($task->context_payload) ? $task->context_payload : [];

        if (in_array($status, [AutoCodingExecutionStatus::Completed, AutoCodingExecutionStatus::Failed, AutoCodingExecutionStatus::Cancelled], true)) {
            return $task;
        }

        if ($status === AutoCodingExecutionStatus::Running) {
            $task->update([
                'context_payload' => array_merge($contextPayload, [
                    'cancellation_requested_at' => now()->toIso8601String(),
                ]),
                'latest_report' => array_merge($task->latest_report ?? [], [
                    'queue' => array_merge($this->queueStateService->resolveQueueReport($task), [
                        'status' => 'cancellation_requested',
                        'cancellation_requested_at' => now()->toIso8601String(),
                    ]),
                ]),
            ]);

            /** @var AutoCodingTask $freshTask */
            $freshTask = $task->fresh();

            return $freshTask;
        }

        $task->update([
            'status' => AutoCodingExecutionStatus::Cancelled,
            'latest_report' => $this->queueStateService->buildCancelledLatestReport($task),
            'completed_at' => now(),
            'context_payload' => array_merge($contextPayload, [
                'cancelled_at' => now()->toIso8601String(),
            ]),
        ]);

        /** @var AutoCodingTask $freshTask */
        $freshTask = $task->fresh();

        return $freshTask;
    }

    /**
     * Cancel every active task (pending, running, blocked).
     *
     * @return array{cancelled_count:int,cancellation_requested_count:int,unchanged_count:int}
     */
    public function cancelActiveTasks(): array
    {
        $tasks = AutoCodingTask::query()
            ->whereIn('status', [
                AutoCodingExecutionStatus::Pending->value,
                AutoCodingExecutionStatus::Running->value,
                AutoCodingExecutionStatus::Blocked->value,
            ])
            ->orderBy('id')
            ->get();

        $cancelledCount = 0;
        $cancellationRequestedCount = 0;
        $unchangedCount = 0;

        foreach ($tasks as $task) {
            $beforeStatus = $task->status;
            $updatedTask = $this->cancelTask((int) $task->id);

            if ($beforeStatus === AutoCodingExecutionStatus::Running) {
                $cancellationRequestedCount++;

                continue;
            }

            if ($updatedTask->status === AutoCodingExecutionStatus::Cancelled) {
                $cancelledCount++;
            } else {
                $unchangedCount++;
            }
        }

        return [
            'cancelled_count' => $cancelledCount,
            'cancellation_requested_count' => $cancellationRequestedCount,
            'unchanged_count' => $unchangedCount,
        ];
    }

    /**
     * Permanently delete one pending task from persistence.
     *
     * This is stricter than cancel: only pending tasks can be removed directly so
     * operators do not delete active or historical execution records by accident.
     *
     * @param  int  $taskId
     * @return array{id:int,summary:string}
     */
    public function deletePendingTask(int $taskId): array
    {
        $task = $this->findTaskOrFail($taskId);

        if ($task->status !== AutoCodingExecutionStatus::Pending) {
            throw ValidationException::withMessages([
                'task_id' => 'Only pending tasks can be deleted permanently.',
            ]);
        }

        $result = [
            'id' => (int) $task->id,
            'summary' => (string) $task->summary,
        ];

        $task->delete();

        return $result;
    }

    /**
     * Permanently delete all pending tasks from persistence.
     *
     * @return array{deleted_count:int,scope:string}
     */
    public function deletePendingTasks(): array
    {
        /** @var int $deletedCount */
        $deletedCount = AutoCodingTask::query()
            ->where('status', AutoCodingExecutionStatus::Pending->value)
            ->delete();

        return [
            'deleted_count' => $deletedCount,
            'scope' => 'pending',
        ];
    }

    /**
     * Purge terminal tasks from persistence history.
     *
     * @param  string  $scope
     * @return array{deleted_count:int,scope:string}
     */
    public function purgeTasks(string $scope = 'terminal'): array
    {
        $effectiveScope = AutoCodingTaskPurgeScope::fromMixed($scope);

        $query = AutoCodingTask::query();

        if ($effectiveScope === AutoCodingTaskPurgeScope::Terminal) {
            $query->whereIn('status', AutoCodingExecutionStatus::terminalValues());
        }

        /** @var int $deletedCount */
        $deletedCount = $query->delete();

        return [
            'deleted_count' => $deletedCount,
            'scope' => $effectiveScope->value,
        ];
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
     * Finalize one running execution as cancelled when operator cancellation was requested.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  string  $machineKey
     * @param  array<string, mixed>|null  $repositoryContext
     * @return AutoCodingTaskRun|null
     */
    protected function finalizeCancellationIfRequested(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        string $machineKey,
        ?array $repositoryContext = null,
    ): ?AutoCodingTaskRun {
        /** @var AutoCodingTask|null $freshTask */
        $freshTask = AutoCodingTask::query()->find($task->id);

        if (! $freshTask instanceof AutoCodingTask) {
            return null;
        }

        $contextPayload = is_array($freshTask->context_payload) ? $freshTask->context_payload : [];
        $cancellationRequestedAt = $contextPayload['cancellation_requested_at'] ?? null;

        if (! is_string($cancellationRequestedAt) || trim($cancellationRequestedAt) === '') {
            return null;
        }

        $cancellationContext = is_array($repositoryContext)
            ? $repositoryContext
            : $run->repository_snapshot;

        return $this->executionStateService->finalizeTerminalExecution(
            $freshTask,
            $run,
            AutoCodingExecutionStatus::Cancelled,
            $machineKey,
            $cancellationContext,
            [
                'prompt_package' => [],
                'provider_result' => [
                    'status' => 'cancelled',
                    'message' => 'Execution cancelled by operator request.',
                ],
                'github_context' => [],
                'validation_results' => $this->buildSkippedValidationResult(),
            ],
            ['required' => false, 'questions' => []],
            'Execution cancelled by operator request.'
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
