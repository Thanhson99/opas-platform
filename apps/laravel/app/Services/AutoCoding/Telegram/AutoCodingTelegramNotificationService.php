<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;

class AutoCodingTelegramNotificationService
{
    public function __construct(
        private readonly AutoCodingTelegramBotService $botService,
        private readonly AutoCodingTelegramChatStateService $chatStateService,
        private readonly AutoCodingTelegramMessageFormatter $messageFormatter,
    ) {}

    /**
     * Send one help message back to the Telegram requester.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string|null  $error
     * @param  array<int, AutoCodingTask>  $tasks
     * @param  AutoCodingMachine|null  $machine
     * @param  array<string, mixed>|null  $chatSession
     * @return void
     */
    public function sendHelp(
        array $messageContext,
        ?string $error = null,
        array $tasks = [],
        ?AutoCodingMachine $machine = null,
        ?array $chatSession = null,
    ): void {
        $lines = [];

        if (is_string($error) && trim($error) !== '') {
            $lines[] = trim($error);
            $lines[] = '';
        }

        $lines[] = $this->messageFormatter->formatHelp($tasks, $machine, $chatSession);

        $this->sendToMessageContext($messageContext, implode("\n", $lines), [
            'reply_markup' => $this->buildRootKeyboard($tasks),
        ]);
    }

