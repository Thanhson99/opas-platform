<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AutoCodingTelegramMaintenanceFormatter
{
    public function __construct(
        private readonly AutoCodingTelegramFormatterSupport $support,
    ) {}

    /**
     * Build one localized chat-reset confirmation message.
     *
     * @param  bool  $forceCleanup
     * @param  string  $scope
     * @return string
     */
    public function formatResetComplete(bool $forceCleanup = false, string $scope = 'session'): string
    {
        if (trim(strtolower($scope)) === 'all') {
            return $forceCleanup
                ? $this->support->text('phrases.chat_cleaned_all_force')
                : $this->support->text('phrases.chat_cleaned_all');
        }

        return $forceCleanup
            ? $this->support->text('phrases.chat_cleaned_force')
            : $this->support->text('phrases.chat_cleaned');
    }

    /**
     * Build one direct chat-session started message for Telegram.
     *
     * @param  array<string, mixed>  $session
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return string
     */
    public function formatChatSessionStarted(
        array $session,
        ?AutoCodingMachine $machine,
        ?AutoCodingTask $activeTask = null,
    ): string {
        return $this->support->text('phrases.chat_session_connected');
    }

    /**
     * Build one direct chat-session status message for Telegram.
     *
     * @param  array<string, mixed>|null  $session
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return string
     */
    public function formatChatSessionStatus(
        ?array $session,
        ?AutoCodingMachine $machine,
        ?AutoCodingTask $activeTask = null,
    ): string {
        if (! is_array($session) || ($session['enabled'] ?? false) !== true) {
            return implode("\n", [
                $this->support->label('chat_session'),
                $this->support->text('phrases.chat_session_inactive'),
                $this->support->text('phrases.chat_session_start_hint'),
            ]);
        }

        return implode("\n", array_filter([
            $this->support->label('chat_session'),
            ...$this->buildChatSessionStatusLines($session, $machine, $activeTask),
            ...$this->support->buildChatSessionTimelineLines($session),
            $this->support->text('phrases.chat_session_hint'),
        ], static fn (?string $line): bool => $line !== null && $line !== ''));
    }

    /**
     * Build one compact connectivity acknowledgement without timeline noise.
     *
     * @param  array<string, mixed>|null  $session
     * @param  AutoCodingMachine|null  $machine
     * @return string
     */
    public function formatChatPing(?array $session, ?AutoCodingMachine $machine): string
    {
        $isActive = is_array($session) && ($session['enabled'] ?? false) === true;
        $sessionId = is_array($session) && is_string($session['session_id'] ?? null)
            ? trim((string) $session['session_id'])
            : '';

        return implode("\n", array_filter([
            $this->support->text('phrases.chat_ping_received'),
            sprintf('%s: %s', $this->support->label('status'), $this->support->text($isActive ? 'values.active' : 'values.inactive')),
            $sessionId !== '' ? sprintf('%s: %s', $this->support->label('session_id'), $sessionId) : null,
            $machine instanceof AutoCodingMachine ? sprintf('%s: %s', $this->support->label('machine'), $machine->machine_key) : null,
            $this->support->text('phrases.chat_ping_no_task_created'),
        ], static fn (?string $line): bool => $line !== null && $line !== ''));
    }

    /**
     * Build one direct chat-session stopped message for Telegram.
     *
     * @return string
     */
    public function formatChatSessionStopped(): string
    {
        return implode("\n", [
            $this->support->label('chat_session_stopped'),
            $this->support->text('phrases.chat_session_stopped'),
            $this->support->text('phrases.chat_session_start_hint'),
        ]);
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
        $lines = [
            $this->support->label('chat_session_reset'),
            $this->support->text('phrases.chat_session_reset'),
        ];

        if (is_array($session) && ($session['enabled'] ?? false) === true) {
            array_push($lines, ...$this->buildChatSessionStatusLines($session, $machine, null));
            array_push($lines, ...$this->support->buildChatSessionTimelineLines($session));
        }

        $lines[] = $this->support->text('phrases.chat_session_hint');

        return implode("\n", array_filter($lines, static fn (?string $line): bool => $line !== null && $line !== ''));
    }

    /**
     * Build one clarification prompt for ambiguous Telegram requests.
     *
     * @param  string  $originalText
     * @return string
     */
    public function formatIntentClarification(string $originalText): string
    {
        return implode("\n", [
            $this->support->label('clarify_request'),
            $this->support->text('phrases.clarify_request_prompt'),
            sprintf('%s: %s', $this->support->label('original_message'), $originalText),
        ]);
    }

    /**
     * Build one issue-context clarification prompt when multiple reusable task histories conflict.
     *
     * @param  array<string, mixed>  $clarification
     * @return string
     */
    public function formatIssueContextClarification(array $clarification): string
    {
        $issueKey = is_string($clarification['issue_key'] ?? null) ? trim((string) $clarification['issue_key']) : '';
        $taskType = is_string($clarification['task_type'] ?? null) ? trim((string) $clarification['task_type']) : '';

        return implode("\n", array_filter([
            $this->support->label('clarify_request'),
            $this->support->text('phrases.issue_context_clarify_prompt', [
                'task_type' => $taskType !== '' ? $taskType : $this->support->text('values.unknown'),
                'issue_key' => $issueKey !== '' ? $issueKey : $this->support->text('values.unknown'),
            ]),
            ...$this->buildIssueContextCandidateLines($clarification),
            $this->support->text('phrases.issue_context_reply_hint'),
        ], static fn (?string $line): bool => $line !== null && $line !== ''));
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
        $resolvedActionLabel = $this->support->resolveDangerousActionLabel($actionLabel);

        return implode("\n", array_filter([
            $this->support->label('confirm_action'),
            $this->support->text('phrases.confirm_action_prompt'),
            sprintf('%s: %s', $this->support->label('action'), $resolvedActionLabel),
            is_string($targetLabel) && trim($targetLabel) !== ''
                ? sprintf('%s: %s', $this->support->label('target'), trim($targetLabel))
                : null,
        ], static fn (?string $line): bool => $line !== null));
    }

    /**
     * Build one expired pending-interaction message.
     *
     * @param  string  $type
     * @return string
     */
    public function formatPendingInteractionExpired(string $type): string
    {
        return match ($type) {
            'clarify_intent' => $this->support->text('phrases.no_pending_clarification'),
            'clarify_issue_context' => $this->support->text('phrases.no_pending_issue_context_clarification'),
            default => $this->support->text('phrases.no_pending_confirmation'),
        };
    }

    /**
     * Build one cancellation message for pending interactions.
     *
     * @return string
     */
    public function formatPendingInteractionCancelled(): string
    {
        return $this->support->text('phrases.pending_interaction_cancelled');
    }

    /**
     * Build one Telegram cancellation confirmation for a specific task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatCancelTaskResult(AutoCodingTask $task): string
    {
        $taskId = $this->support->resolveModelId($task);

        return implode("\n", [
            sprintf('%s #%d', $this->support->label('task_update'), $taskId),
            sprintf('%s: %s', $this->support->label('status'), $this->support->formatStatusValue($task->status->value)),
            $task->status->value === 'running'
                ? $this->support->text('phrases.cancel_requested')
                : $this->support->text('phrases.cancelled_removed_from_active'),
        ]);
    }

    /**
     * Build one bulk-cancellation summary for active tasks.
     *
     * @param  array{cancelled_count:int,cancellation_requested_count:int,unchanged_count:int}  $result
     * @return string
     */
    public function formatCancelTasksResult(array $result): string
    {
        return implode("\n", [
            $this->support->label('bulk_cancellation_completed'),
            sprintf('%s: %d', $this->support->label('cancelled_immediately'), $result['cancelled_count']),
            sprintf('%s: %d', $this->support->label('cancellation_requested_running'), $result['cancellation_requested_count']),
            sprintf('%s: %d', $this->support->label('unchanged'), $result['unchanged_count']),
        ]);
    }

    /**
     * Build one permanent-delete confirmation for a specific pending task.
     *
     * @param  array{id:int,summary:string}  $result
     * @return string
     */
    public function formatDeleteTaskResult(array $result): string
    {
        return implode("\n", [
            sprintf('%s #%d', $this->support->label('deleted_pending_task'), $result['id']),
            sprintf('%s: %s', $this->support->label('summary'), $result['summary']),
            $this->support->text('phrases.task_removed_permanently'),
        ]);
    }

    /**
     * Build one bulk permanent-delete summary for pending tasks.
     *
     * @param  array{deleted_count:int,scope:string}  $result
     * @return string
     */
    public function formatDeleteTasksResult(array $result): string
    {
        $headline = trim($result['scope']) === 'all'
            ? $this->support->button('delete_all_tasks')
            : $this->support->label('bulk_pending_deletion_completed');

        return implode("\n", [
            $headline,
            sprintf('%s: %s', $this->support->label('scope'), $result['scope']),
            sprintf('%s: %d', $this->support->label('deleted_tasks'), $result['deleted_count']),
        ]);
    }

    /**
     * Build one localized button label.
     *
     * @param  string  $key
     * @return string
     */
    public function formatButtonLabel(string $key): string
    {
        $label = $this->support->text(sprintf('buttons.%s', $key));

        return $label !== sprintf('buttons.%s', $key) ? $label : $key;
    }

    /**
     * Build one compact inline-button label for an issue-context clarification option.
     *
     * @param  array<string, mixed>  $candidate
     * @return string
     */
    public function formatIssueContextChoiceLabel(array $candidate): string
    {
        $taskId = is_numeric($candidate['task_id'] ?? null) ? (int) $candidate['task_id'] : 0;
        $summary = is_string($candidate['summary'] ?? null) ? trim((string) $candidate['summary']) : '';

        return sprintf('#%d %s', $taskId, Str::limit($summary, 42));
    }

    /**
     * Build compact summary lines for conflicting issue-context candidates.
     *
     * @param  array<string, mixed>  $clarification
     * @return array<int, string>
     */
    protected function buildIssueContextCandidateLines(array $clarification): array
    {
        $candidates = is_array($clarification['candidates'] ?? null) ? $clarification['candidates'] : [];
        $lines = [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! is_numeric($candidate['task_id'] ?? null)) {
                continue;
            }

            /** @var array<string, mixed> $candidate */
            $rawTaskId = $candidate['task_id'];
            $taskId = is_int($rawTaskId) ? $rawTaskId : (is_string($rawTaskId) ? (int) $rawTaskId : 0);
            $summary = is_string($candidate['summary'] ?? null) ? trim((string) $candidate['summary']) : '';
            $lines[] = sprintf('#%d %s', $taskId, Str::limit($summary, 110));

            $detailParts = array_filter([
                $this->buildIssueContextCandidateRepository($candidate),
                $this->buildIssueContextCandidateBranch($candidate),
                $this->buildIssueContextCandidateProvider($candidate),
                $this->buildIssueContextCandidateScope($candidate),
            ], static fn (?string $value): bool => $value !== null && $value !== '');

            if ($detailParts !== []) {
                $lines[] = implode(' | ', $detailParts);
            }
        }

        return $lines;
    }

    /**
     * Build one compact list of direct chat-session status lines.
     *
     * @param  array<string, mixed>  $session
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return array<int, string>
     */
    protected function buildChatSessionStatusLines(
        array $session,
        ?AutoCodingMachine $machine,
        ?AutoCodingTask $activeTask = null,
    ): array {
        $mode = is_string($session['mode'] ?? null) ? trim((string) $session['mode']) : $this->support->text('values.unknown');
        $sessionId = is_string($session['session_id'] ?? null) ? trim((string) $session['session_id']) : '';
        $startedAt = is_string($session['started_at'] ?? null) ? trim((string) $session['started_at']) : '';
        $lastMessageAt = is_string($session['last_message_at'] ?? null) ? trim((string) $session['last_message_at']) : '';
        $machineKey = $machine instanceof AutoCodingMachine ? trim($machine->machine_key) : '';

        return array_values(array_filter([
            sprintf('%s: %s', $this->support->label('status'), $this->support->text('values.active')),
            sprintf('%s: %s', $this->support->label('chat_mode'), $mode),
            $sessionId !== '' ? sprintf('%s: %s', $this->support->label('session_id'), $sessionId) : null,
            $startedAt !== '' ? sprintf('%s: %s', $this->support->label('started_at'), $startedAt) : null,
            $lastMessageAt !== '' ? sprintf('%s: %s', $this->support->label('last_message'), $lastMessageAt) : null,
            $machineKey !== '' ? sprintf('%s: %s', $this->support->label('machine'), $machineKey) : null,
            $activeTask instanceof AutoCodingTask
                ? sprintf('%s: #%d %s', $this->support->label('task'), $this->support->resolveModelId($activeTask), Str::limit($activeTask->summary, 90))
                : $this->support->text('phrases.chat_session_no_active_task'),
        ], static fn (?string $line): bool => $line !== null && $line !== ''));
    }

    /**
     * Build one repository detail segment for an issue-context candidate.
     *
     * @param  array<string, mixed>  $candidate
     * @return string|null
     */
    protected function buildIssueContextCandidateRepository(array $candidate): ?string
    {
        $repositoryPath = is_string($candidate['repository_path'] ?? null)
            ? trim((string) $candidate['repository_path'])
            : '';

        return $repositoryPath !== ''
            ? sprintf('%s: %s', $this->support->label('repository'), $repositoryPath)
            : null;
    }

    /**
     * Build one branch detail segment for an issue-context candidate.
     *
     * @param  array<string, mixed>  $candidate
     * @return string|null
     */
    protected function buildIssueContextCandidateBranch(array $candidate): ?string
    {
        $branchName = is_string($candidate['branch_name'] ?? null)
            ? trim((string) $candidate['branch_name'])
            : '';

        return $branchName !== ''
            ? sprintf('%s: %s', $this->support->label('branch'), $branchName)
            : null;
    }

    /**
     * Build one provider detail segment for an issue-context candidate.
     *
     * @param  array<string, mixed>  $candidate
     * @return string|null
     */
    protected function buildIssueContextCandidateProvider(array $candidate): ?string
    {
        $provider = is_string($candidate['provider'] ?? null) ? trim((string) $candidate['provider']) : '';

        if ($provider === '') {
            return null;
        }

        $providerOptions = is_array($candidate['provider_options'] ?? null)
            ? $candidate['provider_options']
            : [];
        $model = is_string(Arr::get($providerOptions, 'model')) ? trim((string) Arr::get($providerOptions, 'model')) : '';
        $providerLabel = $model !== '' ? sprintf('%s (%s)', $provider, $model) : $provider;

        return sprintf('%s: %s', $this->support->label('provider'), $providerLabel);
    }

    /**
     * Build one scope detail segment for an issue-context candidate.
     *
     * @param  array<string, mixed>  $candidate
     * @return string|null
     */
    protected function buildIssueContextCandidateScope(array $candidate): ?string
    {
        $scopePaths = is_array($candidate['scope_paths'] ?? null)
            ? array_values(array_filter($candidate['scope_paths'], 'is_string'))
            : [];

        return $scopePaths !== []
            ? sprintf('%s: %s', $this->support->label('scope'), implode(', ', $scopePaths))
            : null;
    }
}
