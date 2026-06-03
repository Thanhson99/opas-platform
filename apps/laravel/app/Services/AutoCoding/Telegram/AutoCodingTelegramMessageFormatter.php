<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;

class AutoCodingTelegramMessageFormatter
{
    public function __construct(
        private readonly AutoCodingTelegramHelpMenuFormatter $helpMenuFormatter,
        private readonly AutoCodingTelegramTaskReportFormatter $taskReportFormatter,
        private readonly AutoCodingTelegramMaintenanceFormatter $maintenanceFormatter,
    ) {}

    /**
     * Build the onboarding and help text for Telegram remote coding control.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @param  AutoCodingMachine|null  $machine
     * @param  array<string, mixed>|null  $chatSession
     * @return string
     */
    public function formatHelp(array $tasks = [], ?AutoCodingMachine $machine = null, ?array $chatSession = null): string
    {
        return $this->helpMenuFormatter->formatHelp($tasks, $machine, $chatSession);
    }

    /**
     * Build one localized menu description for a named Telegram navigation menu.
     *
     * @param  string  $menuKey
     * @param  array<int, AutoCodingTask>  $tasks
     * @return string
     */
    public function formatMenu(string $menuKey, array $tasks = []): string
    {
        return $this->helpMenuFormatter->formatMenu($menuKey, $tasks);
    }

    /**
     * Build the Telegram message shown when one task is queued remotely.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatQueued(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatQueued($task);
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
        return $this->taskReportFormatter->formatRunning($task, $run);
    }

    /**
     * Build the Telegram status message for one task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatStatus(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatStatus($task);
    }

    /**
     * Build one compact next-action view for a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatNextAction(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatNextAction($task);
    }

    /**
     * Build one focused follow-up contract view for blocked or input-driven tasks.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatFollowUp(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatFollowUp($task);
    }

    /**
     * Build one validation-focused summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatValidation(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatValidation($task);
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
        return $this->taskReportFormatter->formatGithubStatus($task, $githubContext);
    }

    /**
     * Build one issue-context clarification prompt when multiple task histories conflict.
     *
     * @param  array<string, mixed>  $clarification
     * @return string
     */
    public function formatIssueContextClarification(array $clarification): string
    {
        return $this->maintenanceFormatter->formatIssueContextClarification($clarification);
    }

    /**
     * Build one compact Telegram queue summary for the latest tasks.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @return string
     */
    public function formatQueue(array $tasks, ?string $statusFilter = null): string
    {
        return $this->taskReportFormatter->formatQueue($tasks, $statusFilter);
    }

    /**
     * Build one Telegram cancellation confirmation for a specific task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatCancelTaskResult(AutoCodingTask $task): string
    {
        return $this->maintenanceFormatter->formatCancelTaskResult($task);
    }

    /**
     * Build one bulk-cancellation summary for active tasks.
     *
     * @param  array{cancelled_count:int,cancellation_requested_count:int,unchanged_count:int}  $result
     * @return string
     */
    public function formatCancelTasksResult(array $result): string
    {
        return $this->maintenanceFormatter->formatCancelTasksResult($result);
    }

    /**
     * Build one permanent-delete confirmation for a specific pending task.
     *
     * @param  array{id:int,summary:string}  $result
     * @return string
     */
    public function formatDeleteTaskResult(array $result): string
    {
        return $this->maintenanceFormatter->formatDeleteTaskResult($result);
    }

    /**
     * Build one bulk permanent-delete summary for pending tasks.
     *
     * @param  array{deleted_count:int,scope:string}  $result
     * @return string
     */
    public function formatDeleteTasksResult(array $result): string
    {
        return $this->maintenanceFormatter->formatDeleteTasksResult($result);
    }

    /**
     * Build one compact changed-file summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatChanges(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatChanges($task);
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
        return $this->taskReportFormatter->formatOutcomeForTask($task, $run);
    }

    /**
     * Build one final outcome summary for the latest run of a task.
     *
     * @param  AutoCodingTaskRun  $run
     * @return string
     */
    public function formatOutcome(AutoCodingTaskRun $run): string
    {
        return $this->taskReportFormatter->formatOutcome($run);
    }

