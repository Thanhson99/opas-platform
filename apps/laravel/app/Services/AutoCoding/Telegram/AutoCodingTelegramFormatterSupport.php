<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use Illuminate\Support\Str;

class AutoCodingTelegramFormatterSupport
{
    public function __construct(
        private readonly AutoCodingTelegramLocaleService $localeService,
        private readonly AutoCodingTelegramTextService $textService,
    ) {}

    /**
     * Resolve the latest known run for one task.
     *
     * @param  AutoCodingTask  $task
     * @return AutoCodingTaskRun|null
     */
    public function resolveLatestRun(AutoCodingTask $task): ?AutoCodingTaskRun
    {
        $latestRun = $task->relationLoaded('runs')
            ? $task->runs->sortByDesc('id')->first()
            : $task->runs()->latest('id')->first();

        return $latestRun instanceof AutoCodingTaskRun ? $latestRun : null;
    }

    /**
     * Resolve one numeric primary key from an Eloquent model safely.
     *
     * @param  AutoCodingTask|AutoCodingTaskRun  $model
     * @return int
     */
    public function resolveModelId(AutoCodingTask|AutoCodingTaskRun $model): int
    {
        $key = $model->getKey();

        return is_numeric($key) ? (int) $key : 0;
    }

    /**
     * Build one Telegram resume hint for blocked tasks with structured follow-up questions.
     *
     * @param  int  $taskId
     * @param  array<string, mixed>  $report
     * @return string|null
     */
    public function buildResumeHint(int $taskId, array $report): ?string
    {
        $followUp = is_array($report['follow_up'] ?? null) ? $report['follow_up'] : [];

        if (($followUp['required'] ?? false) !== true) {
            return null;
        }

        $questionContracts = is_array($followUp['question_contracts'] ?? null)
            ? array_values(array_filter($followUp['question_contracts'], 'is_array'))
            : [];

        if (count($questionContracts) <= 1) {
            return null;
        }

        $questionIds = [];

        foreach ($questionContracts as $questionContract) {
            $questionId = is_string($questionContract['id'] ?? null)
                ? trim($questionContract['id'])
                : '';

            if ($questionId === '') {
                continue;
            }

            $questionIds[] = sprintf('%s=<value>', $questionId);
        }

        if ($questionIds === []) {
            return null;
        }

        return sprintf(
            '%s',
            $this->text('phrases.resume_format', [
                'task_id' => $taskId,
                'question_pairs' => implode('; ', $questionIds),
            ])
        );
    }

