<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use Illuminate\Support\Str;

class AutoCodingTelegramTaskReportFormatter
{
    public function __construct(
        private readonly AutoCodingTelegramFormatterSupport $support,
    ) {}

    /**
     * Build the Telegram message shown when one task is queued remotely.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatQueued(AutoCodingTask $task): string
    {
        if ($this->isDirectCodexChatTask($task)) {
            return $this->formatDirectChatQueued($task);
        }

        $taskId = $this->support->resolveModelId($task);
        $repositoryPath = (string) $task->repository_path;
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $shouldRunValidation = ($taskContext['should_run_validation'] ?? false) === true;
        $chatSessionLines = $this->support->buildTaskChatSessionLines($taskContext);

        $lines = array_filter([
            sprintf('%s #%d', $this->support->label('queued_task'), $taskId),
            sprintf('%s: %s', $this->support->label('summary'), $task->summary),
            sprintf('%s: %s', $this->support->label('status'), $this->support->formatStatusValue($task->status->value)),
            $task->issue_key !== null ? sprintf('%s: %s', $this->support->label('issue'), $task->issue_key) : null,
            sprintf('%s: %s', $this->support->label('repository'), $repositoryPath),
            sprintf('%s: %s', $this->support->label('validate'), $this->support->formatBoolean($shouldRunValidation)),
            $this->buildQueuedIssueReuseLine($taskContext),
            $this->buildQueuedProviderLine($taskContext),
            $this->buildQueuedScopeLine($taskContext),
            $this->buildQueuedWorkspacePolicyLine($taskContext),
        ], static fn (?string $line): bool => $line !== null);

        if ($chatSessionLines !== []) {
            array_push($lines, ...$chatSessionLines);
        }

        return implode("\n", $lines);
    }

    /**
     * Build one queued-task line that explains issue-context reuse when available.
     *
     * @param  array<string, mixed>  $taskContext
     * @return string|null
     */
    protected function buildQueuedIssueReuseLine(array $taskContext): ?string
    {
        $issueContext = is_array($taskContext['issue_context'] ?? null) ? $taskContext['issue_context'] : [];
        $issueEnrichment = is_array($taskContext['issue_enrichment'] ?? null) ? $taskContext['issue_enrichment'] : [];
        $reusedFields = is_array($issueEnrichment['reused_fields'] ?? null) ? $issueEnrichment['reused_fields'] : [];
        $sourceTaskId = $issueContext['source_task_id'] ?? null;

        if (! is_numeric($sourceTaskId) || $reusedFields === []) {
            return null;
        }

        return sprintf(
            '%s: %s',
            $this->support->label('source_task'),
            $this->support->text('phrases.queued_reused_issue_context', [
                'task_id' => (int) $sourceTaskId,
            ])
        );
    }

    /**
     * Build one queued-task provider line when a provider hint is already known.
     *
     * @param  array<string, mixed>  $taskContext
     * @return string|null
     */
    protected function buildQueuedProviderLine(array $taskContext): ?string
    {
        if (! $this->queuedFieldWasReused($taskContext, 'provider') && ! $this->queuedFieldWasReused($taskContext, 'provider_options')) {
            return null;
        }

        $providerName = is_string($taskContext['provider_name'] ?? null)
            ? trim((string) $taskContext['provider_name'])
            : '';
        $providerOptions = is_array($taskContext['provider_options'] ?? null)
            ? $taskContext['provider_options']
            : [];
        $model = is_string($providerOptions['model'] ?? null) ? trim((string) $providerOptions['model']) : '';

        if ($providerName === '') {
            return null;
        }

        $providerValue = $model !== ''
            ? sprintf('%s (%s)', $providerName, $model)
            : $providerName;

        return sprintf('%s: %s', $this->support->label('provider'), $providerValue);
    }

