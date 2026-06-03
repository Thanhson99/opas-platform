<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingWorkflowStep;
use App\Models\AutoCodingTaskRun;
use Throwable;

/**
 * Execute persisted workflow steps for local auto-coding task runs.
 */
class AutoCodingWorkflowStepRunnerService
{
    public function __construct(
        private readonly AutoCodingProviderResolver $providerResolver,
        private readonly AutoCodingWorkflowTracker $workflowTracker,
        private readonly GitHubContextService $gitHubContextService,
        private readonly PromptContextAssembler $promptContextAssembler,
        private readonly RepositoryContextService $repositoryContextService,
        private readonly ValidationPipelineService $validationPipelineService,
    ) {}

    /**
     * Run the repository inspection step and persist the workflow attempt.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  string  $repositoryPath
     * @return array<string, mixed>
     */
    public function runRepositoryInspectionStep(AutoCodingTaskRun $run, string $repositoryPath): array
    {
        /** @var array<string, mixed> $repositoryContext */
        $repositoryContext = $this->executeWorkflowStep(
            $run,
            AutoCodingWorkflowStep::InspectRepository,
            false,
            ['repository_path' => $repositoryPath],
            fn (): array => $this->repositoryContextService->inspect($repositoryPath)
        );

        return $repositoryContext;
    }

    /**
     * Run the prompt preparation step and persist the workflow attempt.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $providerContext
     * @return array<string, mixed>
     */
    public function runPromptPreparationStep(AutoCodingTaskRun $run, array $providerContext): array
    {
        /** @var array<string, mixed> $promptPackage */
        $promptPackage = $this->executeWorkflowStep(
            $run,
            AutoCodingWorkflowStep::PreparePrompt,
            false,
            ['task_summary' => $providerContext['task_summary'] ?? null],
            fn (): array => $this->promptContextAssembler->assemble($providerContext)
        );

        return $promptPackage;
    }

    /**
     * Run the provider planning step and persist the workflow attempt.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $providerContext
     * @param  string|null  $providerName
     * @return array<string, mixed>
     */
    public function runProviderStep(
        AutoCodingTaskRun $run,
        array $providerContext,
        ?string $providerName,
    ): array {
        $provider = $this->providerResolver->resolve($providerName);

        /** @var array<string, mixed> $providerResult */
        $providerResult = $this->executeWorkflowStep(
            $run,
            AutoCodingWorkflowStep::ProviderPlan,
            false,
            ['provider' => $provider->name()],
            fn (): array => $provider->plan($providerContext)
        );

        return $providerResult;
    }

    /**
     * Run the GitHub context step and persist the workflow attempt.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $repositoryContext
     * @param  string|null  $issueKey
     * @return array<string, mixed>
     */
    public function runGithubContextStep(
        AutoCodingTaskRun $run,
        array $repositoryContext,
        ?string $issueKey,
    ): array {
        $repositoryPath = $this->resolveRepositoryPathFromContext($repositoryContext);
        $branchName = $this->resolveBranchNameFromContext($repositoryContext);

        /** @var array<string, mixed> $gitHubContext */
        $gitHubContext = $this->executeWorkflowStep(
            $run,
            AutoCodingWorkflowStep::CollectGithubContext,
            false,
            ['issue_key' => $issueKey],
            fn (): array => $this->gitHubContextService->inspect($repositoryPath, $branchName, $issueKey)
        );

        return $gitHubContext;
    }

    /**
     * Run the validation step with persisted retries when configured.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $repositoryContext
     * @param  bool  $shouldRunValidation
     * @return array<string, mixed>
     */
    public function runValidationStep(
        AutoCodingTaskRun $run,
        array $repositoryContext,
        bool $shouldRunValidation,
    ): array {
        $repositoryPath = $this->resolveRepositoryPathFromContext($repositoryContext);
        $attempt = 0;
        $configuredRetryLimit = config('opas.auto_coding.workflow.validation_retry_limit');
        $retryLimit = max(1, is_numeric($configuredRetryLimit) ? (int) $configuredRetryLimit : 1);
        $validationResults = $this->buildSkippedValidationResult();

        do {
            $attempt++;
            $stepRecord = $this->workflowTracker->startStep(
                $run,
                AutoCodingWorkflowStep::RunValidation,
                $attempt,
                $shouldRunValidation,
                [
                    'repository_path' => $repositoryPath,
                    'requested' => $shouldRunValidation,
                ],
            );

            $validationResults = $this->validationPipelineService->run($repositoryPath, $shouldRunValidation);
            $this->workflowTracker->completeStep($stepRecord, $validationResults);
        } while (
            $shouldRunValidation
            && $validationResults['overall_status'] === 'failed'
            && $validationResults['can_retry'] === true
            && $attempt < $retryLimit
        );

        return $validationResults;
    }

    /**
     * Execute one generic workflow step and persist one attempt record.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  AutoCodingWorkflowStep  $step
     * @param  bool  $isRetryable
     * @param  array<string, mixed>  $inputPayload
     * @param  callable():mixed  $callback
     * @return mixed
     */
    protected function executeWorkflowStep(
        AutoCodingTaskRun $run,
        AutoCodingWorkflowStep $step,
        bool $isRetryable,
        array $inputPayload,
        callable $callback,
    ): mixed {
        $stepRecord = $this->workflowTracker->startStep($run, $step, 1, $isRetryable, $inputPayload);

        try {
            $result = $callback();

            if (is_array($result)) {
                /** @var array<string, mixed> $result */
                $this->workflowTracker->completeStep($stepRecord, $result);
            } else {
                $this->workflowTracker->completeStep($stepRecord, []);
            }

            return $result;
        } catch (Throwable $throwable) {
            $this->workflowTracker->failStep($stepRecord, $throwable->getMessage());
            throw $throwable;
        }
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
}