    /**
     * Build one compact status snapshot for a task list.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @return array<int, string>
     */
    public function buildTaskActivityLines(array $tasks): array
    {
        if ($tasks === []) {
            return [];
        }

        $statusCounts = [
            'pending' => 0,
            'running' => 0,
            'blocked' => 0,
            'failed' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        foreach ($tasks as $task) {
            $status = trim(strtolower($task->status->value));

            if (! array_key_exists($status, $statusCounts)) {
                continue;
            }

            $statusCounts[$status]++;
        }

        $lines = [];

        foreach ($statusCounts as $status => $count) {
            if ($count <= 0) {
                continue;
            }

            $lines[] = sprintf('%s: %d', ucfirst($this->formatStatusValue($status)), $count);
        }

        return $lines;
    }

    /**
     * Build one compact list of blocked or failed tasks that likely need operator input.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @return array<int, string>
     */
    public function buildAttentionTaskLines(array $tasks): array
    {
        if ($tasks === []) {
            return [];
        }

        $lines = [];

        foreach ($tasks as $task) {
            $status = trim(strtolower($task->status->value));

            if (! in_array($status, ['blocked', 'failed'], true)) {
                continue;
            }

            $prefix = $status === 'blocked'
                ? $this->button('blocked')
                : $this->button('failed');

            $taskId = $this->resolveModelId($task);
            $lines[] = sprintf('%s: #%d %s', $prefix, $taskId, Str::limit($task->summary, 90));
        }

        return array_slice($lines, 0, 3);
    }

    /**
     * Build one compact worker snapshot for the Telegram home dashboard.
     *
     * @param  AutoCodingMachine|null  $machine
     * @return array<int, string>
     */
    public function buildMachineDashboardLines(?AutoCodingMachine $machine): array
    {
        if (! $machine instanceof AutoCodingMachine) {
            return [];
        }

        $lines = [
            sprintf('%s: %s', $this->label('worker'), $machine->machine_key),
            sprintf('%s: %s', $this->label('status'), $this->formatMachineStatus($machine)),
        ];

        if (trim($machine->hostname) !== '') {
            $lines[] = sprintf('%s: %s', $this->label('host'), $machine->hostname);
        }

        if (is_string($machine->repository_path) && trim($machine->repository_path) !== '') {
            $lines[] = sprintf('%s: %s', $this->label('repository'), $machine->repository_path);
        }

        return $lines;
    }

    /**
     * Build one compact chat-session snapshot for the Telegram home dashboard.
     *
     * @param  array<string, mixed>|null  $chatSession
     * @return array<int, string>
     */
    public function buildChatSessionDashboardLines(?array $chatSession): array
    {
        if (! is_array($chatSession)) {
            return [];
        }

        $enabled = ($chatSession['enabled'] ?? false) === true;
        $sessionId = is_string($chatSession['session_id'] ?? null) ? trim((string) $chatSession['session_id']) : '';
        $mode = is_string($chatSession['mode'] ?? null) ? trim((string) $chatSession['mode']) : '';
        $activeTaskId = is_numeric($chatSession['active_task_id'] ?? null) ? (int) $chatSession['active_task_id'] : null;

        $lines = [
            sprintf('%s: %s', $this->label('chat_session'), $enabled ? $this->text('values.active') : $this->text('values.inactive')),
        ];

        if ($mode !== '') {
            $lines[] = sprintf('%s: %s', $this->label('chat_mode'), $mode);
        }

        if ($sessionId !== '') {
            $lines[] = sprintf('%s: %s', $this->label('session_id'), Str::limit($sessionId, 12, '...'));
        }

        if (is_int($activeTaskId) && $activeTaskId > 0) {
            $lines[] = sprintf('%s: #%d', $this->label('task'), $activeTaskId);
        }

        $recentEventLine = $this->buildChatSessionRecentActivityHeadline($chatSession);

        if ($recentEventLine !== null) {
            $lines[] = $recentEventLine;
        }

        return $lines;
    }

    /**
     * Build one compact chat-session block for task-oriented Telegram reports.
     *
     * @param  array<string, mixed>  $taskContext
     * @return array<int, string>
     */
    public function buildTaskChatSessionLines(array $taskContext): array
    {
        $transportContext = is_array($taskContext['transport_context'] ?? null)
            ? $taskContext['transport_context']
            : [];
        $chatSession = is_array($transportContext['chat_session'] ?? null)
            ? $transportContext['chat_session']
            : [];
        $sessionId = is_string($chatSession['session_id'] ?? null) ? trim((string) $chatSession['session_id']) : '';
        $mode = is_string($chatSession['mode'] ?? null) ? trim((string) $chatSession['mode']) : '';

        if ($sessionId === '' && $mode === '') {
            return [];
        }

        return array_values(array_filter([
            sprintf('%s: %s', $this->label('chat_session'), $this->text('values.active')),
            $mode !== '' ? sprintf('%s: %s', $this->label('chat_mode'), $mode) : null,
            $sessionId !== '' ? sprintf('%s: %s', $this->label('session_id'), Str::limit($sessionId, 12, '...')) : null,
        ], static fn (?string $line): bool => $line !== null));
    }

    /**
     * Build one compact workflow-progress block for chat-style Telegram reports.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    public function buildWorkflowProgressLines(array $report): array
    {
        /** @var array<string, mixed> $workflow */
        $workflow = is_array($report['workflow'] ?? null) ? $report['workflow'] : [];
        /** @var array<string, mixed> $followUp */
        $followUp = is_array($report['follow_up'] ?? null) ? $report['follow_up'] : [];
        /** @var array<string, mixed> $recommendedAction */
        $recommendedAction = is_array($report['recommended_action'] ?? null) ? $report['recommended_action'] : [];

        $phase = $this->resolveWorkflowPhaseLabel($workflow);
        $focus = $this->resolveWorkflowFocusText($workflow, $followUp, $recommendedAction);

        return array_values(array_filter([
            $phase !== null ? sprintf('%s: %s', $this->label('phase'), $phase) : null,
            $focus !== null ? sprintf('%s: %s', $this->label('focus'), $focus) : null,
        ], static fn (?string $line): bool => $line !== null));
    }