    /**
     * Build one queued-task scope line when reusable scope hints are present.
     *
     * @param  array<string, mixed>  $taskContext
     * @return string|null
     */
    protected function buildQueuedScopeLine(array $taskContext): ?string
    {
        if (! $this->queuedFieldWasReused($taskContext, 'scope_paths')) {
            return null;
        }

        $scopePaths = is_array($taskContext['scope_paths'] ?? null)
            ? array_values(array_filter($taskContext['scope_paths'], 'is_string'))
            : [];

        if ($scopePaths === []) {
            return null;
        }

        $normalizedPaths = array_values(array_filter(array_map(
            static fn (string $path): string => trim($path),
            $scopePaths
        ), static fn (string $path): bool => $path !== ''));

        if ($normalizedPaths === []) {
            return null;
        }

        $scopePreview = implode(', ', array_slice($normalizedPaths, 0, 2));

        if (count($normalizedPaths) > 2) {
            $scopePreview = sprintf(
                '%s, %s',
                $scopePreview,
                $this->support->text('phrases.queued_scope_path_count', [
                    'count' => count($normalizedPaths) - 2,
                ])
            );
        }

        return sprintf('%s: %s', $this->support->label('scope'), $scopePreview);
    }

    /**
     * Build one queued-task workspace policy line when the hint is present.
     *
     * @param  array<string, mixed>  $taskContext
     * @return string|null
     */
    protected function buildQueuedWorkspacePolicyLine(array $taskContext): ?string
    {
        if (! $this->queuedFieldWasReused($taskContext, 'dirty_workspace_policy')) {
            return null;
        }

        $policy = is_string($taskContext['dirty_workspace_policy'] ?? null)
            ? trim((string) $taskContext['dirty_workspace_policy'])
            : '';

        return $policy !== ''
            ? sprintf('%s: %s', $this->support->label('workspace_policy'), $policy)
            : null;
    }

    /**
     * Determine whether one queued-task field was inherited from issue history.
     *
     * @param  array<string, mixed>  $taskContext
     * @param  string  $field
     * @return bool
     */
    protected function queuedFieldWasReused(array $taskContext, string $field): bool
    {
        $issueEnrichment = is_array($taskContext['issue_enrichment'] ?? null) ? $taskContext['issue_enrichment'] : [];
        $reusedFields = is_array($issueEnrichment['reused_fields'] ?? null)
            ? array_values(array_filter($issueEnrichment['reused_fields'], 'is_string'))
            : [];

        return in_array($field, $reusedFields, true);
    }

    /**
     * Build the Telegram message shown when a worker starts executing one task.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun|null  $run
     * @return string
     */
    public function formatRunning(AutoCodingTask $task, ?AutoCodingTaskRun $run = null): string
    {
        if ($this->isDirectCodexChatTask($task)) {
            return $this->formatDirectChatRunning($task);
        }

        $taskId = $this->support->resolveModelId($task);
        $runId = $run instanceof AutoCodingTaskRun ? $this->support->resolveModelId($run) : null;
        $finalReport = $run instanceof AutoCodingTaskRun && is_array($run->final_report) ? $run->final_report : [];
        $reportMachine = is_array($finalReport['machine'] ?? null) ? $finalReport['machine'] : [];
        $machineKey = $run?->machine?->machine_key;
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $chatSessionLines = $this->support->buildTaskChatSessionLines($taskContext);

        if (! is_string($machineKey) || $machineKey === '') {
            $machineKey = is_string($reportMachine['machine_key'] ?? null)
                ? $reportMachine['machine_key']
                : null;
        }

        $workflowLines = $this->support->buildWorkflowProgressLines($finalReport);

        if ($workflowLines === []) {
            $workflowLines = [
                sprintf('%s: %s', $this->support->label('phase'), $this->support->text('phrases.workflow_phase_execution_started')),
                sprintf('%s: %s', $this->support->label('focus'), $this->support->text('phrases.workflow_focus_execution_started')),
            ];
        }

        $lines = array_filter([
            sprintf('%s #%d', $this->support->label('running_task'), $taskId),
            sprintf('%s: %s', $this->support->label('summary'), $task->summary),
            $runId !== null ? sprintf('%s: #%d', $this->support->label('run'), $runId) : null,
            is_string($machineKey) && $machineKey !== '' ? sprintf('%s: %s', $this->support->label('machine'), $machineKey) : null,
        ], static fn (?string $line): bool => $line !== null);

        array_push($lines, ...$workflowLines);

        if ($chatSessionLines !== []) {
            array_push($lines, ...$chatSessionLines);
        }

        return implode("\n", $lines);
    }

