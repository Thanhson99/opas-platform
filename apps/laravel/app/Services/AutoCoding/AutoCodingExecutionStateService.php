<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Enums\AutoCodingWorkflowStep;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramNotificationService;

/**
 * Persist task and run state transitions for auto-coding workflow executions.
 */
class AutoCodingExecutionStateService
{
    public function __construct(
        private readonly AutoCodingCompletionChecklistService $completionChecklistService,
        private readonly AutoCodingExecutionContextService $executionContextService,
        private readonly AutoCodingTelegramNotificationService $telegramNotificationService,
        private readonly AutoCodingWorkflowReportService $workflowReportService,
        private readonly AutoCodingWorkflowTracker $workflowTracker,
        private readonly RunArtifactService $runArtifactService,
    ) {}

    /**
     * Mark one local auto-coding task as running with refreshed repository context.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $taskContext
     * @param  array<string, mixed>  $repositoryContext
     * @return void
     */
    public function markTaskAsRunning(
        AutoCodingTask $task,
        array $taskContext,
        array $repositoryContext,
    ): void {
        $task->update([
            'repository_path' => $repositoryContext['repository_path'],
            'branch_name' => $repositoryContext['branch_name'],
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => array_merge($taskContext, [
                'repository_context' => $repositoryContext,
            ]),
            'completed_at' => null,
        ]);
    }

    /**
     * Create one running task-run record for the current execution attempt.
     *
     * @param  AutoCodingTask  $task
     * @param  int  $machineId
     * @param  array<string, mixed>  $repositoryContext
     * @return AutoCodingTaskRun
     */
    public function createRunningTaskRun(
        AutoCodingTask $task,
        int $machineId,
        array $repositoryContext,
    ): AutoCodingTaskRun {
        /** @var AutoCodingTaskRun $run */
        $run = AutoCodingTaskRun::query()->create([
            'task_id' => $task->getKey(),
            'machine_id' => $machineId,
            'status' => AutoCodingExecutionStatus::Running,
            'repository_snapshot' => $repositoryContext,
            'started_at' => now(),
        ]);

        return $run;
    }

    /**
     * Finalize one terminal execution outcome and persist reports and artifacts.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  AutoCodingExecutionStatus  $status
     * @param  string  $machineKey
     * @param  array<string, mixed>  $repositoryContext
     * @param  array{
     *   prompt_package: array<string, mixed>,
     *   provider_result: array<string, mixed>,
     *   github_context: array<string, mixed>,
     *   validation_results: array<string, mixed>
     * }  $executionArtifacts
     * @param  array<string, mixed>  $followUp
     * @param  string|null  $errorMessage
     * @return AutoCodingTaskRun
     */
    public function finalizeTerminalExecution(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        AutoCodingExecutionStatus $status,
        string $machineKey,
        array $repositoryContext,
        array $executionArtifacts,
        array $followUp,
        ?string $errorMessage,
    ): AutoCodingTaskRun {
        $stepRecord = $this->workflowTracker->startStep(
            $run,
            AutoCodingWorkflowStep::CompletionCheck,
            1,
            false,
            ['status' => $status->value],
        );

        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $report = $this->workflowReportService->buildFinalReport(
            $task,
            $run,
            $machineKey,
            $status,
            $executionArtifacts['provider_result'],
            $executionArtifacts['validation_results'],
            $executionArtifacts['github_context'],
            $followUp,
            $errorMessage,
            $this->executionContextService->normalizeDirtyWorkspacePolicy($taskContext['dirty_workspace_policy'] ?? null),
            $this->executionContextService->normalizeScopePaths($taskContext['scope_paths'] ?? []),
            $this->executionContextService->normalizeScopePolicy($taskContext['scope_policy'] ?? null),
        );
        $checklist = $this->completionChecklistService->build(
            $task->forceFill(['status' => $status]),
            $run->forceFill(['status' => $status]),
            $executionArtifacts['provider_result'],
            $executionArtifacts['validation_results'],
            $report,
        );
        $report['completion'] = $checklist;

        if ($status === AutoCodingExecutionStatus::Blocked) {
            $this->workflowTracker->blockStep(
                $stepRecord,
                $errorMessage ?? 'Follow-up input is required.',
                $checklist
            );
        } elseif ($status === AutoCodingExecutionStatus::Failed) {
            $this->workflowTracker->failStep(
                $stepRecord,
                $errorMessage ?? 'Validation or provider checks failed.',
                $checklist
            );
        } else {
            $this->workflowTracker->completeStep($stepRecord, $checklist);
        }

        $report['workflow'] = $this->workflowReportService->buildWorkflowReport($run->fresh(['steps']) ?? $run);

        $this->runArtifactService->persistRunArtifacts(
            $run,
            $repositoryContext,
            $executionArtifacts['github_context'],
            array_merge($executionArtifacts['provider_result'], [
                'prompt_package' => $executionArtifacts['prompt_package'],
            ]),
            $executionArtifacts['validation_results'],
            $report
        );

        $run->update([
            'status' => $status,
            'changed_files' => $repositoryContext['changed_files'] ?? [],
            'provider_result' => $executionArtifacts['provider_result'],
            'validation_results' => $executionArtifacts['validation_results'],
            'final_report' => $report,
            'completed_at' => now(),
        ]);

        $task->update([
            'status' => $status,
            'latest_report' => $report,
            'completed_at' => now(),
        ]);

        /** @var AutoCodingTaskRun $freshRun */
        $freshRun = $run->fresh(['artifacts', 'steps']);
        /** @var AutoCodingTask $freshTask */
        $freshTask = $task->fresh(['runs']);

        $this->telegramNotificationService->notifyOutcome($freshTask, $freshRun);

        return $freshRun;
    }
}