    /**
     * Build one multi-line chat-session timeline block from cached session events.
     *
     * @param  array<string, mixed>  $session
     * @return array<int, string>
     */
    public function buildChatSessionTimelineLines(array $session): array
    {
        $events = is_array($session['recent_events'] ?? null)
            ? array_values(array_filter($session['recent_events'], 'is_array'))
            : [];

        if ($events === []) {
            return [
                sprintf('%s: %s', $this->label('recent_activity'), $this->text('phrases.chat_session_no_recent_activity')),
            ];
        }

        $lines = [
            sprintf('%s:', $this->label('recent_activity')),
        ];

        foreach (array_reverse(array_slice($events, -4)) as $event) {
            /** @var array<string, mixed> $event */
            $lines[] = sprintf('- %s', $this->formatChatSessionTimelineEvent($event));
        }

        return $lines;
    }

    /**
     * Build one localized queue line for a task.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    public function formatQueueTaskLine(AutoCodingTask $task): string
    {
        $taskId = $this->resolveModelId($task);

        return sprintf(
            '#%d [%s] %s',
            $taskId,
            $this->formatStatusValue($task->status->value),
            Str::limit($task->summary, 90)
        );
    }

    /**
     * Localize one status value.
     *
     * @param  string  $status
     * @return string
     */
    public function formatStatusValue(string $status): string
    {
        return match (trim(strtolower($status))) {
            'pending' => $this->text('values.pending'),
            'running' => $this->text('values.running'),
            'completed' => $this->text('values.completed'),
            'failed' => $this->text('values.failed'),
            'blocked' => $this->text('values.blocked'),
            'cancelled' => $this->text('values.cancelled'),
            'skipped' => $this->text('values.skipped'),
            default => $status,
        };
    }

    /**
     * Localize one status value for headline use.
     *
     * @param  string  $status
     * @return string
     */
    public function formatStatusHeadline(string $status): string
    {
        $value = $this->formatStatusValue($status);

        return $this->localeService->isVietnamese()
            ? mb_strtoupper($value)
            : strtoupper($value);
    }

    /**
     * Localize one boolean value.
     *
     * @param  bool  $value
     * @return string
     */
    public function formatBoolean(bool $value): string
    {
        return $value
            ? $this->text('values.yes')
            : $this->text('values.no');
    }

    /**
     * Resolve the derived machine availability status label.
     *
     * @param  AutoCodingMachine  $machine
     * @return string
     */
    public function formatMachineStatus(AutoCodingMachine $machine): string
    {
        if ($machine->last_seen_at === null) {
            return $this->text('values.unknown');
        }

        $staleSeconds = config('opas.auto_coding.machine_stale_seconds');
        $threshold = is_numeric($staleSeconds) && (int) $staleSeconds > 0 ? (int) $staleSeconds : 0;

        return $machine->last_seen_at->diffInSeconds(now()) <= $threshold
            ? $this->text('values.online')
            : $this->text('values.stale');
    }