    /**
     * Build the Telegram status message for one task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatStatus(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);
        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $recommendedAction = is_array($latestReport['recommended_action'] ?? null)
            ? ($latestReport['recommended_action']['action'] ?? null)
            : null;
        $recommendedReason = is_array($latestReport['recommended_action'] ?? null)
            ? ($latestReport['recommended_action']['reason'] ?? null)
            : null;
        $followUpMessage = is_array($latestReport['follow_up'] ?? null)
            ? ($latestReport['follow_up']['message'] ?? null)
            : null;
        $followUpRequired = (is_array($latestReport['follow_up'] ?? null) ? ($latestReport['follow_up']['required'] ?? false) : false) === true;
        $validationStatus = $this->resolveTaskValidationStatus($task);
        $resumeHint = $this->support->buildResumeHint($taskId, $latestReport);
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $chatSessionLines = $this->support->buildTaskChatSessionLines($taskContext);
        $workflowLines = $this->support->buildWorkflowProgressLines($latestReport);

        $lines = array_filter([
            sprintf('%s #%d', $this->support->label('task'), $taskId),
            sprintf('%s: %s', $this->support->label('status'), $this->support->formatStatusValue($task->status->value)),
            sprintf('%s: %s', $this->support->label('summary'), $task->summary),
            $task->issue_key !== null ? sprintf('%s: %s', $this->support->label('issue'), $task->issue_key) : null,
            $validationStatus !== null ? sprintf('%s: %s', $this->support->label('validation'), $validationStatus) : null,
            is_string($recommendedAction) && $recommendedAction !== ''
                ? sprintf('%s: %s', $this->support->label('next_action'), $recommendedAction)
                : null,
            is_string($recommendedReason) && trim((string) $recommendedReason) !== ''
                ? sprintf('%s: %s', $this->support->label('reason'), trim((string) $recommendedReason))
                : null,
            is_string($followUpMessage) && $followUpMessage !== ''
                ? sprintf('%s: %s', $this->support->label('follow_up'), Str::limit($followUpMessage, 180))
                : null,
            $followUpRequired ? sprintf('%s: %s', $this->support->label('guidance'), $this->support->text('phrases.resume_workflow_continue')) : null,
            $resumeHint,
        ], static fn (?string $line): bool => $line !== null);

        if ($workflowLines !== []) {
            array_push($lines, ...$workflowLines);
        }

        if ($chatSessionLines !== []) {
            array_push($lines, ...$chatSessionLines);
        }

        return implode("\n", $lines);
    }

    /**
     * Build one compact next-action view for a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatNextAction(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);
        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $recommendedAction = is_array($latestReport['recommended_action'] ?? null)
            ? $latestReport['recommended_action']
            : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];
        $action = is_string($recommendedAction['action'] ?? null) ? trim((string) $recommendedAction['action']) : '';
        $reason = is_string($recommendedAction['reason'] ?? null) ? trim((string) $recommendedAction['reason']) : '';
        $message = is_string($followUp['message'] ?? null)
            ? trim((string) $followUp['message'])
            : '';

        if ($action === '' && $message === '') {
            return implode("\n", [
                sprintf('%s #%d', $this->support->label('next_action'), $taskId),
                $this->support->text('phrases.no_next_action_recorded'),
            ]);
        }

        return implode("\n", array_filter([
            sprintf('%s #%d', $this->support->label('next_action'), $taskId),
            $action !== '' ? sprintf('%s: %s', $this->support->label('next_action'), $action) : null,
            $reason !== '' ? sprintf('%s: %s', $this->support->label('reason'), $reason) : null,
            $message !== '' ? sprintf('%s: %s', $this->support->label('guidance'), Str::limit($message, 220)) : null,
        ], static fn (?string $line): bool => $line !== null));
    }

    /**
     * Build one focused follow-up contract view for blocked or input-driven tasks.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatFollowUp(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);
        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];
        $questionContracts = is_array($followUp['question_contracts'] ?? null)
            ? array_values(array_filter($followUp['question_contracts'], 'is_array'))
            : [];
        /** @var array<string, mixed> $inputContract */
        $inputContract = is_array($followUp['input_contract'] ?? null) ? $followUp['input_contract'] : [];

        if (($followUp['required'] ?? false) !== true) {
            return implode("\n", [
                sprintf('%s #%d', $this->support->label('follow_up'), $taskId),
                $this->support->text('phrases.no_follow_up_required'),
            ]);
        }

        $lines = [
            sprintf('%s #%d', $this->support->label('follow_up'), $taskId),
        ];

        if (is_string($followUp['message'] ?? null) && trim((string) $followUp['message']) !== '') {
            $lines[] = sprintf('%s: %s', $this->support->label('follow_up'), trim((string) $followUp['message']));
        }