    /**
     * Build one final report summary for the latest run of a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatSummary(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatSummary($task);
    }

    /**
     * Build one localized resume-accepted message.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatResumeAccepted(AutoCodingTask $task): string
    {
        return $this->taskReportFormatter->formatResumeAccepted($task);
    }

    /**
     * Build one localized chat-reset confirmation message.
     *
     * @param  bool  $forceCleanup
     * @param  string  $scope
     * @return string
     */
    public function formatResetComplete(bool $forceCleanup = false, string $scope = 'session'): string
    {
        return $this->maintenanceFormatter->formatResetComplete($forceCleanup, $scope);
    }

    /**
     * Build one direct chat-session started message for Telegram.
     *
     * @param  array<string, mixed>  $session
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return string
     */
    public function formatChatSessionStarted(array $session, ?AutoCodingMachine $machine, ?AutoCodingTask $activeTask = null): string
    {
        return $this->maintenanceFormatter->formatChatSessionStarted($session, $machine, $activeTask);
    }

    /**
     * Build one direct chat-session status message for Telegram.
     *
     * @param  array<string, mixed>|null  $session
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return string
     */
    public function formatChatSessionStatus(?array $session, ?AutoCodingMachine $machine, ?AutoCodingTask $activeTask = null): string
    {
        return $this->maintenanceFormatter->formatChatSessionStatus($session, $machine, $activeTask);
    }

    /**
     * Build one compact direct chat connectivity acknowledgement.
     *
     * @param  array<string, mixed>|null  $session
     * @param  AutoCodingMachine|null  $machine
     * @return string
     */
    public function formatChatPing(?array $session, ?AutoCodingMachine $machine): string
    {
        return $this->maintenanceFormatter->formatChatPing($session, $machine);
    }

    /**
     * Build one direct chat-session stopped message for Telegram.
     *
     * @return string
     */
    public function formatChatSessionStopped(): string
    {
        return $this->maintenanceFormatter->formatChatSessionStopped();
    }

    /**
     * Build one direct chat-session reset message for Telegram.
     *
     * @param  array<string, mixed>|null  $session
     * @param  AutoCodingMachine|null  $machine
     * @return string
     */
    public function formatChatSessionReset(?array $session, ?AutoCodingMachine $machine): string
    {
        return $this->maintenanceFormatter->formatChatSessionReset($session, $machine);
    }

    /**
     * Build one clarification prompt for ambiguous Telegram requests.
     *
     * @param  string  $originalText
     * @return string
     */
    public function formatIntentClarification(string $originalText): string
    {
        return $this->maintenanceFormatter->formatIntentClarification($originalText);
    }

    /**
     * Build one dangerous-action confirmation prompt for Telegram.
     *
     * @param  string  $actionLabel
     * @param  string|null  $targetLabel
     * @return string
     */
    public function formatDangerousActionConfirmation(string $actionLabel, ?string $targetLabel = null): string
    {
        return $this->maintenanceFormatter->formatDangerousActionConfirmation($actionLabel, $targetLabel);
    }

    /**
     * Build one expired pending-interaction message.
     *
     * @param  string  $type
     * @return string
     */
    public function formatPendingInteractionExpired(string $type): string
    {
        return $this->maintenanceFormatter->formatPendingInteractionExpired($type);
    }

    /**
     * Build one cancellation message for pending interactions.
     *
     * @return string
     */
    public function formatPendingInteractionCancelled(): string
    {
        return $this->maintenanceFormatter->formatPendingInteractionCancelled();
    }

    /**
     * Build one localized button label.
     *
     * @param  string  $key
     * @return string
     */
    public function formatButtonLabel(string $key): string
    {
        return $this->maintenanceFormatter->formatButtonLabel($key);
    }

    /**
     * Build one compact button label for an issue-context clarification option.
     *
     * @param  array<string, mixed>  $candidate
     * @return string
     */
    public function formatIssueContextChoiceLabel(array $candidate): string
    {
        return $this->maintenanceFormatter->formatIssueContextChoiceLabel($candidate);
    }
}