    /**
     * Resolve one user-facing workflow phase label from a report payload.
     *
     * @param  array<string, mixed>  $workflow
     * @return string|null
     */
    public function resolveWorkflowPhaseLabel(array $workflow): ?string
    {
        $stepKey = is_string($workflow['current_step'] ?? null)
            ? trim((string) $workflow['current_step'])
            : '';

        if ($stepKey === '') {
            $decisionPoint = is_array($workflow['current_decision_point'] ?? null)
                ? $workflow['current_decision_point']
                : [];
            $stepKey = is_string($decisionPoint['step'] ?? null)
                ? trim((string) $decisionPoint['step'])
                : '';
        }

        if ($stepKey === '') {
            return null;
        }

        $textKey = sprintf('phrases.workflow_step_%s', $stepKey);
        $label = $this->text($textKey);

        return $label !== $textKey ? $label : $stepKey;
    }

    /**
     * Resolve one user-facing workflow focus sentence from a report payload.
     *
     * @param  array<string, mixed>  $workflow
     * @param  array<string, mixed>  $followUp
     * @param  array<string, mixed>  $recommendedAction
     * @return string|null
     */
    public function resolveWorkflowFocusText(
        array $workflow,
        array $followUp = [],
        array $recommendedAction = [],
    ): ?string {
        $followUpMessage = is_string($followUp['message'] ?? null)
            ? trim((string) $followUp['message'])
            : '';

        if ($followUpMessage !== '') {
            return Str::limit($followUpMessage, 180);
        }

        $recommendedActionText = is_string($recommendedAction['action'] ?? null)
            ? trim((string) $recommendedAction['action'])
            : '';

        if ($recommendedActionText !== '') {
            return Str::limit($recommendedActionText, 180);
        }

        $decisionPoint = is_array($workflow['current_decision_point'] ?? null)
            ? $workflow['current_decision_point']
            : [];
        $decisionType = is_string($decisionPoint['type'] ?? null)
            ? trim((string) $decisionPoint['type'])
            : '';

        return match ($decisionType) {
            'blocked' => $this->text('phrases.workflow_focus_blocked'),
            'completed' => $this->text('phrases.workflow_focus_completed'),
            'failure' => $this->text('phrases.workflow_focus_failure'),
            'in_progress' => $this->text('phrases.workflow_focus_in_progress'),
            default => null,
        };
    }

    /**
     * Resolve one user-facing label for dangerous Telegram actions.
     *
     * @param  string  $actionKey
     * @return string
     */
    public function resolveDangerousActionLabel(string $actionKey): string
    {
        $normalizedKey = trim($actionKey);

        return match ($normalizedKey) {
            'cancel_task' => $this->button('cancel_task'),
            'cancel_tasks' => $this->button('cancel_all_active'),
            'delete_task' => $this->button('delete_task'),
            'delete_tasks' => $this->button('delete_all_pending'),
            default => $normalizedKey,
        };
    }

    /**
     * Build one compact dashboard line from the latest cached session event.
     *
     * @param  array<string, mixed>  $session
     * @return string|null
     */
    protected function buildChatSessionRecentActivityHeadline(array $session): ?string
    {
        $events = is_array($session['recent_events'] ?? null)
            ? array_values(array_filter($session['recent_events'], 'is_array'))
            : [];

        if ($events === []) {
            return null;
        }

        /** @var array<string, mixed> $latestEvent */
        $latestEvent = $events[array_key_last($events)];

        return sprintf(
            '%s: %s',
            $this->label('recent_activity'),
            Str::limit($this->formatChatSessionTimelineEvent($latestEvent), 90)
        );
    }

