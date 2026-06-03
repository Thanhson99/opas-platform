<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingGitHubStatusQueryService;
use App\Services\AutoCoding\AutoCodingIssueContextEnrichmentService;
use App\Services\AutoCoding\AutoCodingMachineQueryService;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\AutoCodingTaskQueryService;
use Illuminate\Validation\ValidationException;

class AutoCodingTelegramWebhookService
{
    public function __construct(
        private readonly AutoCodingTelegramAccessControlService $accessControlService,
        private readonly AutoCodingTelegramBotService $botService,
        private readonly AutoCodingTelegramChatStateService $chatStateService,
        private readonly AutoCodingTelegramCommandParser $commandParser,
        private readonly AutoCodingTelegramIntentResolver $intentResolver,
        private readonly AutoCodingTelegramNotificationService $notificationService,
        private readonly AutoCodingTelegramTextService $textService,
        private readonly AutoCodingGitHubStatusQueryService $gitHubStatusQueryService,
        private readonly AutoCodingIssueContextEnrichmentService $issueContextEnrichmentService,
        private readonly AutoCodingMachineQueryService $machineQueryService,
        private readonly AutoCodingTaskDispatchService $taskDispatchService,
        private readonly AutoCodingTaskQueryService $taskQueryService,
    ) {}

    /**
     * Execute one incoming Telegram remote-control update.
     *
     * @param  array<string, mixed>  $update
     * @return void
     */
    public function handle(array $update): void
    {
        if (! $this->accessControlService->isEnabled()) {
            return;
        }

        /** @var array<string, mixed> $action */
        $action = $this->resolveParsedAction($update);
        $messageContext = $this->normalizeMessageContext($action['message_context'] ?? []);

        $chatId = $messageContext['chat_id'] ?? null;
        $userId = $messageContext['user_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            $chatId = null;
        }

        if (! is_string($userId) && ! is_int($userId)) {
            $userId = null;
        }

        if (! $this->accessControlService->isAuthorized([
            'chat_id' => $chatId,
            'user_id' => $userId,
        ])) {
            $this->sendUnauthorizedChatNotice($chatId, $userId);

            return;
        }

        $this->rememberAuthorizedIncomingMessage($messageContext);

        $actionKey = is_string($action['action'] ?? null) ? $action['action'] : 'help';

        if ($actionKey === 'media_message') {
            $this->handleMediaMessage($messageContext);

            return;
        }

        if (! $this->accessControlService->isActionAllowed($actionKey)) {
            $tasks = $this->resolveDashboardTasks();

            $this->notificationService->sendHelp($messageContext, sprintf(
                $this->textService->line('errors.action_not_allowed', [
                    'action' => '%s',
                ]),
                $actionKey
            ), $tasks, $this->machineQueryService->findLatestDetailed(), $this->resolveChatSession($messageContext));

            return;
        }

        if (($messageContext['callback_query_id'] ?? null) !== null) {
            $this->notificationService->answerCallback(
                $messageContext,
                $this->resolveCallbackAcknowledgement($action)
            );
        }

        try {
            $this->dispatchAction($action, $messageContext, $actionKey);
        } catch (ValidationException $exception) {
            $tasks = $this->resolveDashboardTasks();

            $this->notificationService->sendHelp(
                $messageContext,
                $this->resolveValidationMessage($exception),
                $tasks,
                $this->machineQueryService->findLatestDetailed(),
                $this->resolveChatSession($messageContext)
            );
        }
    }

    /**
     * Tell an operator which IDs must be allowed when the bot receives a blocked chat.
     *
     * @param  int|string|null  $chatId
     * @param  int|string|null  $userId
     * @return void
     */
    protected function sendUnauthorizedChatNotice(int|string|null $chatId, int|string|null $userId): void
    {
        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $lines = [
            'This Telegram chat is not allowed to control OPAS yet.',
            sprintf('Chat ID: %s', (string) $chatId),
        ];

        if (is_string($userId) || is_int($userId)) {
            $lines[] = sprintf('User ID: %s', (string) $userId);
        }

        $lines[] = 'Add one of these IDs in Admin > Telegram Bots > Who can use it, then try /start again.';

        $this->botService->sendMessage($chatId, implode("\n", $lines));
    }

    /**
     * Track authorized operator messages so group cleanup can remove them when Telegram permissions allow.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function rememberAuthorizedIncomingMessage(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;
        $messageId = $messageContext['message_id'] ?? null;

        if ((! is_string($chatId) && ! is_int($chatId)) || ! is_int($messageId) || $messageId <= 0) {
            return;
        }

        $this->chatStateService->rememberOperatorMessage($chatId, $messageId);
    }

    /**
     * Handle one remote create-task request from Telegram.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleCreateTask(array $action, array $messageContext): void
    {
        /** @var array{summary:string, issue_key?:string, repository_path?:string, validate?:bool, provider?:string, provider_options?:array<string, mixed>, dirty_workspace_policy?:string, scope_paths?:array<int, string>, scope_policy?:string, context_metadata?:array<string, mixed>} $taskPayload */
        $taskPayload = is_array($action['task_payload'] ?? null) ? $action['task_payload'] : [];
        $taskPayload = $this->attachChatSessionContextToTaskPayload($taskPayload, $messageContext);
        /** @var array{task_payload: array<string, mixed>, clarification?: array<string, mixed>} $resolution */
        $resolution = $this->issueContextEnrichmentService->resolveTaskPayload($taskPayload);
        $clarification = is_array($resolution['clarification'] ?? null) ? $resolution['clarification'] : null;

        if ($clarification !== null) {
            $this->rememberPendingIssueContextClarification($messageContext, $taskPayload, $clarification);
            $this->notificationService->sendIssueContextClarification($clarification, $messageContext);

            return;
        }

