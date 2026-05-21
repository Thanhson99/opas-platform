<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

/**
 * Build normalized follow-up request payloads for provider and preflight workflow gates.
 */
class AutoCodingFollowUpRequestService
{
    public function __construct(
        private readonly AutoCodingFollowUpContractService $followUpContractService,
        private readonly AutoCodingFollowUpQuestionService $followUpQuestionService,
        private readonly AutoCodingWorkflowReportService $workflowReportService,
    ) {}

    /**
     * Build one normalized follow-up request payload before run-specific report hydration.
     *
     * @param  bool  $required
     * @param  string|null  $message
     * @param  mixed  $questions
     * @param  string|null  $reason
     * @param  mixed  $inputContract
     * @return array<string, mixed>
     */
    public function buildRequest(
        bool $required,
        ?string $message,
        mixed $questions,
        ?string $reason,
        mixed $inputContract,
    ): array {
        $normalizedQuestionContracts = $this->followUpQuestionService->normalizeQuestionContracts($questions);

        return [
            'required' => $required,
            'reason' => is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
            'message' => $message,
            'questions' => $this->followUpQuestionService->normalizeQuestionPrompts($questions, $normalizedQuestionContracts),
            'question_contracts' => $normalizedQuestionContracts,
            'input_contract' => $this->followUpContractService->normalizeDefinition($inputContract),
        ];
    }

    /**
     * Build one dirty-workspace follow-up block when the policy requires a stop.
     *
     * @param  array<string, mixed>  $repositoryContext
     * @param  string  $dirtyWorkspacePolicy
     * @return array<string, mixed>
     */
    public function buildDirtyWorkspaceFollowUp(array $repositoryContext, string $dirtyWorkspacePolicy): array
    {
        if ($dirtyWorkspacePolicy !== 'block' || (($repositoryContext['is_dirty'] ?? false) !== true)) {
            return $this->buildRequest(false, null, [], null, null);
        }

        $changedFiles = $repositoryContext['changed_files'] ?? [];
        $changedFileCount = is_array($changedFiles) ? count($changedFiles) : 0;

        return $this->buildRequest(
            true,
            'Repository has local changes, so execution stopped before provider planning.',
            [[
                'id' => 'workspace_confirmation',
                'prompt' => sprintf(
                    'Workspace is dirty with %d changed file(s). Reply to confirm whether this task should proceed on the current workspace.',
                    $changedFileCount
                ),
                'input_type' => 'confirmation',
                'required' => true,
                'help_text' => 'Choose an explicit confirmation value to continue on the current dirty workspace.',
                'accepted_values' => ['allow', 'continue', 'proceed', 'yes'],
                'options' => [
                    ['label' => 'Allow', 'value' => 'allow'],
                    ['label' => 'Continue', 'value' => 'continue'],
                ],
            ]],
            'dirty_workspace',
            [
                'type' => 'confirmation',
                'format' => 'single_text',
                'expected_input' => 'confirm_to_continue',
                'accepted_values' => ['allow', 'continue', 'proceed', 'yes'],
                'free_text_allowed' => false,
                'safe_to_retry' => true,
                'idempotent_while_blocked' => true,
            ],
        );
    }

    /**
     * Build one scope-mismatch follow-up block when changed files exceed task scope.
     *
     * @param  array<string, mixed>  $repositoryContext
     * @param  array<int, string>  $scopePaths
     * @param  string  $scopePolicy
     * @return array<string, mixed>
     */
    public function buildScopeMismatchFollowUp(
        array $repositoryContext,
        array $scopePaths,
        string $scopePolicy,
    ): array {
        $scopeAnalysis = $this->workflowReportService->buildScopeAnalysis(
            $repositoryContext,
            $scopePaths,
            $scopePolicy,
        );

        if ($scopePolicy !== 'block' || $scopeAnalysis['in_scope'] === true || $scopeAnalysis['requested_paths'] === []) {
            return $this->buildRequest(false, null, [], null, null);
        }

        return $this->buildRequest(
            true,
            'Changed files fall outside the requested task scope, so execution stopped before provider planning.',
            [[
                'id' => 'scope_confirmation',
                'prompt' => sprintf(
                    'Out-of-scope changes detected in %d file(s). Reply to confirm whether this task should proceed anyway.',
                    count($scopeAnalysis['out_of_scope_files'])
                ),
                'input_type' => 'confirmation',
                'required' => true,
                'help_text' => 'Choose an explicit confirmation value to continue despite the scope mismatch.',
                'accepted_values' => ['allow', 'continue', 'proceed', 'yes'],
                'options' => [
                    ['label' => 'Allow', 'value' => 'allow'],
                    ['label' => 'Continue', 'value' => 'continue'],
                ],
            ]],
            'scope_mismatch',
            [
                'type' => 'confirmation',
                'format' => 'single_text',
                'expected_input' => 'confirm_to_continue',
                'accepted_values' => ['allow', 'continue', 'proceed', 'yes'],
                'free_text_allowed' => false,
                'safe_to_retry' => true,
                'idempotent_while_blocked' => true,
            ],
        );
    }
}