    /**
     * Format one cached chat-session event into operator-facing timeline copy.
     *
     * @param  array<string, mixed>  $event
     * @return string
     */
    protected function formatChatSessionTimelineEvent(array $event): string
    {
        $type = is_string($event['type'] ?? null) ? trim((string) $event['type']) : 'queued';
        $taskId = is_numeric($event['task_id'] ?? null) ? (int) $event['task_id'] : 0;
        $summary = is_string($event['summary'] ?? null) ? trim((string) $event['summary']) : '';
        $status = is_string($event['status'] ?? null) ? trim((string) $event['status']) : '';

        $base = match ($type) {
            'running' => $this->text('phrases.chat_timeline_running', ['task_id' => $taskId]),
            'completed' => $this->text('phrases.chat_timeline_completed', ['task_id' => $taskId]),
            'failed' => $this->text('phrases.chat_timeline_failed', ['task_id' => $taskId]),
            'blocked' => $this->text('phrases.chat_timeline_blocked', ['task_id' => $taskId]),
            'cancelled' => $this->text('phrases.chat_timeline_cancelled', ['task_id' => $taskId]),
            default => $this->text('phrases.chat_timeline_queued', ['task_id' => $taskId]),
        };

        $suffixParts = array_values(array_filter([
            $summary !== '' ? Str::limit($summary, 72) : null,
            $status !== '' ? sprintf('[%s]', $this->formatStatusValue($status)) : null,
        ], static fn (?string $value): bool => $value !== null && $value !== ''));

        return $suffixParts === []
            ? $base
            : sprintf('%s %s', $base, implode(' ', $suffixParts));
    }

    /**
     * Build one compact CI summary line for GitHub reporting when check counts exist.
     *
     * @param  array<string, mixed>  $ci
     * @return string|null
     */
    public function buildGitHubCiSummary(array $ci): ?string
    {
        $summary = is_string($ci['summary'] ?? null) ? trim((string) $ci['summary']) : '';
        $failedChecks = is_numeric($ci['failed_checks'] ?? null) ? (int) $ci['failed_checks'] : null;
        $totalChecks = is_numeric($ci['total_checks'] ?? null) ? (int) $ci['total_checks'] : null;

        if ($summary !== '') {
            return sprintf('%s: %s', $this->label('details'), $summary);
        }

        if ($failedChecks === null || $totalChecks === null) {
            return null;
        }

        return sprintf(
            '%s: %s',
            $this->label('details'),
            $this->text('phrases.ci_failed_check_ratio', [
                'failed' => $failedChecks,
                'total' => $totalChecks,
            ])
        );
    }

    /**
     * Build one localized GitHub headline from the available PR and CI status data.
     *
     * @param  array<string, mixed>  $githubContext
     * @param  array<string, mixed>  $pullRequest
     * @param  array<string, mixed>  $ci
     * @return string
     */
    public function buildGitHubHeadline(array $githubContext, array $pullRequest, array $ci): string
    {
        $prStatus = $this->normalizeGitHubStatus($pullRequest['status'] ?? null);
        $ciStatus = $this->normalizeGitHubStatus($ci['status'] ?? null);
        $compareUrl = is_string($githubContext['compare_url'] ?? null)
            ? trim((string) $githubContext['compare_url'])
            : '';

        return match (true) {
            in_array($ciStatus, ['failed', 'error', 'cancelled'], true) => $this->text('phrases.github_headline_ci_attention'),
            in_array($prStatus, ['open', 'ready', 'review'], true) && in_array($ciStatus, ['passed', 'success'], true) => $this->text('phrases.github_headline_pr_open_ci_passing'),
            in_array($prStatus, ['merged', 'closed'], true) => $this->text('phrases.github_headline_pr_closed'),
            $compareUrl !== '' => $this->text('phrases.github_headline_compare_ready'),
            default => $this->text('phrases.github_headline_local_fallback'),
        };
    }