    /**
     * Send one menu-specific Telegram message with the matching inline keyboard.
     *
     * @param  string  $menuKey
     * @param  array<string, mixed>  $messageContext
     * @param  array<int, AutoCodingTask>  $tasks
     * @return void
     */
    public function sendMenu(string $menuKey, array $messageContext, array $tasks = []): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatMenu($menuKey, $tasks), [
            'reply_markup' => $this->buildMenuKeyboard($menuKey),
        ]);
    }

    /**
     * Send one plain text message without inline keyboard.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string  $text
     * @return void
     */
    public function sendPlain(array $messageContext, string $text): void
    {
        $this->sendToMessageContext($messageContext, $text);
    }

    /**
     * Send the root command menu to one configured chat when a bot is enabled from admin.
     *
     * @param  int|string  $chatId
     * @return void
     */
    public function sendStartupMenu(int|string $chatId): void
    {
        $this->sendToMessageContext([
            'chat_id' => $chatId,
        ], $this->messageFormatter->formatHelp(), [
            'reply_markup' => $this->buildRootKeyboard(),
        ]);
    }

    /**
     * Send the queued-task acknowledgement to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendQueued(AutoCodingTask $task, array $messageContext): void
    {
        $chatId = $this->resolveMessageContextChatId($messageContext);

        $this->rememberTaskTimelineEvent($chatId, $task, 'queued');

        if ($this->isDirectCodexChatTask($task)) {
            return;
        }

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatQueued($task), [
            'reply_markup' => $this->buildTaskKeyboard($task),
        ]);
    }

    /**
     * Send one task status snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendStatus(AutoCodingTask $task, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatStatus($task), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one task summary snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendSummary(AutoCodingTask $task, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatSummary($task), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one task next-action snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendNextAction(AutoCodingTask $task, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatNextAction($task), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one task follow-up snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendFollowUp(AutoCodingTask $task, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatFollowUp($task), [
            'reply_markup' => $this->buildPreferredTaskKeyboard($task),
        ]);
    }

    /**
     * Send one task validation snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendValidation(AutoCodingTask $task, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatValidation($task), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one GitHub-context snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $githubContext
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendGithubStatus(AutoCodingTask $task, array $githubContext, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatGithubStatus($task, $githubContext), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one changed-file snapshot back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendChanges(AutoCodingTask $task, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatChanges($task), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one latest-task queue summary back to the Telegram requester.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendQueue(array $tasks, array $messageContext, ?string $statusFilter = null): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatQueue($tasks, $statusFilter), [
            'reply_markup' => $this->buildQueueMenuKeyboard(),
        ]);
    }

    /**
     * Send one clarification prompt for an ambiguous Telegram message.
     *
     * @param  string  $originalText
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendIntentClarification(string $originalText, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatIntentClarification($originalText), [
            'reply_markup' => $this->buildIntentClarificationKeyboard(),
        ]);
    }

    /**
     * Send one issue-context clarification prompt when multiple reuse candidates conflict.
     *
     * @param  array<string, mixed>  $clarification
     * @param  array<string, mixed>  $messageContext
     * @param  string|null  $error
     * @return void
     */
    public function sendIssueContextClarification(
        array $clarification,
        array $messageContext,
        ?string $error = null,
    ): void {
        $lines = [];

        if (is_string($error) && trim($error) !== '') {
            $lines[] = trim($error);
            $lines[] = '';
        }

        $lines[] = $this->messageFormatter->formatIssueContextClarification($clarification);

        $this->sendToMessageContext($messageContext, implode("\n", $lines), [
            'reply_markup' => $this->buildIssueContextClarificationKeyboard($clarification),
        ]);
    }

    /**
     * Send one dangerous-action confirmation prompt back to the Telegram requester.
     *
     * @param  string  $actionLabel
     * @param  string|null  $targetLabel
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendDangerousActionConfirmation(
        string $actionLabel,
        ?string $targetLabel,
        array $messageContext,
    ): void {
        $this->sendToMessageContext(
            $messageContext,
            $this->messageFormatter->formatDangerousActionConfirmation($actionLabel, $targetLabel),
            [
                'reply_markup' => $this->buildPendingConfirmationKeyboard(),
            ]
        );
    }

    /**
     * Send one pending-interaction expiration message.
     *
     * @param  string  $type
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendPendingInteractionExpired(string $type, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatPendingInteractionExpired($type), [
            'reply_markup' => $this->buildRootKeyboard(),
        ]);
    }

    /**
     * Send one pending-interaction cancellation message.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendPendingInteractionCancelled(array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatPendingInteractionCancelled(), [
            'reply_markup' => $this->buildRootKeyboard(),
        ]);
    }

    /**
     * Send one single-task cancellation result back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendCancelTaskResult(AutoCodingTask $task, array $messageContext): void
    {
        $chatId = $this->resolveMessageContextChatId($messageContext);

        $this->rememberTaskTimelineEvent(
            $chatId,
            $task,
            $task->status->value === 'running' ? 'running' : 'cancelled'
        );

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatCancelTaskResult($task), [
            'reply_markup' => $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task)),
        ]);
    }

    /**
     * Send one bulk-cancellation summary back to the Telegram requester.
     *
     * @param  array{cancelled_count:int,cancellation_requested_count:int,unchanged_count:int}  $result
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendCancelTasksResult(array $result, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatCancelTasksResult($result), [
            'reply_markup' => $this->buildMenuKeyboard('maintenance'),
        ]);
    }

    /**
     * Send one permanent-delete result for a specific pending task.
     *
     * @param  array{id:int,summary:string}  $result
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendDeleteTaskResult(array $result, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatDeleteTaskResult($result), [
            'reply_markup' => $this->buildMenuKeyboard('maintenance'),
        ]);
    }

    /**
     * Send one bulk permanent-delete summary for pending tasks.
     *
     * @param  array{deleted_count:int,scope:string}  $result
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendDeleteTasksResult(array $result, array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatDeleteTasksResult($result), [
            'reply_markup' => $this->buildMenuKeyboard('maintenance'),
        ]);
    }

    /**
     * Send one running-task progress message to the originating Telegram chat.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun|null  $run
     * @return void
     */
    public function notifyRunning(AutoCodingTask $task, ?AutoCodingTaskRun $run = null): void
    {
        $context = $this->resolveTelegramContext($task);

        if ($context === null) {
            return;
        }

        $this->rememberTaskTimelineEvent($this->resolveMessageContextChatId($context), $task, 'running');

        if ($this->isDirectCodexChatTask($task)) {
            return;
        }

        $this->sendToMessageContext($context, $this->messageFormatter->formatRunning($task, $run), [
            'reply_markup' => $this->buildTaskKeyboard($task),
        ]);
    }

    /**
     * Send one terminal task outcome to the originating Telegram chat.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @return void
     */
    public function notifyOutcome(AutoCodingTask $task, AutoCodingTaskRun $run): void
    {
        $context = $this->resolveTelegramContext($task);

        if ($context === null) {
            return;
        }

        $this->rememberTaskTimelineEvent($this->resolveMessageContextChatId($context), $task, $run->status->value);

        if ($this->isDirectCodexChatTask($task)) {
            $this->sendToMessageContext($context, $this->messageFormatter->formatOutcomeForTask($task, $run), [
                'reply_markup' => $this->buildChatSessionKeyboard(true),
            ]);

            return;
        }

        $this->sendToMessageContext($context, $this->messageFormatter->formatOutcomeForTask($task, $run), [
            'reply_markup' => $this->buildPreferredTaskKeyboard($task),
        ]);
    }

    /**
     * Send one resume acknowledgement back to the Telegram requester.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendResumeAccepted(AutoCodingTask $task, array $messageContext): void
    {
        $this->rememberTaskTimelineEvent($this->resolveMessageContextChatId($messageContext), $task, 'running');

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatResumeAccepted($task), [
            'reply_markup' => $this->buildTaskKeyboard($task),
        ]);
    }

    /**
     * Clear the tracked bot messages for one Telegram chat and leave one compact fresh message behind.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  bool  $forceCleanup
     * @return void
     */
    public function resetChat(array $messageContext, bool $forceCleanup = false, string $scope = 'session'): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $messageIds = $this->resolveClearMessageIds($chatId, $scope);
        $currentMessageId = is_int($messageContext['message_id'] ?? null) ? $messageContext['message_id'] : null;
        $isCallbackReset = is_string($messageContext['callback_query_id'] ?? null)
            && trim((string) $messageContext['callback_query_id']) !== '';

        foreach ($messageIds as $messageId) {
            if (! $isCallbackReset && $currentMessageId !== null && $messageId === $currentMessageId) {
                continue;
            }

            $this->botService->deleteMessage($chatId, $messageId);
        }

        if ($forceCleanup && ! $isCallbackReset && $currentMessageId !== null && $currentMessageId > 0) {
            $this->botService->deleteMessage($chatId, $currentMessageId);
        }

        if ($isCallbackReset && $currentMessageId !== null && $currentMessageId > 0) {
            $this->botService->deleteMessage($chatId, $currentMessageId);
        }

        if (trim(strtolower($scope)) === 'all') {
            $this->chatStateService->forgetTrackedMessages($chatId);
        } else {
            $this->chatStateService->forgetTrackedMessageIds($chatId, $messageIds);
        }

        $this->chatStateService->forgetActiveTaskId($chatId);

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatResetComplete($forceCleanup, $scope), [
            'reply_markup' => $this->buildRootKeyboard(),
        ]);
    }

    /**
     * Send one direct chat-session started message back to the Telegram requester.
     *
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $messageContext
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return void
     */
    public function sendChatSessionStarted(
        array $session,
        array $messageContext,
        ?AutoCodingMachine $machine,
        ?AutoCodingTask $activeTask = null,
    ): void {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatChatSessionStarted($session, $machine, $activeTask), [
            'reply_markup' => $this->buildChatSessionKeyboard(true),
        ]);
    }

    /**
     * Send one direct chat-session status snapshot back to the Telegram requester.
     *
     * @param  array<string, mixed>|null  $session
     * @param  array<string, mixed>  $messageContext
     * @param  AutoCodingMachine|null  $machine
     * @param  AutoCodingTask|null  $activeTask
     * @return void
     */
    public function sendChatSessionStatus(
        ?array $session,
        array $messageContext,
        ?AutoCodingMachine $machine,
        ?AutoCodingTask $activeTask = null,
    ): void {
        $isActive = is_array($session) && ($session['enabled'] ?? false) === true;

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatChatSessionStatus($session, $machine, $activeTask), [
            'reply_markup' => $this->buildChatSessionKeyboard($isActive),
        ]);
    }

    /**
     * Send one compact connectivity acknowledgement without task history.
     *
     * @param  array<string, mixed>|null  $session
     * @param  array<string, mixed>  $messageContext
     * @param  AutoCodingMachine|null  $machine
     * @return void
     */
    public function sendChatPing(?array $session, array $messageContext, ?AutoCodingMachine $machine): void
    {
        $isActive = is_array($session) && ($session['enabled'] ?? false) === true;

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatChatPing($session, $machine), [
            'reply_markup' => $this->buildChatSessionKeyboard($isActive),
        ]);
    }

    /**
     * Send one direct chat-session stopped message back to the Telegram requester.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    public function sendChatSessionStopped(array $messageContext): void
    {
        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatChatSessionStopped(), [
            'reply_markup' => $this->buildChatSessionKeyboard(false),
        ]);
    }

    /**
     * Send one direct chat-session reset message back to the Telegram requester.
     *
     * @param  array<string, mixed>|null  $session
     * @param  array<string, mixed>  $messageContext
     * @param  AutoCodingMachine|null  $machine
     * @return void
     */
    public function sendChatSessionReset(
        ?array $session,
        array $messageContext,
        ?AutoCodingMachine $machine,
    ): void {
        $isActive = is_array($session) && ($session['enabled'] ?? false) === true;

        $this->sendToMessageContext($messageContext, $this->messageFormatter->formatChatSessionReset($session, $machine), [
            'reply_markup' => $this->buildChatSessionKeyboard($isActive),
        ]);
    }

    /**
     * Answer one callback query and optionally send a follow-up message.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string|null  $text
     * @return void
     */
    public function answerCallback(array $messageContext, ?string $text = null): void
    {
        $callbackQueryId = is_string($messageContext['callback_query_id'] ?? null)
            ? $messageContext['callback_query_id']
            : null;

        $this->botService->answerCallbackQuery($callbackQueryId, $text);
    }

    /**
     * Resolve the Telegram notification context stored on one task.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>|null
     */
    protected function resolveTelegramContext(AutoCodingTask $task): ?array
    {
        $contextPayload = is_array($task->context_payload) ? $task->context_payload : [];
        $transportContext = is_array($contextPayload['transport_context'] ?? null)
            ? $contextPayload['transport_context']
            : [];
        $telegramContext = $transportContext['telegram'] ?? null;

        /** @var array<string, mixed>|null $telegramContext */
        return is_array($telegramContext) ? $telegramContext : null;
    }

    /**
     * Resolve one Telegram chat id from a normalized message context.
     *
     * @param  array<string, mixed>  $messageContext
     * @return int|string|null
     */
    protected function resolveMessageContextChatId(array $messageContext): int|string|null
    {
        $chatId = $messageContext['chat_id'] ?? null;

        return is_string($chatId) || is_int($chatId)
            ? $chatId
            : null;
    }

    /**
     * Record one compact timeline event for the current direct chat session.
     *
     * @param  int|string|null  $chatId
     * @param  AutoCodingTask  $task
     * @param  string  $type
     * @return void
     */
    protected function rememberTaskTimelineEvent(int|string|null $chatId, AutoCodingTask $task, string $type): void
    {
        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $taskId = $this->resolveTaskId($task);

        if ($taskId <= 0) {
            return;
        }

        $this->chatStateService->rememberChatSessionEvent($chatId, [
            'type' => $type,
            'task_id' => $taskId,
            'summary' => $task->summary,
            'status' => $task->status->value,
            'created_at' => now()->toAtomString(),
        ]);
    }

    /**
     * Determine whether one task is a direct Telegram chat reply rather than a visible coding job.
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
     * Send one message to the Telegram chat referenced by the normalized context.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string  $text
     * @param  array<string, mixed>  $options
     * @return void
     */
    protected function sendToMessageContext(array $messageContext, string $text, array $options = []): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $threadId = $messageContext['message_thread_id'] ?? null;

        if (is_int($threadId)) {
            $options['message_thread_id'] = $threadId;
        }

        $response = $this->botService->sendMessage($chatId, $text, $options);
        $result = is_array($response['result'] ?? null) ? $response['result'] : [];
        $messageId = is_numeric($result['message_id'] ?? null) ? (int) $result['message_id'] : null;

        if ($messageId !== null && $messageId > 0) {
            $this->chatStateService->rememberBotMessage($chatId, $messageId);
        }
    }

    /**
     * Build the root inline keyboard shown on help and queue messages.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @return array<string, mixed>
     */
    protected function buildRootKeyboard(array $tasks = []): array
    {
        return $this->buildCompactControlKeyboard();
    }

    /**
     * Build the compact operator keyboard used by direct chat and queue messages.
     *
     * @return array<string, mixed>
     */
    protected function buildCompactControlKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('chat_start'), 'callback_data' => 'ac:chat:start'],
                    ['text' => $this->messageFormatter->formatButtonLabel('queue'), 'callback_data' => 'ac:queue'],
                ],
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('delete_task'), 'callback_data' => 'ac:delete:latest:pending'],
                    ['text' => $this->messageFormatter->formatButtonLabel('delete_all_pending'), 'callback_data' => 'ac:delete-all'],
                ],
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('clear_chat'), 'callback_data' => 'ac:reset:session'],
                    ['text' => $this->messageFormatter->formatButtonLabel('clear_all_chat'), 'callback_data' => 'ac:reset:all'],
                ],
            ],
        ];
    }

    /**
     * Build one named menu keyboard for Telegram navigation.
     *
     * @param  string  $menuKey
     * @return array<string, mixed>
     */
    protected function buildMenuKeyboard(string $menuKey): array
    {
        return match (trim(strtolower($menuKey))) {
            'queue' => $this->buildQueueMenuKeyboard(),
            default => $this->buildRootKeyboard(),
        };
    }

    /**
     * Build the queue management submenu keyboard.
     *
     * @return array<string, mixed>
     */
    protected function buildQueueMenuKeyboard(): array
    {
        return $this->buildCompactControlKeyboard();
    }

    /**
     * Resolve bot messages to delete for one clear-chat request.
     *
     * @param  int|string  $chatId
     * @param  string  $scope
     * @return array<int, int>
     */
    protected function resolveClearMessageIds(int|string $chatId, string $scope): array
    {
        if (trim(strtolower($scope)) === 'all') {
            return $this->chatStateService->getTrackedMessageIds($chatId);
        }

        return $this->chatStateService->getCurrentSessionMessageIds($chatId);
    }

    /**
     * Build the inline keyboard used by direct chat-session messages.
     *
     * @param  bool  $isActive
     * @return array<string, mixed>
     */
    protected function buildChatSessionKeyboard(bool $isActive): array
    {
        if ($isActive) {
            return [
                'inline_keyboard' => [
                    [
                        ['text' => $this->messageFormatter->formatButtonLabel('chat_stop'), 'callback_data' => 'ac:chat:stop'],
                    ],
                ],
            ];
        }

        return $this->buildCompactControlKeyboard();
    }

    /**
     * Build one inline keyboard for ambiguous-intent clarification.
     *
     * @return array<string, mixed>
     */
    protected function buildIntentClarificationKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('code'), 'callback_data' => 'ac:clarify:code'],
                    ['text' => $this->messageFormatter->formatButtonLabel('review'), 'callback_data' => 'ac:clarify:review'],
                ],
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('validate'), 'callback_data' => 'ac:clarify:validate'],
                    ['text' => $this->messageFormatter->formatButtonLabel('status'), 'callback_data' => 'ac:clarify:status'],
                ],
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('summary'), 'callback_data' => 'ac:clarify:summary'],
                    ['text' => $this->messageFormatter->formatButtonLabel('changes'), 'callback_data' => 'ac:clarify:changes'],
                ],
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('github'), 'callback_data' => 'ac:clarify:github'],
                    ['text' => $this->messageFormatter->formatButtonLabel('queue'), 'callback_data' => 'ac:clarify:queue'],
                ],
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('confirm_no'), 'callback_data' => 'ac:clarify:cancel'],
                ],
            ],
        ];
    }

    /**
     * Build the inline keyboard for one conflicting issue-context clarification.
     *
     * @param  array<string, mixed>  $clarification
     * @return array<string, mixed>
     */
    protected function buildIssueContextClarificationKeyboard(array $clarification): array
    {
        $rows = [];
        $candidates = is_array($clarification['candidates'] ?? null) ? $clarification['candidates'] : [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! is_numeric($candidate['task_id'] ?? null)) {
                continue;
            }

            /** @var array<string, mixed> $candidate */
            $rawTaskId = $candidate['task_id'];
            $taskId = is_int($rawTaskId) ? $rawTaskId : (is_string($rawTaskId) ? (int) $rawTaskId : 0);
            $rows[] = [[
                'text' => $this->messageFormatter->formatIssueContextChoiceLabel($candidate),
                'callback_data' => sprintf('ac:issue-context:%d', $taskId),
            ]];
        }

        $rows[] = [[
            'text' => $this->messageFormatter->formatButtonLabel('confirm_no'),
            'callback_data' => 'ac:issue-context:cancel',
        ]];

        return [
            'inline_keyboard' => $rows,
        ];
    }

    /**
     * Build one inline keyboard for pending dangerous-action confirmation.
     *
     * @return array<string, mixed>
     */
    protected function buildPendingConfirmationKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => $this->messageFormatter->formatButtonLabel('confirm_yes'), 'callback_data' => 'ac:confirm:yes'],
                    ['text' => $this->messageFormatter->formatButtonLabel('confirm_no'), 'callback_data' => 'ac:confirm:no'],
                ],
            ],
        ];
    }

    /**
     * Build one task-specific inline keyboard for common remote workflows.
     *
     * @param  AutoCodingTask  $task
     * @param  bool  $includeResume
     * @return array<string, mixed>
     */
    protected function buildTaskKeyboard(AutoCodingTask $task, bool $includeResume = false): array
    {
        $taskId = $this->resolveTaskId($task);
        $rows = [
            [
                ['text' => $this->messageFormatter->formatButtonLabel('status'), 'callback_data' => sprintf('ac:status:%d', $taskId)],
                ['text' => $this->messageFormatter->formatButtonLabel('summary'), 'callback_data' => sprintf('ac:summary:%d', $taskId)],
            ],
            [
                ['text' => $this->messageFormatter->formatButtonLabel('next_action'), 'callback_data' => sprintf('ac:next:%d', $taskId)],
                ['text' => $this->messageFormatter->formatButtonLabel('follow_up'), 'callback_data' => sprintf('ac:followup:%d', $taskId)],
            ],
            [
                ['text' => $this->messageFormatter->formatButtonLabel('validation'), 'callback_data' => sprintf('ac:validation:%d', $taskId)],
                ['text' => $this->messageFormatter->formatButtonLabel('changes'), 'callback_data' => sprintf('ac:changes:%d', $taskId)],
            ],
            [
                ['text' => $this->messageFormatter->formatButtonLabel('github'), 'callback_data' => sprintf('ac:github:%d', $taskId)],
                ['text' => $this->messageFormatter->formatButtonLabel('queue'), 'callback_data' => 'ac:queue'],
            ],
            [
                ['text' => $this->messageFormatter->formatButtonLabel('cancel_task'), 'callback_data' => sprintf('ac:cancel:%d', $taskId)],
            ],
            [
                ['text' => $this->messageFormatter->formatButtonLabel('reset'), 'callback_data' => 'ac:reset'],
            ],
        ];

        if ($includeResume) {
            $rows[] = [
                ['text' => $this->messageFormatter->formatButtonLabel('resume'), 'callback_data' => sprintf('ac:resume:%d:allow', $taskId)],
            ];
        }

        return [
            'inline_keyboard' => $rows,
        ];
    }

    /**
     * Build the best available inline keyboard for one task outcome.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    protected function buildPreferredTaskKeyboard(AutoCodingTask $task): array
    {
        $followUpKeyboard = $this->buildFollowUpKeyboard($task);

        return $followUpKeyboard ?? $this->buildTaskKeyboard($task, $this->taskSupportsQuickResume($task));
    }

    /**
     * Build one follow-up-driven keyboard for blocked tasks when Telegram can answer safely by button.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>|null
     */
    protected function buildFollowUpKeyboard(AutoCodingTask $task): ?array
    {
        if ($task->status->value !== 'blocked') {
            return null;
        }

        $followUp = is_array($task->latest_report['follow_up'] ?? null) ? $task->latest_report['follow_up'] : [];
        /** @var array<string, mixed> $inputContract */
        $inputContract = is_array($followUp['input_contract'] ?? null) ? $followUp['input_contract'] : [];
        /** @var array<int, array<string, mixed>> $questionContracts */
        $questionContracts = is_array($followUp['question_contracts'] ?? null)
            ? array_values(array_filter($followUp['question_contracts'], 'is_array'))
            : [];
        $taskId = $this->resolveTaskId($task);

        if (($inputContract['type'] ?? null) === 'confirmation') {
            return $this->buildConfirmationFollowUpKeyboard($taskId, $inputContract);
        }

        return $this->buildSingleQuestionFollowUpKeyboard($taskId, $questionContracts);
    }

    /**
     * Build one quick-action keyboard for confirmation-type follow-up contracts.
     *
     * @param  int  $taskId
     * @param  array<string, mixed>  $inputContract
     * @return array<string, mixed>|null
     */
    protected function buildConfirmationFollowUpKeyboard(int $taskId, array $inputContract): ?array
    {
        $acceptedValues = $this->normalizeQuickActionValues($inputContract['accepted_values'] ?? []);

        if ($acceptedValues === []) {
            return null;
        }

        return [
            'inline_keyboard' => [
                array_map(
                    fn (string $value): array => [
                        'text' => ucfirst($value),
                        'callback_data' => sprintf('ac:resume:%d:%s', $taskId, $value),
                    ],
                    array_slice($acceptedValues, 0, 3)
                ),
            ],
        ];
    }

    /**
     * Build one quick-action keyboard for one-question follow-up contracts.
     *
     * @param  int  $taskId
     * @param  array<int, mixed>  $questionContracts
     * @return array<string, mixed>|null
     */
    protected function buildSingleQuestionFollowUpKeyboard(int $taskId, array $questionContracts): ?array
    {
        if (count($questionContracts) !== 1 || ! is_array($questionContracts[0])) {
            return null;
        }

        /** @var array<string, mixed> $questionContract */
        $questionContract = $questionContracts[0];
        $options = is_array($questionContract['options'] ?? null)
            ? array_values(array_filter($questionContract['options'], 'is_array'))
            : [];
        $acceptedValues = $this->normalizeQuickActionValues($questionContract['accepted_values'] ?? []);

        if ($options === [] && $acceptedValues === []) {
            return null;
        }

        $buttons = $this->buildOptionButtons($options, $taskId);

        if ($buttons === [] && $acceptedValues !== []) {
            $buttons = $this->buildAcceptedValueButtons($acceptedValues, $taskId);
        }

        if ($buttons === []) {
            return null;
        }

        return [
            'inline_keyboard' => array_map(
                static fn (array $button): array => [$button],
                $buttons
            ),
        ];
    }

    /**
     * Build option-based quick-answer buttons from one follow-up option list.
     *
     * @param  array<int, mixed>  $options
     * @param  int  $taskId
     * @return array<int, array{text:string,callback_data:string}>
     */
    protected function buildOptionButtons(array $options, int $taskId): array
    {
        $buttons = [];

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = is_string($option['label'] ?? null) ? trim($option['label']) : '';
            $value = is_string($option['value'] ?? null) ? trim($option['value']) : '';

            if ($label === '' || $value === '') {
                continue;
            }

            $buttons[] = [
                'text' => $label,
                'callback_data' => sprintf('ac:ra:%d:0:%s', $taskId, $value),
            ];
        }

        return $buttons;
    }

    /**
     * Build accepted-value quick-answer buttons when explicit options are absent.
     *
     * @param  array<int, string>  $acceptedValues
     * @param  int  $taskId
     * @return array<int, array{text:string,callback_data:string}>
     */
    protected function buildAcceptedValueButtons(array $acceptedValues, int $taskId): array
    {
        $buttons = [];

        foreach (array_slice($acceptedValues, 0, 4) as $value) {
            $buttons[] = [
                'text' => ucfirst($value),
                'callback_data' => sprintf('ac:ra:%d:0:%s', $taskId, $value),
            ];
        }

        return $buttons;
    }

    /**
     * Determine whether one task can expose a quick-resume action in Telegram.
     *
     * @param  AutoCodingTask  $task
     * @return bool
     */
    protected function taskSupportsQuickResume(AutoCodingTask $task): bool
    {
        if ($task->status->value !== 'blocked') {
            return false;
        }

        $followUp = is_array($task->latest_report['follow_up'] ?? null) ? $task->latest_report['follow_up'] : [];
        $inputContract = is_array($followUp['input_contract'] ?? null) ? $followUp['input_contract'] : [];

        return ($inputContract['type'] ?? null) === 'confirmation';
    }

    /**
     * Normalize mixed quick-action values into stable Telegram callback values.
     *
     * @param  mixed  $values
     * @return array<int, string>
     */
    protected function normalizeQuickActionValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalizedValues = [];

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $normalizedValues[] = trim($value);
        }

        return array_values(array_unique($normalizedValues));
    }

    /**
     * Determine whether one task list contains a specific execution status.
     *
     * @param  array<int, AutoCodingTask>  $tasks
     * @param  string  $status
     * @return bool
     */
    protected function hasTaskWithStatus(array $tasks, string $status): bool
    {
        foreach ($tasks as $task) {
            if (trim(strtolower($task->status->value)) === trim(strtolower($status))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve one numeric task id safely from the Eloquent model key.
     *
     * @param  AutoCodingTask  $task
     * @return int
     */
    protected function resolveTaskId(AutoCodingTask $task): int
    {
        $key = $task->getKey();

        return is_numeric($key) ? (int) $key : 0;
    }
}