        if (is_string($inputContract['type'] ?? null) && trim((string) $inputContract['type']) !== '') {
            $lines[] = sprintf('%s: %s', $this->support->label('input_type'), trim((string) $inputContract['type']));
        }

        $acceptedValuesLine = $this->buildAcceptedValuesLine($inputContract);

        if ($acceptedValuesLine !== null) {
            $lines[] = $acceptedValuesLine;
        }

        foreach (array_slice($questionContracts, 0, 3) as $questionContract) {
            $questionId = is_string($questionContract['id'] ?? null) ? trim((string) $questionContract['id']) : '';
            $prompt = is_string($questionContract['prompt'] ?? null) ? trim((string) $questionContract['prompt']) : '';

            if ($questionId === '' && $prompt === '') {
                continue;
            }

            $lines[] = sprintf(
                '%s: %s',
                $questionId !== '' ? $questionId : $this->support->label('question'),
                $prompt !== '' ? $prompt : $this->support->text('phrases.provide_value')
            );
        }

        $resumeHint = $this->support->buildResumeHint($taskId, $latestReport);

        if ($resumeHint !== null) {
            $lines[] = $resumeHint;
        }

        return implode("\n", $lines);
    }

    /**
     * Build one validation-focused summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatValidation(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);
        $run = $this->support->resolveLatestRun($task);

        if (! $run instanceof AutoCodingTaskRun) {
            return sprintf(
                '%s #%d %s',
                $this->support->label('task'),
                $taskId,
                $this->support->text('phrases.no_validation_run')
            );
        }

        $validation = is_array($run->validation_results) ? $run->validation_results : [];
        $summary = is_string($validation['summary'] ?? null) ? trim((string) $validation['summary']) : '';
        $overallStatus = is_string($validation['overall_status'] ?? null) ? trim((string) $validation['overall_status']) : 'skipped';
        $failedCommands = is_numeric($validation['failed_commands'] ?? null) ? (int) $validation['failed_commands'] : 0;
        $totalCommands = is_numeric($validation['total_commands'] ?? null) ? (int) $validation['total_commands'] : 0;

        return implode("\n", array_filter([
            sprintf('%s #%d', $this->support->label('validation_for_task'), $taskId),
            sprintf('%s: %s', $this->support->label('status'), $this->support->formatStatusValue($overallStatus)),
            sprintf('%s: %d', $this->support->label('total_commands'), $totalCommands),
            sprintf('%s: %d', $this->support->label('failed_commands'), $failedCommands),
            $summary !== '' ? sprintf('%s: %s', $this->support->label('details'), $summary) : null,
        ], static fn (?string $line): bool => $line !== null));
    }

    /**
     * Build one GitHub-focused status snapshot for a task.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $githubContext
     * @return string
     */
    public function formatGithubStatus(AutoCodingTask $task, array $githubContext): string
    {
        $taskId = $this->support->resolveModelId($task);
        /** @var array<string, mixed> $issue */
        $issue = is_array($githubContext['issue'] ?? null) ? $githubContext['issue'] : [];
        /** @var array<string, mixed> $pullRequest */
        $pullRequest = is_array($githubContext['pull_request'] ?? null) ? $githubContext['pull_request'] : [];
        /** @var array<string, mixed> $ci */
        $ci = is_array($githubContext['ci'] ?? null) ? $githubContext['ci'] : [];
        $blockers = is_array($githubContext['blockers'] ?? null)
            ? array_values(array_filter($githubContext['blockers'], 'is_string'))
            : [];
        $nextAction = is_string($githubContext['next_action'] ?? null)
            ? trim((string) $githubContext['next_action'])
            : '';
        $headline = is_string($githubContext['headline'] ?? null)
            ? trim((string) $githubContext['headline'])
            : '';
        $ciSummary = $this->support->buildGitHubCiSummary($ci);

        if ($headline === '') {
            $headline = $this->support->buildGitHubHeadline($githubContext, $pullRequest, $ci);
        }

        if ($blockers === []) {
            $blockers = $this->support->buildGitHubBlockers($githubContext, $pullRequest, $ci);
        }

        if ($nextAction === '') {
            $nextAction = $this->support->buildGitHubNextAction($githubContext, $pullRequest, $ci) ?? '';
        }

        $lines = array_filter([
            sprintf('%s #%d', $this->support->label('github_for_task'), $taskId),
            sprintf('%s: %s', $this->support->label('summary'), $task->summary),
            $headline !== '' ? sprintf('%s: %s', $this->support->label('headline'), $headline) : null,
            is_string($issue['key'] ?? null) && trim((string) $issue['key']) !== ''
                ? sprintf('%s: %s', $this->support->label('issue'), trim((string) $issue['key']))
                : null,
            is_string($githubContext['repository_slug'] ?? null) && trim((string) $githubContext['repository_slug']) !== ''
                ? sprintf('%s: %s', $this->support->label('repository'), trim((string) $githubContext['repository_slug']))
                : null,
            is_string($githubContext['branch_name'] ?? null) && trim((string) $githubContext['branch_name']) !== ''
                ? sprintf('%s: %s', $this->support->label('branch'), trim((string) $githubContext['branch_name']))
                : null,
            is_string($githubContext['compare_url'] ?? null) && trim((string) $githubContext['compare_url']) !== ''
                ? sprintf('%s: %s', $this->support->label('compare'), trim((string) $githubContext['compare_url']))
                : null,
            is_string($pullRequest['status'] ?? null)
                ? sprintf('%s: %s', $this->support->label('pull_request'), trim((string) $pullRequest['status']))
                : null,
            is_string($pullRequest['url'] ?? null) && trim((string) $pullRequest['url']) !== ''
                ? sprintf('%s: %s', $this->support->label('url'), trim((string) $pullRequest['url']))
                : null,
            is_string($pullRequest['reason'] ?? null) && trim((string) $pullRequest['reason']) !== ''
                ? sprintf('%s: %s', $this->support->label('pr_note'), Str::limit(trim((string) $pullRequest['reason']), 180))
                : null,
            is_string($ci['status'] ?? null)
                ? sprintf('%s: %s', $this->support->label('ci'), trim((string) $ci['status']))
                : null,
            $ciSummary,
            is_string($ci['reason'] ?? null) && trim((string) $ci['reason']) !== ''
                ? sprintf('%s: %s', $this->support->label('ci_note'), Str::limit(trim((string) $ci['reason']), 180))
                : null,
            $nextAction !== '' ? sprintf('%s: %s', $this->support->label('next_action'), $nextAction) : null,
        ], static fn (?string $line): bool => $line !== null);

        if ($blockers !== []) {
            $lines[] = sprintf('%s:', $this->support->label('blockers'));

            foreach (array_slice($blockers, 0, 3) as $blocker) {
                $lines[] = sprintf('- %s', Str::limit(trim($blocker), 180));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build one compact Telegram queue summary for the latest tasks.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @return string
     */
    public function formatQueue(array $tasks, ?string $statusFilter = null): string
    {
        if ($tasks === []) {
            if ($statusFilter !== null) {
                return sprintf(
                    $this->support->text('phrases.no_tasks_for_status_filter', [
                        'status' => '%s',
                    ]),
                    $this->support->formatStatusValue($statusFilter)
                );
            }

            return $this->support->text('phrases.no_local_tasks');
        }

        $lines = [
            $statusFilter !== null
                ? sprintf(
                    $this->support->text('phrases.latest_tasks_for_status_filter', [
                        'status' => '%s',
                    ]),
                    $this->support->formatStatusValue($statusFilter)
                )
                : $this->support->text('phrases.latest_tasks'),
        ];

        $activityLines = $this->support->buildTaskActivityLines($tasks);

        if ($activityLines !== []) {
            $lines[] = '';
            $lines[] = $this->support->label('activity_snapshot');
            array_push($lines, ...$activityLines);
        }

        $attentionLines = $this->support->buildAttentionTaskLines($tasks);

        if ($attentionLines !== []) {
            $lines[] = '';
            $lines[] = $this->support->label('needs_attention');
            array_push($lines, ...$attentionLines);
        }

        if ($activityLines !== [] || $attentionLines !== []) {
            $lines[] = '';
            $lines[] = $this->support->label('tasks');
        }

        foreach ($tasks as $task) {
            $lines[] = $this->support->formatQueueTaskLine($task);
        }

        return implode("\n", $lines);
    }

    /**
     * Build one compact changed-file summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatChanges(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);
        $run = $this->support->resolveLatestRun($task);
        $changedFiles = $run?->changed_files;

        if (! is_array($changedFiles) || $changedFiles === []) {
            return sprintf(
                '%s #%d %s',
                $this->support->label('task'),
                $taskId,
                $this->support->text('phrases.no_recorded_changed_files')
            );
        }

        $lines = [
            sprintf('%s #%d', $this->support->label('changed_files_for_task'), $taskId),
        ];

        /** @var array<int, array<string, string>> $typedChangedFiles */
        $typedChangedFiles = $changedFiles;

        foreach (array_slice($typedChangedFiles, 0, 8) as $changedFile) {
            $path = is_string($changedFile['path'] ?? null) ? $changedFile['path'] : null;
            $status = is_string($changedFile['status'] ?? null) ? strtoupper($changedFile['status']) : '?';

            if ($path === null) {
                continue;
            }

            $lines[] = sprintf('%s %s', $status, $path);
            $lines[] = sprintf('  %s', $this->resolveChangedFileDescription($changedFile, $status));
        }

        if (count($changedFiles) > 8) {
            $lines[] = $this->support->text('phrases.more_files', [
                'count' => count($changedFiles) - 8,
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve one changed-file description without inventing unavailable diff details.
     *
     * @param  array<string, string>  $changedFile
     * @param  string  $status
     * @return string
     */
    protected function resolveChangedFileDescription(array $changedFile, string $status): string
    {
        foreach (['description', 'summary', 'change_summary'] as $field) {
            $description = is_string($changedFile[$field] ?? null) ? trim($changedFile[$field]) : '';

            if ($description !== '') {
                return $description;
            }
        }

        return match (strtolower($status)) {
            'a', 'added', 'new' => $this->support->text('phrases.changed_file_added'),
            'd', 'deleted', 'removed' => $this->support->text('phrases.changed_file_deleted'),
            'r', 'renamed' => $this->support->text('phrases.changed_file_renamed'),
            'm', 'modified', 'changed' => $this->support->text('phrases.changed_file_modified'),
            default => $this->support->text('phrases.changed_file_touched'),
        };
    }

    /**
     * Build one final outcome summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @return string
     */
    public function formatOutcomeForTask(AutoCodingTask $task, AutoCodingTaskRun $run): string
    {
        if ($this->isDirectCodexChatTask($task)) {
            return $this->formatDirectChatOutcome($task, $run);
        }

        $taskId = (int) $run->task_id;
        $runId = $this->support->resolveModelId($run);
        $report = is_array($run->final_report) ? $run->final_report : [];
        /** @var array<string, mixed> $validation */
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $failure = is_array($report['failure'] ?? null) ? $report['failure'] : [];
        $recommendedAction = is_array($report['recommended_action'] ?? null) ? $report['recommended_action'] : [];
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $followUp = is_array($report['follow_up'] ?? null) ? $report['follow_up'] : [];
        $resumeHint = $this->support->buildResumeHint($taskId, $report);
        $machine = is_array($report['machine'] ?? null) ? $report['machine'] : [];
        $machineKey = is_string($machine['machine_key'] ?? null) ? trim((string) $machine['machine_key']) : '';
        $changedFilePreviewLines = $this->buildChangedFilePreviewLines($run->changed_files);
        $validationDetails = $this->buildOutcomeValidationDetails($validation);
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $chatSessionLines = $this->support->buildTaskChatSessionLines($taskContext);
        $workflowLines = $this->support->buildWorkflowProgressLines($report);

        $lines = array_filter([
            sprintf('%s #%d %s', $this->support->label('task'), $taskId, $this->support->formatStatusHeadline($run->status->value)),
            sprintf('%s: %s', $this->support->label('summary'), $task->summary),
            $task->issue_key !== null ? sprintf('%s: %s', $this->support->label('issue'), $task->issue_key) : null,
            sprintf('%s: #%d', $this->support->label('run'), $runId),
            $machineKey !== '' ? sprintf('%s: %s', $this->support->label('machine'), $machineKey) : null,
            is_string($validation['overall_status'] ?? null)
                ? sprintf('%s: %s', $this->support->label('validation'), $this->support->formatStatusValue((string) $validation['overall_status']))
                : null,
            $validationDetails,
            is_numeric($summary['changed_file_count'] ?? null)
                ? sprintf('%s: %d', $this->support->label('changed_files'), (int) $summary['changed_file_count'])
                : null,
            is_string($failure['message'] ?? null) && trim((string) $failure['message']) !== ''
                ? sprintf('%s: %s', $this->support->label('failure'), Str::limit(trim((string) $failure['message']), 180))
                : null,
            is_string($followUp['message'] ?? null) && trim((string) $followUp['message']) !== ''
                ? sprintf('%s: %s', $this->support->label('follow_up'), Str::limit(trim((string) $followUp['message']), 180))
                : null,
            is_string($recommendedAction['action'] ?? null)
                ? sprintf('%s: %s', $this->support->label('next_action'), $recommendedAction['action'])
                : null,
            $resumeHint,
        ], static fn (?string $line): bool => $line !== null);

        if ($changedFilePreviewLines !== []) {
            array_push($lines, ...$changedFilePreviewLines);
        }

        if ($workflowLines !== []) {
            array_push($lines, ...$workflowLines);
        }

        if ($chatSessionLines !== []) {
            array_push($lines, ...$chatSessionLines);
        }

        return implode("\n", $lines);
    }

    /**
     * Build the Telegram acknowledgement for a direct Codex chat message.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    protected function formatDirectChatQueued(AutoCodingTask $task): string
    {
        return implode("\n", [
            $this->support->text('phrases.direct_chat_queued'),
            sprintf('%s: %s', $this->support->label('message'), Str::limit((string) $task->summary, 180)),
        ]);
    }

    /**
     * Build the Telegram running message for a direct Codex chat reply.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    protected function formatDirectChatRunning(AutoCodingTask $task): string
    {
        return implode("\n", [
            $this->support->text('phrases.direct_chat_running'),
            sprintf('%s: %s', $this->support->label('message'), Str::limit((string) $task->summary, 180)),
        ]);
    }

    /**
     * Build the Telegram final reply for a direct Codex chat message.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @return string
     */
    protected function formatDirectChatOutcome(AutoCodingTask $task, AutoCodingTaskRun $run): string
    {
        $report = is_array($run->final_report) ? $run->final_report : [];
        $provider = is_array($report['provider_result'] ?? null)
            ? $report['provider_result']
            : (is_array($report['provider'] ?? null) ? $report['provider'] : []);

        if ($run->status->value !== 'completed') {
            $failure = is_array($report['failure'] ?? null) ? $report['failure'] : [];
            $message = is_string($failure['message'] ?? null)
                ? trim((string) $failure['message'])
                : '';

            return trim(implode("\n", array_filter([
                $this->support->text('phrases.direct_chat_failed'),
                $message !== '' ? sprintf('%s: %s', $this->support->label('details'), Str::limit($message, 220)) : null,
            ], static fn (?string $line): bool => $line !== null)));
        }

        $content = is_string($provider['content'] ?? null) ? trim((string) $provider['content']) : '';

        return $content !== ''
            ? $this->normalizeDirectChatContent(Str::limit($content, 3600))
            : $this->support->text('phrases.direct_chat_empty');
    }

    /**
     * Keep Telegram direct-chat text structurally safe for fenced blocks.
     *
     * @param  string  $content
     * @return string
     */
    protected function normalizeDirectChatContent(string $content): string
    {
        $normalizedContent = trim($content);

        if (substr_count($normalizedContent, '```') % 2 === 1) {
            return $normalizedContent."\n```";
        }

        return $normalizedContent;
    }

    /**
     * Determine whether one task represents a direct Telegram Codex chat reply.
     *
     * @param  AutoCodingTask  $task
     * @return bool
     */
    protected function isDirectCodexChatTask(AutoCodingTask $task): bool
    {
        $taskContext = is_array($task->context_payload) ? $task->context_payload : [];
        $providerOptions = is_array($taskContext['provider_options'] ?? null) ? $taskContext['provider_options'] : [];
        $transportContext = is_array($taskContext['transport_context'] ?? null) ? $taskContext['transport_context'] : [];

        return ($providerOptions['mode'] ?? null) === 'telegram_direct_chat'
            || ($transportContext['intent'] ?? null) === 'codex_chat_reply';
    }

    /**
     * Build one final outcome summary for the latest run of a task.
     *
     * @param  AutoCodingTaskRun  $run
     * @return string
     */
    public function formatOutcome(AutoCodingTaskRun $run): string
    {
        $task = $run->relationLoaded('task') && $run->task instanceof AutoCodingTask
            ? $run->task
            : new AutoCodingTask([
                'summary' => $this->support->text('phrases.no_execution_run'),
                'issue_key' => null,
            ]);

        return $this->formatOutcomeForTask($task, $run);
    }

    /**
     * Build one final report summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatSummary(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);
        $run = $this->support->resolveLatestRun($task);

        if (! $run instanceof AutoCodingTaskRun) {
            return sprintf(
                '%s #%d %s',
                $this->support->label('task'),
                $taskId,
                $this->support->text('phrases.no_execution_run')
            );
        }

        return $this->formatOutcomeForTask($task, $run);
    }

    /**
     * Build one localized resume-accepted message.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatResumeAccepted(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);

        return implode("\n", [
            sprintf('%s #%d', $this->support->label('resume_accepted_for_task'), $taskId),
            sprintf('%s: %s', $this->support->label('status'), $this->support->formatStatusValue($task->status->value)),
            $this->support->text('phrases.resume_workflow_continue'),
        ]);
    }

    /**
     * Build one compact validation-details line for Telegram outcome reporting.
     *
     * @param  array<string, mixed>  $validation
     * @return string|null
     */
    protected function buildOutcomeValidationDetails(array $validation): ?string
    {
        $summary = is_string($validation['summary'] ?? null) ? trim((string) $validation['summary']) : '';

        if ($summary !== '') {
            return sprintf('%s: %s', $this->support->label('details'), Str::limit($summary, 180));
        }

        $failedCommands = is_numeric($validation['failed_commands'] ?? null) ? (int) $validation['failed_commands'] : null;
        $totalCommands = is_numeric($validation['total_commands'] ?? null) ? (int) $validation['total_commands'] : null;

        if ($failedCommands === null || $totalCommands === null) {
            return null;
        }

        return sprintf(
            '%s: %s',
            $this->support->label('details'),
            $this->support->text('phrases.validation_failed_command_ratio', [
                'failed' => $failedCommands,
                'total' => $totalCommands,
            ])
        );
    }

    /**
     * Build one compact changed-file preview block for Telegram outcome reporting.
     *
     * @param  mixed  $changedFiles
     * @return array<int, string>
     */
    protected function buildChangedFilePreviewLines(mixed $changedFiles): array
    {
        if (! is_array($changedFiles) || $changedFiles === []) {
            return [];
        }

        $lines = [];

        /** @var array<int, array<string, mixed>> $typedChangedFiles */
        $typedChangedFiles = $changedFiles;

        foreach (array_slice($typedChangedFiles, 0, 3) as $changedFile) {
            $path = is_string($changedFile['path'] ?? null) ? trim((string) $changedFile['path']) : '';
            $status = is_string($changedFile['status'] ?? null) ? strtoupper(trim((string) $changedFile['status'])) : '?';

            if ($path === '') {
                continue;
            }

            $lines[] = sprintf('- %s %s', $status, $path);
        }

        if (count($typedChangedFiles) > 3) {
            $lines[] = $this->support->text('phrases.more_files', [
                'count' => count($typedChangedFiles) - 3,
            ]);
        }

        return $lines;
    }

    /**
     * Resolve one compact validation status for task-status rendering.
     *
     * @param  AutoCodingTask  $task
     * @return string|null
     */
    protected function resolveTaskValidationStatus(AutoCodingTask $task): ?string
    {
        $run = $this->support->resolveLatestRun($task);

        if (! $run instanceof AutoCodingTaskRun) {
            return null;
        }

        $validation = is_array($run->validation_results) ? $run->validation_results : [];
        $status = is_string($validation['overall_status'] ?? null) ? trim((string) $validation['overall_status']) : '';

        return $status !== '' ? $this->support->formatStatusValue($status) : null;
    }

    /**
     * Build one accepted-values hint for confirmation-style follow-up prompts.
     *
     * @param  array<string, mixed>  $inputContract
     * @return string|null
     */
    protected function buildAcceptedValuesLine(array $inputContract): ?string
    {
        if (! is_array($inputContract['accepted_values'] ?? null)) {
            return null;
        }

        $acceptedValues = array_values(array_filter(
            $inputContract['accepted_values'],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
        ));

        if ($acceptedValues === []) {
            return null;
        }

        return sprintf('%s: %s', $this->support->label('details'), implode(', ', $acceptedValues));
    }
}