    /**
     * Build one localized blocker list for the GitHub snapshot when no explicit list is persisted.
     *
     * @param  array<string, mixed>  $githubContext
     * @param  array<string, mixed>  $pullRequest
     * @param  array<string, mixed>  $ci
     * @return array<int, string>
     */
    public function buildGitHubBlockers(array $githubContext, array $pullRequest, array $ci): array
    {
        $blockers = [];
        $compareUrl = is_string($githubContext['compare_url'] ?? null)
            ? trim((string) $githubContext['compare_url'])
            : '';
        $prStatus = $this->normalizeGitHubStatus($pullRequest['status'] ?? null);
        $ciStatus = $this->normalizeGitHubStatus($ci['status'] ?? null);

        if ($compareUrl === '') {
            $blockers[] = $this->text('phrases.github_blocker_compare_unavailable');
        }

        if (in_array($prStatus, ['unavailable', 'missing', 'not_found'], true)) {
            $blockers[] = $this->text('phrases.github_blocker_pr_unavailable');
        }

        if (in_array($ciStatus, ['failed', 'error', 'cancelled'], true)) {
            $blockers[] = is_string($ci['reason'] ?? null) && trim((string) $ci['reason']) !== ''
                ? trim((string) $ci['reason'])
                : $this->text('phrases.github_blocker_ci_failed_default');
        }

        if ($ciStatus === 'unavailable') {
            $blockers[] = $this->text('phrases.github_blocker_ci_unavailable');
        }

        return array_values(array_unique(array_slice($blockers, 0, 3)));
    }

    /**
     * Build one localized next-action hint for the GitHub snapshot.
     *
     * @param  array<string, mixed>  $githubContext
     * @param  array<string, mixed>  $pullRequest
     * @param  array<string, mixed>  $ci
     * @return string|null
     */
    public function buildGitHubNextAction(array $githubContext, array $pullRequest, array $ci): ?string
    {
        $compareUrl = is_string($githubContext['compare_url'] ?? null)
            ? trim((string) $githubContext['compare_url'])
            : '';
        $prStatus = $this->normalizeGitHubStatus($pullRequest['status'] ?? null);
        $ciStatus = $this->normalizeGitHubStatus($ci['status'] ?? null);

        return match (true) {
            in_array($ciStatus, ['failed', 'error', 'cancelled'], true) => $this->text('phrases.github_next_action_fix_ci'),
            in_array($prStatus, ['unavailable', 'missing', 'not_found'], true) && $compareUrl !== '' => $this->text('phrases.github_next_action_open_compare_pr'),
            in_array($prStatus, ['open', 'ready', 'review'], true) && in_array($ciStatus, ['passed', 'success'], true) => $this->text('phrases.github_next_action_review_merge_pr'),
            in_array($prStatus, ['merged', 'closed'], true) => $this->text('phrases.github_next_action_no_follow_up'),
            $compareUrl !== '' => $this->text('phrases.github_next_action_manual_follow_up'),
            default => null,
        };
    }

    /**
     * Normalize one GitHub status token for formatter-side fallback logic.
     *
     * @param  mixed  $value
     * @return string
     */
    public function normalizeGitHubStatus(mixed $value): string
    {
        return is_string($value) && trim($value) !== ''
            ? strtolower(trim($value))
            : 'unavailable';
    }

    /**
     * Resolve one localized text string.
     *
     * @param  string  $key
     * @param  array<string, scalar|null>  $replace
     * @return string
     */
    public function text(string $key, array $replace = []): string
    {
        return $this->textService->line($key, $replace);
    }

    /**
     * Resolve one reusable Telegram label.
     *
     * @param  string  $key
     * @return string
     */
    public function label(string $key): string
    {
        return $this->text(sprintf('labels.%s', $key));
    }

    /**
     * Resolve one reusable Telegram button label.
     *
     * @param  string  $key
     * @return string
     */
    public function button(string $key): string
    {
        return $this->text(sprintf('buttons.%s', $key));
    }

    /**
     * Resolve one reusable Telegram copy list.
     *
     * @param  string  $key
     * @param  array<string, scalar|null>  $replace
     * @return array<int, string>
     */
    public function textLines(string $key, array $replace = []): array
    {
        return $this->textService->lines($key, $replace);
    }

    /**
     * Resolve one line from a configured Telegram copy list.
     *
     * @param  string  $key
     * @param  int  $index
     * @return string
     */
    public function textLineAt(string $key, int $index): string
    {
        return $this->textLines($key)[$index] ?? '';
    }
}
