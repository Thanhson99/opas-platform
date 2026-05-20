<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Repositories\AutoCoding\Interfaces\AutoCodingTaskRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
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
     * @return AutoCodingTask
     */
    public function createPendingTask(
        string $summary,
        ?string $issueKey = null,
        ?string $repositoryPath = null,
        bool $shouldRunValidation = false,
        ?string $providerName = null,
        array $providerOptions = [],
    ): AutoCodingTask {
        $effectiveRepositoryPath = $this->resolveRequestedRepositoryPath($repositoryPath);
        $pendingReport = $this->buildPendingReport($summary, $issueKey, $effectiveRepositoryPath);

        /** @var AutoCodingTask $task */
        $task = DB::transaction(function () use (
            $summary,
            $issueKey,
            $effectiveRepositoryPath,
            $shouldRunValidation,
            $providerName,
            $providerOptions,
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
                ],
                'latest_report' => $pendingReport,
            ]);

            return $createdTask;
        });

        return $task;
    }

    public function __construct(
        private readonly RepositoryContextService $repositoryContextService,
        private readonly LocalMachineService $localMachineService,
        private readonly ValidationPipelineService $validationPipelineService,
        private readonly GitHubContextService $gitHubContextService,
        private readonly RunArtifactService $runArtifactService,
        private readonly PromptContextAssembler $promptContextAssembler,
        private readonly AutoCodingProviderResolver $providerResolver,
        private readonly AutoCodingTaskRepositoryInterface $taskRepository,
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
     * @return AutoCodingTaskRun
     */
    public function runInspectionTask(
        string $summary,
        ?string $issueKey = null,
        ?string $repositoryPath = null,
        bool $shouldRunValidation = false,
        ?string $providerName = null,
        array $providerOptions = [],
    ): AutoCodingTaskRun {
        $task = $this->createPendingTask(
            $summary,
            $issueKey,
            $repositoryPath,
            $shouldRunValidation,
            $providerName,
            $providerOptions,
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
        $executionContext = $this->buildExecutionContext($task);
        $repositoryContext = $this->repositoryContextService->inspect($executionContext['repository_path']);
        $machine = $this->localMachineService->resolve($repositoryContext['repository_path']);

        $this->markTaskAsRunning($task, $executionContext['task_context'], $repositoryContext);
        $run = $this->createRunningTaskRun($task, $machine->id, $repositoryContext);

        try {
            $executionArtifacts = $this->collectExecutionArtifacts(
                $task,
                $repositoryContext,
                $executionContext['provider_name'],
                $executionContext['provider_options'],
                $executionContext['should_run_validation'],
            );

            return $this->finalizeSuccessfulExecution(
                $task,
                $run,
                $machine->machine_key,
                $repositoryContext,
                $executionArtifacts,
            );
        } catch (Throwable $throwable) {
            $this->markExecutionAsFailed($task, $run, $throwable);
            throw $throwable;
        }
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

            $queueReport = $this->resolveQueueReport($task);
            $updated = AutoCodingTask::query()
                ->whereKey($task->id)
                ->where('status', AutoCodingExecutionStatus::Pending->value)
                ->update([
                    'status' => AutoCodingExecutionStatus::Running,
                    'latest_report' => array_merge($task->latest_report ?? [], [
                        'status' => AutoCodingExecutionStatus::Running->value,
                        'queue' => array_merge($queueReport, [
                            'status' => 'claimed',
                            'claimed_at' => now()->toIso8601String(),
                        ]),
                    ]),
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
     * Build the initial pending report returned before queue execution starts.
     *
     * @param  string  $summary
     * @param  string|null  $issueKey
     * @param  string  $repositoryPath
     * @return array<string, mixed>
     */
    protected function buildPendingReport(string $summary, ?string $issueKey, string $repositoryPath): array
    {
        return [
            'status' => AutoCodingExecutionStatus::Pending->value,
            'task' => [
                'summary' => $summary,
                'issue_key' => $issueKey,
            ],
            'queue' => [
                'status' => 'queued',
            ],
            'repository' => [
                'repository_path' => $repositoryPath,
            ],
            'provider_result' => [
                'status' => 'pending',
            ],
            'validation' => [
                'overall_status' => 'pending',
            ],
            'summary' => [
                'artifact_count' => 0,
            ],
        ];
    }

    /**
     * Resolve the existing queue report block from one local auto-coding task safely.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    protected function resolveQueueReport(AutoCodingTask $task): array
    {
        $latestReport = $task->latest_report;
        $queueReport = is_array($latestReport['queue'] ?? null) ? $latestReport['queue'] : [];

        /** @var array<string, mixed> $queueReport */
        return $queueReport;
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
     * Build the normalized execution context used to run one pending task.
     *
     * @param  AutoCodingTask  $task
     * @return array{
     *   task_context: array<string, mixed>,
     *   repository_path: string,
     *   should_run_validation: bool,
     *   provider_name: string|null,
     *   provider_options: array<string, mixed>
     * }
     */
    protected function buildExecutionContext(AutoCodingTask $task): array
    {
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $providerOptions = is_array($taskContext['provider_options'] ?? null)
            ? $taskContext['provider_options']
            : [];

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
        ];
    }

    /**
     * Normalize one provider-options payload into a string-keyed array.
     *
     * @param  array<int|string, mixed>  $providerOptions
     * @return array<string, mixed>
     */
    protected function normalizeProviderOptions(array $providerOptions): array
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
     * Mark one local auto-coding task as running with refreshed repository context.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $taskContext
     * @param  array<string, mixed>  $repositoryContext
     * @return void
     */
    protected function markTaskAsRunning(
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
    protected function createRunningTaskRun(
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
     * Collect provider, GitHub, validation, and report artifacts for one execution attempt.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $repositoryContext
     * @param  string|null  $providerName
     * @param  array<string, mixed>  $providerOptions
     * @param  bool  $shouldRunValidation
     * @return array{
     *   prompt_package: array<string, mixed>,
     *   provider_result: array<string, mixed>,
     *   github_context: array<string, mixed>,
     *   validation_results: array<string, mixed>
     * }
     */
    protected function collectExecutionArtifacts(
        AutoCodingTask $task,
        array $repositoryContext,
        ?string $providerName,
        array $providerOptions,
        bool $shouldRunValidation,
    ): array {
        $providerContext = $this->buildProviderContext($task, $repositoryContext, $providerOptions);
        $provider = $this->providerResolver->resolve($providerName);
        $promptPackage = $this->promptContextAssembler->assemble($providerContext);
        $providerResult = $provider->plan($providerContext);
        $repositoryPath = $this->resolveRepositoryPathFromContext($repositoryContext);
        $branchName = $this->resolveBranchNameFromContext($repositoryContext);

        return [
            'prompt_package' => $promptPackage,
            'provider_result' => $providerResult,
            'github_context' => $this->gitHubContextService->inspect(
                $repositoryPath,
                $branchName,
                $task->issue_key
            ),
            'validation_results' => $this->validationPipelineService->run(
                $repositoryPath,
                $shouldRunValidation
            ),
        ];
    }

    /**
     * Build the provider context payload for one local auto-coding execution.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $repositoryContext
     * @param  array<string, mixed>  $providerOptions
     * @return array<string, mixed>
     */
    protected function buildProviderContext(
        AutoCodingTask $task,
        array $repositoryContext,
        array $providerOptions,
    ): array {
        return [
            'task_summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'repository_context' => $repositoryContext,
            'provider_options' => $providerOptions,
        ];
    }

    /**
     * Finalize one successful local auto-coding execution and persist reports and artifacts.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  string  $machineKey
     * @param  array<string, mixed>  $repositoryContext
     * @param  array{
     *   prompt_package: array<string, mixed>,
     *   provider_result: array<string, mixed>,
     *   github_context: array<string, mixed>,
     *   validation_results: array<string, mixed>
     * }  $executionArtifacts
     * @return AutoCodingTaskRun
     */
    protected function finalizeSuccessfulExecution(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        string $machineKey,
        array $repositoryContext,
        array $executionArtifacts,
    ): AutoCodingTaskRun {
        $report = $this->buildFinalReport(
            $task,
            $run,
            $machineKey,
            $executionArtifacts['provider_result'],
            $executionArtifacts['validation_results'],
            $executionArtifacts['github_context'],
        );

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
            'status' => AutoCodingExecutionStatus::Completed,
            'changed_files' => $repositoryContext['changed_files'],
            'provider_result' => $executionArtifacts['provider_result'],
            'validation_results' => $executionArtifacts['validation_results'],
            'final_report' => $report,
            'completed_at' => now(),
        ]);

        $task->update([
            'status' => AutoCodingExecutionStatus::Completed,
            'latest_report' => $report,
            'completed_at' => now(),
        ]);

        /** @var AutoCodingTaskRun $freshRun */
        $freshRun = $run->fresh();

        return $freshRun;
    }

    /**
     * Mark one local auto-coding execution as failed and persist the failure report.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  Throwable  $throwable
     * @return void
     */
    protected function markExecutionAsFailed(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        Throwable $throwable,
    ): void {
        $failureReport = [
            'status' => AutoCodingExecutionStatus::Failed->value,
            'error' => $throwable->getMessage(),
        ];

        $run->update([
            'status' => AutoCodingExecutionStatus::Failed,
            'final_report' => $failureReport,
            'completed_at' => now(),
        ]);

        $task->update([
            'status' => AutoCodingExecutionStatus::Failed,
            'latest_report' => $failureReport,
            'completed_at' => now(),
        ]);
    }

    /**
     * Resolve the repository path from one inspected repository context.
     *
     * @param  array<string, mixed>  $repositoryContext
     * @return string
     */
    protected function resolveRepositoryPathFromContext(array $repositoryContext): string
    {
        $repositoryPath = $repositoryContext['repository_path'] ?? null;

        return is_string($repositoryPath) ? $repositoryPath : base_path('..');
    }

    /**
     * Resolve the branch name from one inspected repository context.
     *
     * @param  array<string, mixed>  $repositoryContext
     * @return string|null
     */
    protected function resolveBranchNameFromContext(array $repositoryContext): ?string
    {
        $branchName = $repositoryContext['branch_name'] ?? null;

        return is_string($branchName) ? $branchName : null;
    }

    /**
     * Build the structured final report for one local task run.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  string  $machineKey
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, mixed>  $validationResults
     * @param  array<string, mixed>  $gitHubContext
     * @return array<string, mixed>
     */
    protected function buildFinalReport(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        string $machineKey,
        array $providerResult,
        array $validationResults,
        array $gitHubContext,
    ): array {
        return [
            'task' => [
                'id' => $task->getKey(),
                'summary' => $task->summary,
                'issue_key' => $task->issue_key,
                'status' => AutoCodingExecutionStatus::Completed->value,
            ],
            'run' => [
                'id' => $run->getKey(),
                'status' => AutoCodingExecutionStatus::Completed->value,
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
            'validation' => $validationResults,
            'summary' => [
                'artifact_count' => 5,
                'changed_file_count' => count($this->resolveChangedFiles($run)),
                'is_dirty' => (bool) ($run->repository_snapshot['is_dirty'] ?? false),
            ],
        ];
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