        /** @var array{summary:string, issue_key?:string, repository_path?:string, validate?:bool, provider?:string, provider_options?:array<string, mixed>, dirty_workspace_policy?:string, scope_paths?:array<int, string>, scope_policy?:string, context_metadata?:array<string, mixed>} $taskPayload */
        $taskPayload = $resolution['task_payload'];
        $task = $this->taskDispatchService->createPendingTaskFromPayload($taskPayload);
        $this->rememberActiveTask($task, $messageContext);

        $this->notificationService->sendQueued($task, $messageContext);
    }

    /**
     * Handle one Telegram task-status request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleStatus(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('status', $action, $messageContext, 'latest');
    }

    /**
     * Handle one Telegram next-action request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleNextAction(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('next_action', $action, $messageContext, 'latest');
    }

    /**
     * Handle one Telegram follow-up request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleFollowUp(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('follow_up', $action, $messageContext, 'latest:blocked');
    }

    /**
     * Handle one Telegram validation-summary request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleValidation(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('validation', $action, $messageContext, 'latest');
    }

    /**
     * Handle one Telegram GitHub-status request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleGithubStatus(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('github_status', $action, $messageContext, 'latest');
    }

    /**
     * Handle one Telegram task-summary request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleSummary(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('summary', $action, $messageContext, 'latest');
    }

    /**
     * Handle one Telegram changed-file request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleChanges(array $action, array $messageContext): void
    {
        $this->handleTaskLookupResult('changes', $action, $messageContext, 'latest');
    }

    /**
     * Handle one Telegram queue-summary request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleQueue(array $action, array $messageContext): void
    {
        $statusFilter = is_string($action['status_filter'] ?? null)
            ? trim((string) $action['status_filter'])
            : null;
        $tasks = $this->taskQueryService->getLatest(5, $statusFilter, null);

        $this->notificationService->sendQueue($tasks, $messageContext, $statusFilter);
    }

    /**
     * Handle one Telegram clarification request for ambiguous conversational input.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleClarifyIntent(array $action, array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $intent = is_string($action['intent'] ?? null) ? trim((string) $action['intent']) : '';
        $originalText = is_string($action['original_text'] ?? null) ? trim((string) $action['original_text']) : '';

        if ($intent === '' && $originalText !== '') {
            $this->chatStateService->rememberPendingInteraction($chatId, [
                'type' => 'clarify_intent',
                'original_text' => $originalText,
            ]);
            $this->notificationService->sendIntentClarification($originalText, $messageContext);

            return;
        }

        $pendingInteraction = $this->chatStateService->getPendingInteraction($chatId);

        if (! is_array($pendingInteraction) || ($pendingInteraction['type'] ?? null) !== 'clarify_intent') {
            $this->notificationService->sendPendingInteractionExpired('clarify_intent', $messageContext);

            return;
        }

        if ($intent === '') {
            $this->notificationService->sendIntentClarification(
                is_string($pendingInteraction['original_text'] ?? null) ? trim((string) $pendingInteraction['original_text']) : '',
                $messageContext
            );

            return;
        }

        if (in_array(strtolower($intent), ['cancel', 'no', 'khong'], true)) {
            $this->chatStateService->forgetPendingInteraction($chatId);
            $this->notificationService->sendPendingInteractionCancelled($messageContext);

            return;
        }

        $this->chatStateService->forgetPendingInteraction($chatId);
        $resolvedAction = $this->intentResolver->resolveClarifiedIntent(
            $intent,
            is_string($pendingInteraction['original_text'] ?? null) ? trim((string) $pendingInteraction['original_text']) : '',
            $messageContext
        );
        $resolvedActionKey = is_string($resolvedAction['action'] ?? null) ? trim((string) $resolvedAction['action']) : 'help';

        $this->dispatchAction($resolvedAction, $messageContext, $resolvedActionKey);
    }

    /**
     * Handle one Telegram issue-context clarification response.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleClarifyIssueContext(array $action, array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $pendingInteraction = $this->chatStateService->getPendingInteraction($chatId);

        if (! is_array($pendingInteraction) || ($pendingInteraction['type'] ?? null) !== 'clarify_issue_context') {
            $this->notificationService->sendPendingInteractionExpired('clarify_issue_context', $messageContext);

            return;
        }

        /** @var array<string, mixed> $clarification */
        $clarification = is_array($pendingInteraction['clarification'] ?? null)
            ? $pendingInteraction['clarification']
            : [];
        $selection = is_string($action['selection'] ?? null) ? trim((string) $action['selection']) : '';

        if ($selection === '') {
            $this->notificationService->sendIssueContextClarification($clarification, $messageContext);

            return;
        }

        if (in_array(mb_strtolower($selection), ['cancel', 'no', 'khong', 'không'], true)) {
            $this->chatStateService->forgetPendingInteraction($chatId);
            $this->notificationService->sendPendingInteractionCancelled($messageContext);

            return;
        }

        $selectedSourceTaskId = $this->resolveIssueContextSelection($selection, $clarification);

        if ($selectedSourceTaskId === null) {
            $this->notificationService->sendIssueContextClarification(
                $clarification,
                $messageContext,
                $this->textService->line('errors.issue_context_selection_invalid')
            );

            return;
        }

        /** @var array{summary:string, issue_key?:string, repository_path?:string, validate?:bool, provider?:string, provider_options?:array<string, mixed>, dirty_workspace_policy?:string, scope_paths?:array<int, string>, scope_policy?:string, context_metadata?:array<string, mixed>} $taskPayload */
        $taskPayload = is_array($pendingInteraction['task_payload'] ?? null) ? $pendingInteraction['task_payload'] : [];
        $taskPayload = $this->applySelectedIssueContextSource($taskPayload, $selectedSourceTaskId);
        $this->chatStateService->forgetPendingInteraction($chatId);
        /** @var array{summary:string, issue_key?:string, repository_path?:string, validate?:bool, provider?:string, provider_options?:array<string, mixed>, dirty_workspace_policy?:string, scope_paths?:array<int, string>, scope_policy?:string, context_metadata?:array<string, mixed>} $resolvedTaskPayload */
        $resolvedTaskPayload = $this->issueContextEnrichmentService->enrichTaskPayload($taskPayload);
        $task = $this->taskDispatchService->createPendingTaskFromPayload($resolvedTaskPayload);
        $this->rememberActiveTask($task, $messageContext);

        $this->notificationService->sendQueued($task, $messageContext);
    }

    /**
     * Handle one Telegram single-task cancel request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleCancelTask(array $action, array $messageContext): void
    {
        $task = $this->resolveTaskOrNotify($action['task_reference'] ?? 'latest:running', $messageContext);

        if (! $task instanceof AutoCodingTask) {
            return;
        }

        if (($action['confirmed'] ?? false) !== true) {
            $this->rememberPendingDangerousAction($messageContext, [
                'action' => 'cancel_task',
                'task_reference' => (string) $this->resolveTaskId($task),
                'confirmed' => true,
            ]);
            $this->notificationService->sendDangerousActionConfirmation(
                'cancel_task',
                sprintf('#%d %s', $this->resolveTaskId($task), $task->summary),
                $messageContext
            );

            return;
        }

        $cancelledTask = $this->taskDispatchService->cancelTask($this->resolveTaskId($task));
        $this->notificationService->sendCancelTaskResult($cancelledTask, $messageContext);
    }

    /**
     * Handle one Telegram bulk-cancel request for active tasks.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleCancelTasks(array $messageContext): void
    {
        if (! $this->shouldRunConfirmedDangerousAction($messageContext, 'cancel_tasks')) {
            $this->rememberPendingDangerousAction($messageContext, [
                'action' => 'cancel_tasks',
                'scope' => 'active',
                'confirmed' => true,
            ]);
            $this->notificationService->sendDangerousActionConfirmation('cancel_tasks', 'active', $messageContext);

            return;
        }

        $result = $this->taskDispatchService->cancelActiveTasks();
        $this->notificationService->sendCancelTasksResult($result, $messageContext);
    }

    /**
     * Handle one Telegram permanent-delete request for a pending task.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleDeleteTask(array $action, array $messageContext): void
    {
        $task = $this->resolveTaskOrNotify($action['task_reference'] ?? 'latest:pending', $messageContext);

        if (! $task instanceof AutoCodingTask) {
            return;
        }

        if (($action['confirmed'] ?? false) !== true) {
            $this->rememberPendingDangerousAction($messageContext, [
                'action' => 'delete_task',
                'task_reference' => (string) $this->resolveTaskId($task),
                'confirmed' => true,
            ]);
            $this->notificationService->sendDangerousActionConfirmation(
                'delete_task',
                sprintf('#%d %s', $this->resolveTaskId($task), $task->summary),
                $messageContext
            );

            return;
        }

        $result = $this->taskDispatchService->deletePendingTask($this->resolveTaskId($task));
        $this->forgetActiveTaskWhenMissing($messageContext);
        $this->notificationService->sendDeleteTaskResult($result, $messageContext);
    }

    /**
     * Handle one Telegram bulk permanent-delete request for pending tasks or all tasks.
     *
     * `pending` keeps the original safe delete-all behavior.
     * `all` is the explicit operator opt-in for clearing every persisted task row.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleDeleteTasks(array $action, array $messageContext): void
    {
        $scope = is_string($action['scope'] ?? null) ? trim((string) $action['scope']) : 'pending';

        if (! $this->shouldRunConfirmedDangerousAction($messageContext, 'delete_tasks')) {
            $this->rememberPendingDangerousAction($messageContext, [
                'action' => 'delete_tasks',
                'scope' => $scope,
                'confirmed' => true,
            ]);
            $this->notificationService->sendDangerousActionConfirmation('delete_tasks', $scope, $messageContext);

            return;
        }

        $result = $scope === 'all'
            ? $this->taskDispatchService->purgeTasks('all')
            : $this->taskDispatchService->deletePendingTasks();
        $this->forgetActiveTaskWhenMissing($messageContext);
        $this->notificationService->sendDeleteTasksResult($result, $messageContext);
    }

    /**
     * Handle one Telegram confirmation response for a pending dangerous action.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleConfirmPending(array $action, array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $pendingInteraction = $this->chatStateService->getPendingInteraction($chatId);

        if (! is_array($pendingInteraction) || ($pendingInteraction['type'] ?? null) !== 'dangerous_action') {
            $this->notificationService->sendPendingInteractionExpired('dangerous_action', $messageContext);

            return;
        }

        $decision = is_string($action['decision'] ?? null)
            ? trim(strtolower((string) $action['decision']))
            : '';

        if (! in_array($decision, ['yes', 'confirm', 'ok', 'dong y', 'đồng ý', 'co', 'có', 'no', 'cancel', 'khong', 'không'], true)) {
            $this->notificationService->sendDangerousActionConfirmation(
                is_string($pendingInteraction['action_label'] ?? null) ? trim((string) $pendingInteraction['action_label']) : 'confirm',
                is_string($pendingInteraction['target_label'] ?? null) ? trim((string) $pendingInteraction['target_label']) : null,
                $messageContext
            );

            return;
        }

        if (in_array($decision, ['no', 'cancel', 'khong', 'không'], true)) {
            $this->chatStateService->forgetPendingInteraction($chatId);
            $this->notificationService->sendPendingInteractionCancelled($messageContext);

            return;
        }

        /** @var array<string, mixed> $storedAction */
        $storedAction = is_array($pendingInteraction['action_payload'] ?? null)
            ? $pendingInteraction['action_payload']
            : [];
        $storedActionKey = is_string($storedAction['action'] ?? null) ? trim((string) $storedAction['action']) : 'help';

        $this->dispatchAction($storedAction, $messageContext, $storedActionKey);
        $this->chatStateService->forgetPendingInteraction($chatId);
    }

    /**
     * Handle one Telegram chat cleanup request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleReset(array $action, array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;
        $forceCleanup = ($action['force_cleanup'] ?? false) === true;
        $scope = is_string($action['scope'] ?? null) ? trim((string) $action['scope']) : 'session';

        if (is_string($chatId) || is_int($chatId)) {
            $this->chatStateService->forgetActiveTaskId($chatId);
            $this->chatStateService->forgetPendingInteraction($chatId);
        }

        $this->notificationService->resetChat($messageContext, $forceCleanup, $scope);
    }

    /**
     * Acknowledge unsupported media without leaving direct chat mode or showing the help menu.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleMediaMessage(array $messageContext): void
    {
        $this->notificationService->sendPlain(
            $messageContext,
            $this->textService->line('phrases.media_message_needs_caption')
        );
    }

    /**
     * Start direct chat mode for one Telegram chat.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleChatStart(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $session = $this->chatStateService->startChatSession($chatId);
        $this->notificationService->sendChatSessionStarted(
            $session,
            $messageContext,
            $this->machineQueryService->findLatestDetailed(),
            $this->resolveActiveTaskForChat($chatId)
        );
    }

    /**
     * Acknowledge one connectivity check without showing task history.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleChatPing(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $this->notificationService->sendChatPing(
            $this->chatStateService->getChatSession($chatId),
            $messageContext,
            $this->machineQueryService->findLatestDetailed()
        );
    }

    /**
     * Show the current direct chat-mode state for one Telegram chat.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleChatStatus(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $this->notificationService->sendChatSessionStatus(
            $this->chatStateService->getChatSession($chatId),
            $messageContext,
            $this->machineQueryService->findLatestDetailed(),
            $this->resolveActiveTaskForChat($chatId)
        );
    }

    /**
     * Stop direct chat mode for one Telegram chat.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleChatStop(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $this->chatStateService->forgetChatSession($chatId);
        $this->notificationService->sendChatSessionStopped($messageContext);
    }

    /**
     * Reset direct chat-mode context for one Telegram chat while keeping the mode enabled.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleChatReset(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $this->chatStateService->forgetPendingInteraction($chatId);
        $this->chatStateService->forgetActiveTaskId($chatId);
        $session = $this->chatStateService->resetChatSession($chatId);

        $this->notificationService->sendChatSessionReset(
            $session,
            $messageContext,
            $this->machineQueryService->findLatestDetailed()
        );
    }

    /**
     * Handle one onboarding or help request and include the latest task snapshot.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string|null  $error
     * @return void
     */
    protected function handleHelp(array $messageContext, ?string $error = null): void
    {
        $tasks = $this->resolveDashboardTasks();

        $this->notificationService->sendHelp(
            $messageContext,
            $error,
            $tasks,
            $this->machineQueryService->findLatestDetailed(),
            $this->resolveChatSession($messageContext)
        );
    }

    /**
     * Handle one Telegram menu-navigation request and render the relevant submenu.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleMenu(array $action, array $messageContext): void
    {
        $menuKey = is_string($action['menu_key'] ?? null) ? trim((string) $action['menu_key']) : 'root';
        $tasks = $this->resolveDashboardTasks();

        if ($menuKey === '' || strtolower($menuKey) === 'root') {
            $this->notificationService->sendHelp(
                $messageContext,
                null,
                $tasks,
                $this->machineQueryService->findLatestDetailed(),
                $this->resolveChatSession($messageContext)
            );

            return;
        }

        $this->notificationService->sendMenu($menuKey, $messageContext, $tasks);
    }

    /**
     * Resolve one compact task list for Telegram dashboard/help rendering.
     *
     * Dashboard should focus on active tasks and avoid noise from old terminal tasks.
     *
     * @return array<int, AutoCodingTask>
     */
    protected function resolveDashboardTasks(): array
    {
        $tasks = $this->taskQueryService->getLatest(12, null, null);

        $activeTasks = array_values(array_filter(
            $tasks,
            static fn (AutoCodingTask $task): bool => in_array($task->status->value, AutoCodingExecutionStatus::activeValues(), true)
        ));

        return array_slice($activeTasks, 0, 5);
    }

    /**
     * Handle one Telegram blocked-task resume request.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function handleResume(array $action, array $messageContext): void
    {
        $task = $this->resolveTaskOrNotify($action['task_reference'] ?? null, $messageContext);

        if (! $task instanceof AutoCodingTask) {
            return;
        }

        $this->rememberActiveTask($task, $messageContext);

        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];
        $inputContract = is_array($followUp['input_contract'] ?? null) ? $followUp['input_contract'] : [];
        $resumeToken = $inputContract['resume_token'] ?? null;

        if (! is_string($resumeToken) || trim($resumeToken) === '') {
            $this->notificationService->sendHelp(
                $messageContext,
                $this->textService->line('errors.blocked_task_missing_resume_token'),
                [],
                $this->machineQueryService->findLatestDetailed()
            );

            return;
        }

        $resumedTask = $this->taskDispatchService->resumeBlockedTask(
            $this->resolveTaskId($task),
            is_string($action['response'] ?? null) ? trim($action['response']) : '',
            trim($resumeToken),
            $this->buildResumeResponsePayload($task, $action),
        );

        $this->rememberActiveTask($resumedTask, $messageContext);

        $this->notificationService->sendResumeAccepted($resumedTask, $messageContext);
    }

    /**
     * Normalize one parsed Telegram update into a dispatchable action.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    protected function resolveParsedAction(array $update): array
    {
        /** @var array<string, mixed> $action */
        $action = $this->commandParser->parse($update);
        $actionKey = is_string($action['action'] ?? null) ? trim((string) $action['action']) : 'help';

        if ($actionKey !== 'conversation') {
            return $action;
        }

        $text = is_string($action['text'] ?? null) ? trim((string) $action['text']) : '';
        /** @var array<string, mixed> $messageContext */
        $messageContext = is_array($action['message_context'] ?? null) ? $action['message_context'] : [];

        $interactionAction = $this->resolvePendingInteractionFromText($text, $messageContext);

        if ($interactionAction !== null) {
            return $interactionAction;
        }

        $resolvedAction = $this->intentResolver->resolve($text, $messageContext);

        return $this->applyChatSessionContextToAction($resolvedAction, $messageContext);
    }

    /**
     * Dispatch one normalized Telegram action through the shared handler map.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @param  string  $actionKey
     * @return void
     */
    protected function dispatchAction(array $action, array $messageContext, string $actionKey): void
    {
        match ($actionKey) {
            'help' => $this->handleHelp($messageContext, is_string($action['error'] ?? null) ? $action['error'] : null),
            'chat_start' => $this->handleChatStart($messageContext),
            'chat_ping' => $this->handleChatPing($messageContext),
            'chat_status' => $this->handleChatStatus($messageContext),
            'chat_stop' => $this->handleChatStop($messageContext),
            'chat_reset' => $this->handleChatReset($messageContext),
            'media_message' => $this->handleMediaMessage($messageContext),
            'menu' => $this->handleMenu($action, $messageContext),
            'create_task' => $this->handleCreateTask($action, $messageContext),
            'status' => $this->handleStatus($action, $messageContext),
            'next_action' => $this->handleNextAction($action, $messageContext),
            'follow_up' => $this->handleFollowUp($action, $messageContext),
            'validation' => $this->handleValidation($action, $messageContext),
            'github_status' => $this->handleGithubStatus($action, $messageContext),
            'summary' => $this->handleSummary($action, $messageContext),
            'changes' => $this->handleChanges($action, $messageContext),
            'queue' => $this->handleQueue($action, $messageContext),
            'clarify_intent' => $this->handleClarifyIntent($action, $messageContext),
            'clarify_issue_context' => $this->handleClarifyIssueContext($action, $messageContext),
            'cancel_task' => $this->handleCancelTask($action, $messageContext),
            'cancel_tasks' => $this->handleCancelTasks($messageContext),
            'delete_task' => $this->handleDeleteTask($action, $messageContext),
            'delete_tasks' => $this->handleDeleteTasks($action, $messageContext),
            'confirm_pending' => $this->handleConfirmPending($action, $messageContext),
            'reset' => $this->handleReset($action, $messageContext),
            'resume' => $this->handleResume($action, $messageContext),
            default => $this->handleHelp($messageContext, is_string($action['error'] ?? null) ? $action['error'] : null),
        };
    }

    /**
     * Resolve one plain-text reply against the pending Telegram interaction remembered for the chat.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolvePendingInteractionFromText(string $text, array $messageContext): ?array
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if ((! is_string($chatId) && ! is_int($chatId)) || $text === '') {
            return null;
        }

        $pendingInteraction = $this->chatStateService->getPendingInteraction($chatId);

        if (! is_array($pendingInteraction)) {
            return null;
        }

        $type = is_string($pendingInteraction['type'] ?? null) ? trim((string) $pendingInteraction['type']) : '';
        $normalizedText = trim(strtolower($text));

        return match ($type) {
            'clarify_intent' => [
                'action' => 'clarify_intent',
                'intent' => $normalizedText,
                'message_context' => $messageContext,
            ],
            'clarify_issue_context' => [
                'action' => 'clarify_issue_context',
                'selection' => $normalizedText,
                'message_context' => $messageContext,
            ],
            'dangerous_action' => [
                'action' => 'confirm_pending',
                'decision' => $normalizedText,
                'message_context' => $messageContext,
            ],
            default => null,
        };
    }

    /**
     * Remember one dangerous Telegram action so it can be confirmed explicitly.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  array<string, mixed>  $actionPayload
     * @return void
     */
    protected function rememberPendingDangerousAction(array $messageContext, array $actionPayload): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $this->chatStateService->rememberPendingInteraction($chatId, [
            'type' => 'dangerous_action',
            'action_payload' => $actionPayload,
            'action_label' => $this->resolveDangerousActionLabel($actionPayload),
            'target_label' => $this->resolveDangerousActionTargetLabel($actionPayload),
        ]);
    }

    /**
     * Remember one issue-context clarification so Telegram can resume task creation safely.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  array<string, mixed>  $taskPayload
     * @param  array<string, mixed>  $clarification
     * @return void
     */
    protected function rememberPendingIssueContextClarification(
        array $messageContext,
        array $taskPayload,
        array $clarification,
    ): void {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $this->chatStateService->rememberPendingInteraction($chatId, [
            'type' => 'clarify_issue_context',
            'task_payload' => $taskPayload,
            'clarification' => $clarification,
        ]);
    }

    /**
     * Determine whether one dangerous action already carries an explicit confirmation.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string  $actionName
     * @return bool
     */
    protected function shouldRunConfirmedDangerousAction(array $messageContext, string $actionName): bool
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return false;
        }

        $pendingInteraction = $this->chatStateService->getPendingInteraction($chatId);

        return is_array($pendingInteraction)
            && ($pendingInteraction['type'] ?? null) === 'dangerous_action'
            && is_array($pendingInteraction['action_payload'] ?? null)
            && (($pendingInteraction['action_payload']['action'] ?? null) === $actionName)
            && (($pendingInteraction['action_payload']['confirmed'] ?? false) === true);
    }

    /**
     * Resolve one user-facing label for a pending dangerous Telegram action.
     *
     * @param  array<string, mixed>  $actionPayload
     * @return string
     */
    protected function resolveDangerousActionLabel(array $actionPayload): string
    {
        $actionName = is_string($actionPayload['action'] ?? null) ? trim((string) $actionPayload['action']) : '';

        return match ($actionName) {
            'cancel_task' => $this->textService->line('buttons.cancel_task'),
            'cancel_tasks' => $this->textService->line('buttons.cancel_all_active'),
            'delete_task' => $this->textService->line('buttons.delete_task'),
            'delete_tasks' => ($actionPayload['scope'] ?? null) === 'all'
                ? $this->textService->line('buttons.delete_all_tasks')
                : $this->textService->line('buttons.delete_all_pending'),
            default => $actionName,
        };
    }

    /**
     * Resolve one user-facing target label for a pending dangerous Telegram action.
     *
     * @param  array<string, mixed>  $actionPayload
     * @return string|null
     */
    protected function resolveDangerousActionTargetLabel(array $actionPayload): ?string
    {
        if (is_string($actionPayload['task_reference'] ?? null)) {
            $taskReference = trim((string) $actionPayload['task_reference']);

            return $taskReference !== '' ? $taskReference : null;
        }

        if (! is_string($actionPayload['scope'] ?? null)) {
            return null;
        }

        $scope = trim((string) $actionPayload['scope']);

        if ($scope === '') {
            return null;
        }

        return match ($scope) {
            'active' => $this->textService->line('values.active'),
            'all' => $this->textService->line('values.all'),
            'pending' => $this->textService->line('values.pending'),
            'terminal' => $this->textService->line('values.terminal'),
            default => $scope,
        };
    }

    /**
     * Forget the remembered active task for one chat when that task row no longer exists.
     *
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function forgetActiveTaskWhenMissing(array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return;
        }

        $activeTaskId = $this->chatStateService->getActiveTaskId($chatId);

        if ($activeTaskId === null) {
            return;
        }

        if ($this->taskQueryService->findDetailedById($activeTaskId) instanceof AutoCodingTask) {
            return;
        }

        $this->chatStateService->forgetActiveTaskId($chatId);
    }

    /**
     * Resolve one operator selection into a valid issue-context source task id.
     *
     * @param  string  $selection
     * @param  array<string, mixed>  $clarification
     * @return int|null
     */
    protected function resolveIssueContextSelection(string $selection, array $clarification): ?int
    {
        $normalizedSelection = ltrim(trim($selection), '#');

        if (! is_numeric($normalizedSelection)) {
            return null;
        }

        $selectedTaskId = (int) $normalizedSelection;
        $candidates = is_array($clarification['candidates'] ?? null) ? $clarification['candidates'] : [];

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! is_numeric($candidate['task_id'] ?? null)) {
                continue;
            }

            if ((int) $candidate['task_id'] === $selectedTaskId) {
                return $selectedTaskId;
            }
        }

        return null;
    }

    /**
     * Apply one selected issue-context source task id onto the pending task payload.
     *
     * @param  array<string, mixed>  $taskPayload
     * @param  int  $selectedSourceTaskId
     * @return array<string, mixed>
     */
    protected function applySelectedIssueContextSource(array $taskPayload, int $selectedSourceTaskId): array
    {
        $contextMetadata = is_array($taskPayload['context_metadata'] ?? null) ? $taskPayload['context_metadata'] : [];
        $issueEnrichment = is_array($contextMetadata['issue_enrichment'] ?? null)
            ? $contextMetadata['issue_enrichment']
            : [];
        $contextMetadata['issue_enrichment'] = array_merge($issueEnrichment, [
            'selected_source_task_id' => $selectedSourceTaskId,
        ]);
        $taskPayload['context_metadata'] = $contextMetadata;

        return $taskPayload;
    }

    /**
     * Resolve one task and dispatch the matching Telegram response for lookup-style actions.
     *
     * @param  string  $actionKey
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @param  string  $defaultTaskReference
     * @return void
     */
    protected function handleTaskLookupResult(
        string $actionKey,
        array $action,
        array $messageContext,
        string $defaultTaskReference,
    ): void {
        $task = $this->resolveTaskOrNotify($action['task_reference'] ?? $defaultTaskReference, $messageContext);

        if (! $task instanceof AutoCodingTask) {
            return;
        }

        $this->rememberActiveTask($task, $messageContext);

        match ($actionKey) {
            'status' => $this->notificationService->sendStatus($task, $messageContext),
            'next_action' => $this->notificationService->sendNextAction($task, $messageContext),
            'follow_up' => $this->notificationService->sendFollowUp($task, $messageContext),
            'validation' => $this->notificationService->sendValidation($task, $messageContext),
            'github_status' => $this->notificationService->sendGithubStatus(
                $task,
                $this->gitHubStatusQueryService->resolveForTask($task),
                $messageContext
            ),
            'summary' => $this->notificationService->sendSummary($task, $messageContext),
            'changes' => $this->notificationService->sendChanges($task, $messageContext),
            default => $this->notificationService->sendStatus($task, $messageContext),
        };
    }

    /**
     * Resolve one task reference and send a unified not-found message when missing.
     *
     * @param  mixed  $reference
     * @param  array<string, mixed>  $messageContext
     * @return AutoCodingTask|null
     */
    protected function resolveTaskOrNotify(mixed $reference, array $messageContext): ?AutoCodingTask
    {
        $task = $this->resolveTaskReference($reference);

        if ($task instanceof AutoCodingTask) {
            return $task;
        }

        $this->notificationService->sendHelp(
            $messageContext,
            $this->textService->line('errors.task_not_found'),
            [],
            $this->machineQueryService->findLatestDetailed(),
            $this->resolveChatSession($messageContext)
        );

        return null;
    }

    /**
     * Remember one task as the active Telegram conversation target for this chat.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $messageContext
     * @return void
     */
    protected function rememberActiveTask(AutoCodingTask $task, array $messageContext): void
    {
        $chatId = $messageContext['chat_id'] ?? null;
        $taskId = $this->resolveTaskId($task);

        if ((! is_string($chatId) && ! is_int($chatId)) || $taskId <= 0) {
            return;
        }

        $this->chatStateService->rememberActiveTaskId($chatId, $taskId);
    }

    /**
     * Resolve the current direct chat session for one Telegram chat context.
     *
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolveChatSession(array $messageContext): ?array
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return null;
        }

        return $this->chatStateService->getChatSession($chatId);
    }

    /**
     * Resolve the current active task remembered for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @return AutoCodingTask|null
     */
    protected function resolveActiveTaskForChat(int|string $chatId): ?AutoCodingTask
    {
        $taskId = $this->chatStateService->getActiveTaskId($chatId);

        return $taskId !== null
            ? $this->taskQueryService->findDetailedById($taskId)
            : null;
    }

    /**
     * Attach direct chat-session metadata onto one resolved Telegram action.
     *
     * @param  array<string, mixed>  $action
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function applyChatSessionContextToAction(array $action, array $messageContext): array
    {
        $session = $this->resolveChatSession($messageContext);

        if (! is_array($session) || ($session['enabled'] ?? false) !== true) {
            return $action;
        }

        $chatId = $messageContext['chat_id'] ?? null;

        if (is_string($chatId) || is_int($chatId)) {
            $this->chatStateService->touchChatSession($chatId);
        }

        if (($action['action'] ?? null) !== 'create_task') {
            return $action;
        }

        /** @var array<string, mixed> $taskPayload */
        $taskPayload = is_array($action['task_payload'] ?? null) ? $action['task_payload'] : [];
        $action['task_payload'] = $this->attachChatSessionContextToTaskPayload($taskPayload, $messageContext, $session);

        return $action;
    }

    /**
     * Attach active Telegram chat-session metadata to one task payload.
     *
     * @param  array<string, mixed>  $taskPayload
     * @param  array<string, mixed>  $messageContext
     * @param  array<string, mixed>|null  $session
     * @return array<string, mixed>
     */
    protected function attachChatSessionContextToTaskPayload(
        array $taskPayload,
        array $messageContext,
        ?array $session = null,
    ): array {
        $resolvedSession = is_array($session) ? $session : $this->resolveChatSession($messageContext);

        if (! is_array($resolvedSession) || ($resolvedSession['enabled'] ?? false) !== true) {
            return $taskPayload;
        }

        $contextMetadata = is_array($taskPayload['context_metadata'] ?? null) ? $taskPayload['context_metadata'] : [];
        $transportContext = is_array($contextMetadata['transport_context'] ?? null) ? $contextMetadata['transport_context'] : [];
        $transportContext['command'] = 'chat_session';
        $transportContext['chat_session'] = [
            'mode' => is_string($resolvedSession['mode'] ?? null) ? trim((string) $resolvedSession['mode']) : 'codex_chat',
            'session_id' => is_string($resolvedSession['session_id'] ?? null) ? trim((string) $resolvedSession['session_id']) : '',
            'started_at' => is_string($resolvedSession['started_at'] ?? null) ? trim((string) $resolvedSession['started_at']) : '',
            'last_message_at' => is_string($resolvedSession['last_message_at'] ?? null) ? trim((string) $resolvedSession['last_message_at']) : '',
        ];
        $contextMetadata['transport_context'] = $transportContext;
        $taskPayload['context_metadata'] = $contextMetadata;

        return $taskPayload;
    }

    /**
     * Resolve one task reference into the detailed task model used by Telegram commands.
     *
     * @param  mixed  $reference
     * @return AutoCodingTask|null
     */
    protected function resolveTaskReference(mixed $reference): ?AutoCodingTask
    {
        if (is_string($reference) && trim(strtolower($reference)) === 'latest') {
            return $this->taskQueryService->findLatestDetailed();
        }

        if (is_string($reference) && str_starts_with(trim(strtolower($reference)), 'latest:')) {
            $status = trim(substr(trim(strtolower($reference)), strlen('latest:')));

            if (in_array($status, AutoCodingExecutionStatus::allValues(), true)) {
                return $this->taskQueryService->findLatestDetailedByStatus($status);
            }
        }

        if (is_string($reference) && is_numeric(trim($reference))) {
            return $this->taskQueryService->findDetailedById((int) trim($reference));
        }

        if (is_string($reference) && str_starts_with(trim(strtolower($reference)), 'issue:')) {
            $issueKey = strtoupper(trim(substr(trim((string) $reference), strlen('issue:'))));

            return $issueKey !== ''
                ? $this->taskQueryService->findLatestDetailedByIssueKey($issueKey)
                : null;
        }

        if (is_string($reference) && str_starts_with(trim(strtolower($reference)), 'branch:')) {
            $branchName = trim(substr(trim((string) $reference), strlen('branch:')));

            return $branchName !== ''
                ? $this->taskQueryService->findLatestDetailedByBranchName($branchName)
                : null;
        }

        if (is_string($reference) && str_starts_with(trim(strtolower($reference)), 'pr:')) {
            $pullRequestNumber = trim(substr(trim((string) $reference), strlen('pr:')));

            return is_numeric($pullRequestNumber)
                ? $this->taskQueryService->findLatestDetailedByPullRequestNumber((int) $pullRequestNumber)
                : null;
        }

        if (is_int($reference)) {
            return $this->taskQueryService->findDetailedById($reference);
        }

        return null;
    }

    /**
     * Resolve the first validation failure message into a Telegram-safe summary line.
     *
     * @param  ValidationException  $exception
     * @return string
     */
    protected function resolveValidationMessage(ValidationException $exception): string
    {
        foreach ($exception->errors() as $messages) {
            if (! is_array($messages)) {
                continue;
            }

            foreach ($messages as $message) {
                if (is_string($message) && trim($message) !== '') {
                    return trim($message);
                }
            }
        }

        return $this->textService->line('errors.command_failed');
    }

    /**
     * Resolve one short callback acknowledgement for Telegram button actions.
     *
     * @param  array<string, mixed>  $action
     * @return string|null
     */
    protected function resolveCallbackAcknowledgement(array $action): ?string
    {
        $actionKey = is_string($action['action'] ?? null) ? trim((string) $action['action']) : 'help';

        return match ($actionKey) {
            'chat_start' => $this->textService->line('callbacks.chat_start'),
            'chat_status' => $this->textService->line('callbacks.chat_status'),
            'chat_stop' => $this->textService->line('callbacks.chat_stop'),
            'chat_reset' => $this->textService->line('callbacks.chat_reset'),
            'menu' => $this->textService->line('callbacks.menu'),
            'create_task' => $this->textService->line('callbacks.create_task'),
            'status' => $this->textService->line('callbacks.status'),
            'next_action' => $this->textService->line('callbacks.next_action'),
            'follow_up' => $this->textService->line('callbacks.follow_up'),
            'validation' => $this->textService->line('callbacks.validation'),
            'github_status' => $this->textService->line('callbacks.github_status'),
            'summary' => $this->textService->line('callbacks.summary'),
            'changes' => $this->textService->line('callbacks.changes'),
            'queue' => $this->textService->line('callbacks.queue'),
            'cancel_task' => $this->textService->line('callbacks.cancel_task'),
            'cancel_tasks' => $this->textService->line('callbacks.cancel_tasks'),
            'delete_task' => $this->textService->line('callbacks.delete_task'),
            'delete_tasks' => $this->textService->line('callbacks.delete_tasks'),
            'reset' => $this->textService->line('callbacks.reset'),
            'resume' => $this->textService->line('callbacks.resume'),
            default => $this->textService->line('callbacks.default'),
        };
    }

    /**
     * Normalize one parsed Telegram message context into a stable array shape.
     *
     * @param  mixed  $messageContext
     * @return array<string, mixed>
     */
    protected function normalizeMessageContext(mixed $messageContext): array
    {
        if (! is_array($messageContext)) {
            return [];
        }

        return [
            'chat_id' => $messageContext['chat_id'] ?? null,
            'chat_type' => is_string($messageContext['chat_type'] ?? null) ? $messageContext['chat_type'] : null,
            'user_id' => $messageContext['user_id'] ?? null,
            'username' => is_string($messageContext['username'] ?? null) ? $messageContext['username'] : null,
            'message_id' => is_int($messageContext['message_id'] ?? null) ? $messageContext['message_id'] : null,
            'message_thread_id' => is_int($messageContext['message_thread_id'] ?? null) ? $messageContext['message_thread_id'] : null,
            'callback_query_id' => is_string($messageContext['callback_query_id'] ?? null)
                ? $messageContext['callback_query_id']
                : null,
        ];
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

    /**
     * Build one structured resume payload from a Telegram callback action when question contracts are available.
     *
     * @param  AutoCodingTask  $task
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>|null
     */
    protected function buildResumeResponsePayload(AutoCodingTask $task, array $action): ?array
    {
        $questionReference = is_string($action['question_reference'] ?? null)
            ? trim($action['question_reference'])
            : '';
        $response = is_string($action['response'] ?? null) ? trim($action['response']) : '';

        $structuredTextPayload = $this->buildStructuredResumePayloadFromText($task, $response);

        if ($structuredTextPayload !== null) {
            return $structuredTextPayload;
        }

        if ($questionReference === '' || $response === '') {
            return null;
        }

        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];
        $questionContracts = is_array($followUp['question_contracts'] ?? null)
            ? array_values(array_filter($followUp['question_contracts'], 'is_array'))
            : [];

        if (! ctype_digit($questionReference)) {
            return null;
        }

        $questionIndex = (int) $questionReference;
        $questionContract = $questionContracts[$questionIndex] ?? null;

        if (! is_array($questionContract)) {
            return null;
        }

        $questionId = is_string($questionContract['id'] ?? null) ? trim($questionContract['id']) : '';
        $inputType = is_string($questionContract['input_type'] ?? null)
            ? trim($questionContract['input_type'])
            : 'text';

        if ($questionId === '') {
            return null;
        }

        return [
            'type' => 'question_answer_list',
            'value' => $response,
            'answers' => [
                [
                    'question_id' => $questionId,
                    'type' => $inputType,
                    'value' => $response,
                    'metadata' => [
                        'source' => 'telegram_callback',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build one structured resume payload from a Telegram text response containing question-id pairs.
     *
     * Supported format:
     * `question_id=value; question_two=another value`
     *
     * @param  AutoCodingTask  $task
     * @param  string  $response
     * @return array<string, mixed>|null
     */
    protected function buildStructuredResumePayloadFromText(AutoCodingTask $task, string $response): ?array
    {
        if ($response === '' || ! str_contains($response, '=')) {
            return null;
        }

        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];
        $questionContracts = is_array($followUp['question_contracts'] ?? null)
            ? array_values(array_filter($followUp['question_contracts'], 'is_array'))
            : [];

        if ($questionContracts === []) {
            return null;
        }

        $contractsById = [];

        foreach ($questionContracts as $questionContract) {
            $questionId = is_string($questionContract['id'] ?? null)
                ? trim($questionContract['id'])
                : '';

            if ($questionId === '') {
                continue;
            }

            $contractsById[$questionId] = $questionContract;
        }

        if ($contractsById === []) {
            return null;
        }

        $answers = [];
        $segments = preg_split('/[;\n]+/', $response) ?: [];

        foreach ($segments as $segment) {
            $normalizedSegment = trim((string) $segment);

            if ($normalizedSegment === '' || ! str_contains($normalizedSegment, '=')) {
                continue;
            }

            $pair = array_pad(explode('=', $normalizedSegment, 2), 2, '');
            $rawQuestionId = is_string($pair[0] ?? null) ? $pair[0] : '';
            $rawValue = is_string($pair[1] ?? null) ? $pair[1] : '';
            $questionId = trim($rawQuestionId);
            $value = trim($rawValue);

            if ($questionId === '' || $value === '' || ! array_key_exists($questionId, $contractsById)) {
                continue;
            }

            $questionContract = $contractsById[$questionId];
            $inputType = is_string($questionContract['input_type'] ?? null)
                ? trim($questionContract['input_type'])
                : 'text';

            $answers[] = [
                'question_id' => $questionId,
                'type' => $inputType,
                'value' => $value,
                'metadata' => [
                    'source' => 'telegram_text',
                ],
            ];
        }

        if ($answers === []) {
            return null;
        }

        return [
            'type' => 'question_answer_list',
            'value' => $response,
            'answers' => $answers,
        ];
    }
}
