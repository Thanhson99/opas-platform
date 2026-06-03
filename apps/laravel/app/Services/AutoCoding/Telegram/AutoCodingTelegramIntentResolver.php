<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingTaskQueryService;
use Illuminate\Support\Str;

class AutoCodingTelegramIntentResolver
{
    public function __construct(
        private readonly AutoCodingTelegramChatStateService $chatStateService,
        private readonly AutoCodingTaskQueryService $taskQueryService,
    ) {}

    /**
     * Resolve one free-form Telegram text message into a structured action.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    public function resolve(string $text, array $messageContext): array
    {
        $normalizedText = trim($text);

        if ($normalizedText === '') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
            ];
        }

        $chatSessionAction = $this->resolveChatSessionIntent($normalizedText, $messageContext);

        if ($chatSessionAction !== null) {
            return $chatSessionAction;
        }

        if ($this->shouldResolveAsDirectCodexChat($normalizedText, $messageContext)) {
            return $this->buildDirectCodexChatAction($normalizedText, $messageContext);
        }

        $blockedTask = $this->resolveBlockedTaskForChat($messageContext);

        if ($blockedTask instanceof AutoCodingTask && ! $this->looksLikeStandaloneTaskLookup($normalizedText)) {
            $taskKey = $blockedTask->getKey();

            return [
                'action' => 'resume',
                'task_reference' => is_scalar($taskKey) ? trim((string) $taskKey) : '',
                'response' => $normalizedText,
                'message_context' => $messageContext,
            ];
        }

        $issueKey = $this->extractIssueKey($normalizedText);
        $summaryWithoutIssue = $this->stripIssueKey($normalizedText, $issueKey);

        $lookupAction = $this->resolveLookupIntent($normalizedText, $messageContext);

        if ($lookupAction !== null) {
            return $lookupAction;
        }

        $taskAction = $this->resolveExplicitTaskIntent($normalizedText, $issueKey, $messageContext);

        if ($taskAction !== null) {
            return $taskAction;
        }

        if ($this->looksAmbiguousRequest($normalizedText)) {
            return [
                'action' => 'clarify_intent',
                'original_text' => $normalizedText,
                'message_context' => $messageContext,
            ];
        }

        if ($issueKey !== null && strcasecmp($normalizedText, $issueKey) === 0) {
            return $this->buildCreateTaskAction(
                'code',
                sprintf('Review GitHub issue %s and implement the requested changes.', $issueKey),
                $issueKey,
                $messageContext
            );
        }

        return $this->buildCreateTaskAction(
            'code',
            $summaryWithoutIssue !== '' ? $summaryWithoutIssue : $normalizedText,
            $issueKey,
            $messageContext
        );
    }

    /**
     * Resolve one explicit clarification choice into a structured Telegram action.
     *
     * @param  string  $intent
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    public function resolveClarifiedIntent(string $intent, string $text, array $messageContext): array
    {
        $normalizedIntent = trim(strtolower($intent));
        $normalizedText = trim($text);
        $issueKey = $this->extractIssueKey($normalizedText);
        $summaryWithoutIssue = $this->stripIssueKey($normalizedText, $issueKey);

        return match ($normalizedIntent) {
            'review' => $this->buildCreateTaskAction('review', $summaryWithoutIssue, $issueKey, $messageContext),
            'validate' => $this->buildCreateTaskAction('validate', $summaryWithoutIssue, $issueKey, $messageContext),
            'status' => [
                'action' => 'status',
                'task_reference' => 'latest',
                'message_context' => $messageContext,
            ],
            'summary' => [
                'action' => 'summary',
                'task_reference' => 'latest',
                'message_context' => $messageContext,
            ],
            'changes' => [
                'action' => 'changes',
                'task_reference' => 'latest',
                'message_context' => $messageContext,
            ],
            'github' => [
                'action' => 'github_status',
                'task_reference' => 'latest',
                'message_context' => $messageContext,
            ],
            'queue' => [
                'action' => 'queue',
                'message_context' => $messageContext,
            ],
            default => $this->buildCreateTaskAction(
                'code',
                $summaryWithoutIssue !== '' ? $summaryWithoutIssue : $normalizedText,
                $issueKey,
                $messageContext
            ),
        };
    }

    /**
     * Resolve the blocked task currently associated with one Telegram chat.
     *
     * @param  array<string, mixed>  $messageContext
     * @return AutoCodingTask|null
     */
    protected function resolveBlockedTaskForChat(array $messageContext): ?AutoCodingTask
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return null;
        }

        $activeTaskId = $this->chatStateService->getActiveTaskId($chatId);

        if ($activeTaskId !== null) {
            $activeTask = $this->taskQueryService->findDetailedById($activeTaskId);

            if ($activeTask instanceof AutoCodingTask
                && $activeTask->status->value === 'blocked'
                && $this->taskBelongsToChat($activeTask, $chatId)
            ) {
                return $activeTask;
            }
        }

        $tasks = $this->taskQueryService->getLatest(20, 'blocked', null);

        foreach ($tasks as $task) {
            if ($this->taskBelongsToChat($task, $chatId)) {
                return $task;
            }
        }

        return null;
    }

    /**
     * Determine whether one task belongs to the current Telegram chat.
     *
     * @param  AutoCodingTask  $task
     * @param  int|string  $chatId
     * @return bool
     */
    protected function taskBelongsToChat(AutoCodingTask $task, int|string $chatId): bool
    {
        $contextPayload = is_array($task->context_payload) ? $task->context_payload : [];
        $transportContext = is_array($contextPayload['transport_context'] ?? null)
            ? $contextPayload['transport_context']
            : [];
        $telegramContext = is_array($transportContext['telegram'] ?? null)
            ? $transportContext['telegram']
            : [];
        $taskChatId = $telegramContext['chat_id'] ?? null;

        return is_string($taskChatId) || is_int($taskChatId)
            ? trim((string) $taskChatId) === trim((string) $chatId)
            : false;
    }

    /**
     * Build one create-task action from conversational Telegram input.
     *
     * @param  string  $command
     * @param  string  $summary
     * @param  string|null  $issueKey
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildCreateTaskAction(
        string $command,
        string $summary,
        ?string $issueKey,
        array $messageContext,
    ): array {
        $normalizedSummary = trim($summary);

        if ($normalizedSummary === '') {
            $normalizedSummary = $this->resolveDefaultTaskSummary($command, $issueKey);
        }

        return [
            'action' => 'create_task',
            'message_context' => $messageContext,
            'task_payload' => [
                'summary' => $normalizedSummary,
                'issue_key' => $issueKey,
                'validate' => $command !== 'review',
                'context_metadata' => [
                    'transport_context' => [
                        'source' => 'telegram',
                        'command' => 'conversation',
                        'intent' => $command,
                        'telegram' => [
                            'chat_id' => $messageContext['chat_id'] ?? null,
                            'user_id' => $messageContext['user_id'] ?? null,
                            'username' => $messageContext['username'] ?? null,
                            'chat_type' => $messageContext['chat_type'] ?? null,
                            'message_thread_id' => $messageContext['message_thread_id'] ?? null,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build one direct Codex chat reply action backed by the connected machine.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildDirectCodexChatAction(string $text, array $messageContext): array
    {
        $action = $this->buildCreateTaskAction('codex_chat_reply', $text, null, $messageContext);
        /** @var array<string, mixed> $taskPayload */
        $taskPayload = is_array($action['task_payload'] ?? null) ? $action['task_payload'] : [];
        $taskPayload['validate'] = false;
        $taskPayload['dirty_workspace_policy'] = 'allow';
        $taskPayload['provider_options'] = array_merge(
            is_array($taskPayload['provider_options'] ?? null) ? $taskPayload['provider_options'] : [],
            [
                'mode' => 'telegram_direct_chat',
                'response_style' => 'direct_answer',
            ]
        );
        $action['task_payload'] = $taskPayload;

        return $action;
    }

    /**
     * Determine whether active chat-session text should be answered conversationally.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return bool
     */
    protected function shouldResolveAsDirectCodexChat(string $text, array $messageContext): bool
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return false;
        }

        if (! $this->chatStateService->hasActiveChatSession($chatId)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether a free-form message should stay a standalone lookup request.
     *
     * @param  string  $text
     * @return bool
     */
    protected function looksLikeStandaloneTaskLookup(string $text): bool
    {
        return $this->resolveLookupIntent($text, []) !== null
            || $this->resolveExplicitTaskIntent($text, $this->extractIssueKey($text), []) !== null;
    }

    /**
     * Determine whether one conversational message is too ambiguous to execute directly.
     *
     * @param  string  $text
     * @return bool
     */
    protected function looksAmbiguousRequest(string $text): bool
    {
        $normalizedText = mb_strtolower(trim($text));

        return in_array($normalizedText, [
            'lam tiep',
            'làm tiếp',
            'tiep tuc',
            'tiếp tục',
            'check giup toi',
            'check giúp tôi',
            'check dum toi',
            'sua cai nay',
            'sửa cái này',
            'fix cai nay',
            'fix cái này',
            'xem giup toi',
            'xem giúp tôi',
            'lam cai nay',
            'làm cái này',
            'do it',
            'continue',
            'check this',
            'fix this',
        ], true);
    }

    /**
     * Determine whether one message starts with any supported intent prefix.
     *
     * @param  string  $text
     * @param  array<int, string>  $keywords
     * @return bool
     */
    protected function startsWithIntent(string $text, array $keywords): bool
    {
        return $this->resolveMatchedPhrase($text, $keywords) !== null;
    }

    /**
     * Remove one leading intent keyword from a conversational message.
     *
     * @param  string  $text
     * @param  string  $keyword
     * @return string
     */
    protected function stripLeadingIntentKeyword(string $text, string $keyword): string
    {
        if ($keyword === '') {
            return trim($text);
        }

        $pattern = sprintf('/^%s\b\s*/iu', preg_quote($keyword, '/'));

        return trim((string) preg_replace($pattern, '', $text));
    }

    /**
     * Resolve one latest-task lookup action from conversational Telegram text.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolveLookupIntent(string $text, array $messageContext): ?array
    {
        $defaultTaskReference = $this->resolveDefaultLookupReference($messageContext);

        return match (true) {
            $this->startsWithIntent($text, ['status', 'trang thai', 'check status', 'xem status']) => [
                'action' => 'status',
                'task_reference' => $this->resolveLookupTaskReference(
                    $text,
                    ['status', 'trang thai', 'check status', 'xem status'],
                    $defaultTaskReference
                ),
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['summary', 'tom tat', 'check summary', 'xem summary', 'xem tom tat']) => [
                'action' => 'summary',
                'task_reference' => $this->resolveLookupTaskReference(
                    $text,
                    ['summary', 'tom tat', 'check summary', 'xem summary', 'xem tom tat'],
                    $defaultTaskReference
                ),
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['changes', 'change', 'thay doi', 'check changes', 'xem changes', 'xem thay doi']) => [
                'action' => 'changes',
                'task_reference' => $this->resolveLookupTaskReference(
                    $text,
                    ['changes', 'change', 'thay doi', 'check changes', 'xem changes', 'xem thay doi'],
                    $defaultTaskReference
                ),
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['github', 'check github', 'xem github', 'pr status', 'check pr', 'xem pr', 'ci status', 'check ci', 'xem ci']) => [
                'action' => 'github_status',
                'task_reference' => $this->resolveGithubLookupTaskReference(
                    $text,
                    ['github', 'check github', 'xem github', 'pr status', 'check pr', 'xem pr', 'ci status', 'check ci', 'xem ci'],
                    $defaultTaskReference
                ),
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['queue', 'hang cho', 'check queue', 'xem queue', 'xem hang cho']) => [
                'action' => 'queue',
                'status_filter' => $this->resolveQueueStatusFilter(
                    $this->stripLeadingIntentPhrase($text, ['queue', 'hang cho', 'check queue', 'xem queue', 'xem hang cho'])
                ),
                'message_context' => $messageContext,
            ],
            default => null,
        };
    }

    /**
     * Resolve one plain-text direct chat-session control request.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolveChatSessionIntent(string $text, array $messageContext): ?array
    {
        $activeTaskReference = $this->resolveLinkedActiveTaskReference($messageContext);

        $controlAction = $this->resolveChatSessionControlIntent($text, $messageContext);

        if ($controlAction !== null) {
            return $controlAction;
        }

        return $this->resolveChatSessionReportIntent($text, $messageContext, $activeTaskReference);
    }

    /**
     * Resolve one plain-text chat-session control request such as start/stop/reset.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolveChatSessionControlIntent(string $text, array $messageContext): ?array
    {
        if ($this->looksLikeChatConnectivityCheck($text)) {
            return [
                'action' => 'chat_ping',
                'message_context' => $messageContext,
            ];
        }

        return match (true) {
            $this->startsWithIntent($text, ['chat status', 'status chat', 'xem chat', 'trang thai chat']) => [
                'action' => 'chat_status',
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['chat start', 'start chat', 'bat chat', 'bật chat']) => [
                'action' => 'chat_start',
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['chat stop', 'stop chat', 'tat chat', 'tắt chat']) => [
                'action' => 'chat_stop',
                'message_context' => $messageContext,
            ],
            $this->startsWithIntent($text, ['chat reset', 'reset chat mode', 'lam moi chat mode', 'làm mới chat mode']) => [
                'action' => 'chat_reset',
                'message_context' => $messageContext,
            ],
            default => null,
        };
    }

    /**
     * Detect simple connectivity checks that should not become coding tasks.
     *
     * @param  string  $text
     * @return bool
     */
    protected function looksLikeChatConnectivityCheck(string $text): bool
    {
        $normalizedText = $this->normalizeTextForMatching($text);

        if (in_array($normalizedText, ['test', 'ping', 'hello', 'hi'], true)) {
            return true;
        }

        foreach ([
            'co nhan duoc tin nhan',
            'co nhan duoc tin',
            'ban co nhan duoc',
            'nhan duoc tin nhan khong',
            'nhan duoc tin khong',
            'telegram co hoat dong khong',
            'bot co hoat dong khong',
            'are you receiving',
            'did you receive',
            'can you receive',
            'telegram working',
            'bot working',
        ] as $needle) {
            if (str_contains($normalizedText, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize free-form Telegram text for broad natural-language matching.
     *
     * @param  string  $text
     * @return string
     */
    protected function normalizeTextForMatching(string $text): string
    {
        return trim((string) preg_replace(
            '/\s+/',
            ' ',
            Str::ascii(mb_strtolower($text))
        ));
    }

    /**
     * Resolve one plain-text report-style request while a direct chat session is active.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @param  string|null  $activeTaskReference
     * @return array<string, mixed>|null
     */
    protected function resolveChatSessionReportIntent(
        string $text,
        array $messageContext,
        ?string $activeTaskReference,
    ): ?array {
        $interruptAction = $this->resolveCurrentWorkInterruptIntent($text, $messageContext);

        if ($interruptAction !== null) {
            return $interruptAction;
        }

        return match (true) {
            $this->startsWithIntent($text, ['what are you doing', 'current progress', 'dang lam gi', 'đang làm gì', 'ban dang lam gi', 'bạn đang làm gì']) => $this->buildChatSessionLookupAction(
                'status',
                $messageContext,
                $activeTaskReference
            ),
            $this->startsWithIntent($text, ['what changed', 'what files changed', 'da doi gi', 'đã đổi gì', 'da sua gi roi', 'đã sửa gì rồi', 'ban da doi gi', 'bạn đã đổi gì', 'ban da sua gi roi', 'bạn đã sửa gì rồi']) => $this->buildChatSessionLookupAction(
                'changes',
                $messageContext,
                $activeTaskReference
            ),
            $this->startsWithIntent($text, ['what did you do', 'what have you done so far', 'da lam gi', 'đã làm gì', 'toi gio da lam gi', 'tới giờ đã làm gì', 'ban da lam gi', 'bạn đã làm gì']) => $this->buildChatSessionLookupAction(
                'summary',
                $messageContext,
                $activeTaskReference
            ),
            $this->startsWithIntent($text, ['what next', 'next step', 'what should i do next', 'what remains', 'what is left', 'what\'s left', 'buoc tiep theo la gi', 'bước tiếp theo là gì', 'tiep theo lam gi', 'tiếp theo làm gì', 'con gi chua xong', 'còn gì chưa xong', 'con gi chua lam', 'còn gì chưa làm']) => $this->buildChatSessionLookupAction(
                'next_action',
                $messageContext,
                $activeTaskReference
            ),
            $this->startsWithIntent($text, ['need anything from me', 'do you need anything from me', 'can i help with anything', 'co can toi tra loi gi khong', 'có cần tôi trả lời gì không', 'co can toi phan hoi gi khong', 'có cần tôi phản hồi gì không']) => $this->buildChatSessionLookupAction(
                'follow_up',
                $messageContext,
                $activeTaskReference ?? 'latest:blocked'
            ),
            $this->startsWithIntent($text, ['what is blocking you', 'are you blocked', 'what are you waiting for', 'bi vuong gi', 'bị vướng gì', 'dang ket gi', 'đang kẹt gì', 'dang cho gi', 'đang chờ gì']) => $this->buildChatSessionLookupAction(
                'follow_up',
                $messageContext,
                $activeTaskReference ?? 'latest:blocked'
            ),
            $activeTaskReference !== null
                && $this->startsWithIntent($text, ['continue working', 'keep going', 'lam tiep di', 'làm tiếp đi', 'tiep tuc di', 'tiếp tục đi']) => $this->buildChatSessionLookupAction(
                    'status',
                    $messageContext,
                    $activeTaskReference
                ),
            default => null,
        };
    }

    /**
     * Build one lookup-style action scoped to the active direct chat session when possible.
     *
     * @param  string  $action
     * @param  array<string, mixed>  $messageContext
     * @param  string|null  $taskReference
     * @return array<string, mixed>
     */
    protected function buildChatSessionLookupAction(
        string $action,
        array $messageContext,
        ?string $taskReference = null,
    ): array {
        return [
            'action' => $action,
            'task_reference' => $taskReference ?? 'latest',
            'message_context' => $messageContext,
        ];
    }

    /**
     * Resolve one natural-language interrupt request for the current chat task.
     *
     * Keep these requests on the existing cancel-confirmation workflow so chat
     * mode can stop safely without introducing a second interruption contract.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolveCurrentWorkInterruptIntent(string $text, array $messageContext): ?array
    {
        if (! $this->startsWithIntent($text, [
            'stop current work',
            'cancel current task',
            'pause current work',
            'pause for now',
            'hold on',
            'stop for now',
            'tam dung',
            'tạm dừng',
            'tam dung giup toi',
            'tạm dừng giúp tôi',
            'dung lai',
            'dừng lại',
            'dung task nay',
            'dừng task này',
            'huy task nay',
            'hủy task này',
        ])) {
            return null;
        }

        return [
            'action' => 'cancel_task',
            'task_reference' => $this->resolveDefaultLookupReference($messageContext, 'latest:running'),
            'message_context' => $messageContext,
        ];
    }

    /**
     * Resolve one explicit task-creation intent from conversational Telegram text.
     *
     * @param  string  $text
     * @param  string|null  $issueKey
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>|null
     */
    protected function resolveExplicitTaskIntent(string $text, ?string $issueKey, array $messageContext): ?array
    {
        $interruptAction = $this->resolveCurrentWorkInterruptIntent($text, $messageContext);

        return match (true) {
            $interruptAction !== null => $interruptAction,
            $this->startsWithIntent($text, ['review']) => $this->buildPrefixedTaskAction(
                'review',
                $text,
                ['review'],
                $issueKey,
                $messageContext
            ),
            $this->startsWithIntent($text, ['validate', 'validation', 'check', 'test', 'lint']) => $this->buildPrefixedTaskAction(
                'validate',
                $text,
                ['validate', 'validation', 'check', 'test', 'lint'],
                $issueKey,
                $messageContext
            ),
            $this->startsWithIntent($text, ['code']) => $this->buildPrefixedTaskAction(
                'code',
                $text,
                ['code'],
                $issueKey,
                $messageContext
            ),
            $this->startsWithIntent($text, ['fix', 'implement', 'sua', 'sửa']) => $this->buildPrefixedTaskAction(
                'code',
                $text,
                ['fix', 'implement', 'sua', 'sửa'],
                $issueKey,
                $messageContext
            ),
            $issueKey !== null && $this->startsWithIntent($text, ['issue', 'github issue', 'ticket']) => $this->buildIssueTaskAction(
                $text,
                $issueKey,
                $messageContext
            ),
            default => null,
        };
    }

    /**
     * Build one prefixed conversational task action.
     *
     * @param  string  $command
     * @param  string  $text
     * @param  array<int, string>  $prefixes
     * @param  string|null  $issueKey
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildPrefixedTaskAction(
        string $command,
        string $text,
        array $prefixes,
        ?string $issueKey,
        array $messageContext,
    ): array {
        $summary = $this->stripTaskScaffolding(
            $this->stripLeadingIntentPhrase($text, $prefixes),
            $issueKey
        );

        return $this->buildCreateTaskAction($command, $summary, $issueKey, $messageContext);
    }

    /**
     * Build one issue-based conversational coding task.
     *
     * @param  string  $text
     * @param  string  $issueKey
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildIssueTaskAction(string $text, string $issueKey, array $messageContext): array
    {
        $summary = $this->stripTaskScaffolding(
            $this->stripLeadingIntentPhrase($text, ['github issue', 'issue', 'ticket']),
            $issueKey
        );

        return $this->buildCreateTaskAction('code', $summary, $issueKey, $messageContext);
    }

    /**
     * Resolve one default summary for conversational task creation.
     *
     * @param  string  $command
     * @param  string|null  $issueKey
     * @return string
     */
    protected function resolveDefaultTaskSummary(string $command, ?string $issueKey): string
    {
        if ($issueKey !== null) {
            return match ($command) {
                'review' => sprintf('Review GitHub issue %s and assess the requested changes.', $issueKey),
                'validate' => sprintf('Validate the current work for GitHub issue %s.', $issueKey),
                default => sprintf('Review GitHub issue %s and implement the requested changes.', $issueKey),
            };
        }

        return match ($command) {
            'review' => 'Review the latest repository changes.',
            'validate' => 'Validate the current repository state.',
            default => 'Investigate and implement the requested coding change.',
        };
    }

    /**
     * Resolve the first matched conversational phrase from one candidate list.
     *
     * @param  string  $text
     * @param  array<int, string>  $phrases
     * @return string|null
     */
    protected function resolveMatchedPhrase(string $text, array $phrases): ?string
    {
        $normalizedText = mb_strtolower(trim($text));
        $normalizedPhrases = array_values(array_filter(array_map(
            static fn (string $phrase): string => mb_strtolower(trim($phrase)),
            $phrases
        ), static fn (string $phrase): bool => $phrase !== ''));

        usort($normalizedPhrases, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($normalizedPhrases as $phrase) {
            if ($normalizedText === $phrase || str_starts_with($normalizedText, $phrase.' ')) {
                return $phrase;
            }
        }

        return null;
    }

    /**
     * Remove the first supported leading phrase from one conversational message.
     *
     * @param  string  $text
     * @param  array<int, string>  $phrases
     * @return string
     */
    protected function stripLeadingIntentPhrase(string $text, array $phrases): string
    {
        $matchedPhrase = $this->resolveMatchedPhrase($text, $phrases);

        return $matchedPhrase !== null
            ? $this->stripLeadingIntentKeyword($text, $matchedPhrase)
            : trim($text);
    }

    /**
     * Remove conversational scaffolding from one task summary.
     *
     * @param  string  $text
     * @param  string|null  $issueKey
     * @return string
     */
    protected function stripTaskScaffolding(string $text, ?string $issueKey): string
    {
        $summary = $this->stripIssueKey($text, $issueKey);
        $summary = trim((string) preg_replace('/^(github\s+)?(issue|ticket)\b[:\s-]*/iu', '', $summary));

        return trim($summary);
    }

    /**
     * Resolve one conversational lookup target into a Telegram task reference.
     *
     * @param  string  $text
     * @param  array<int, string>  $prefixes
     * @param  string  $defaultReference
     * @return string
     */
    protected function resolveLookupTaskReference(string $text, array $prefixes, string $defaultReference = 'latest'): string
    {
        $referenceText = $this->stripLeadingIntentPhrase($text, $prefixes);
        $normalizedReference = mb_strtolower(trim($referenceText));

        if ($normalizedReference === '') {
            return $defaultReference;
        }

        if (is_numeric($normalizedReference)) {
            return trim($normalizedReference);
        }

        if ($normalizedReference === 'latest') {
            return 'latest';
        }

        if (preg_match('/^latest:(pending|running|blocked|failed|completed|cancelled)$/', $normalizedReference) === 1) {
            return $normalizedReference;
        }

        if (preg_match('/^(pending|running|blocked|failed|completed|cancelled)$/', $normalizedReference, $matches) === 1) {
            return sprintf('latest:%s', trim((string) $matches[1]));
        }

        $branchReference = $this->extractBranchReference($referenceText);

        if ($branchReference !== null) {
            return sprintf('branch:%s', $branchReference);
        }

        $pullRequestNumber = $this->extractPullRequestNumber($referenceText);

        if ($pullRequestNumber !== null) {
            return sprintf('pr:%d', $pullRequestNumber);
        }

        $issueKey = $this->extractIssueKey($referenceText);

        if ($issueKey !== null) {
            return sprintf('issue:%s', $issueKey);
        }

        return $defaultReference;
    }

    /**
     * Resolve one GitHub-oriented conversational lookup target into a task reference.
     *
     * Preserve PR-prefixed phrases so numeric suffixes such as `check pr 105`
     * are treated as PR numbers instead of task ids.
     *
     * @param  string  $text
     * @param  array<int, string>  $prefixes
     * @param  string  $defaultReference
     * @return string
     */
    protected function resolveGithubLookupTaskReference(string $text, array $prefixes, string $defaultReference = 'latest'): string
    {
        $matchedPhrase = $this->resolveMatchedPhrase($text, $prefixes);
        $referenceText = $this->stripLeadingIntentPhrase($text, $prefixes);
        $normalizedReference = mb_strtolower(trim($referenceText));

        if ($matchedPhrase !== null && in_array($matchedPhrase, ['pr status', 'check pr', 'xem pr'], true)) {
            if (preg_match('/^\d+$/', $normalizedReference) === 1) {
                return sprintf('pr:%d', (int) $normalizedReference);
            }
        }

        return $this->resolveLookupTaskReference($text, $prefixes, $defaultReference);
    }

    /**
     * Resolve the default lookup target for the current Telegram chat.
     *
     * Prefer the active task linked to the current chat session before falling
     * back to a generic latest-task reference.
     *
     * @param  array<string, mixed>  $messageContext
     * @param  string  $fallbackReference
     * @return string
     */
    protected function resolveDefaultLookupReference(array $messageContext, string $fallbackReference = 'latest'): string
    {
        $activeTaskReference = $this->resolveLinkedActiveTaskReference($messageContext);

        return $activeTaskReference ?? $fallbackReference;
    }

    /**
     * Resolve the active task reference linked to the current Telegram chat.
     *
     * @param  array<string, mixed>  $messageContext
     * @return string|null
     */
    protected function resolveLinkedActiveTaskReference(array $messageContext): ?string
    {
        $chatId = $messageContext['chat_id'] ?? null;

        if (! is_string($chatId) && ! is_int($chatId)) {
            return null;
        }

        $activeTaskId = $this->chatStateService->getActiveTaskId($chatId);

        if ($activeTaskId === null) {
            return null;
        }

        $task = $this->taskQueryService->findDetailedById($activeTaskId);

        return $task instanceof AutoCodingTask && $this->taskBelongsToChat($task, $chatId)
            ? (string) $activeTaskId
            : null;
    }

    /**
     * Resolve one optional queue status filter from conversational text.
     *
     * @param  string  $text
     * @return string|null
     */
    protected function resolveQueueStatusFilter(string $text): ?string
    {
        $normalizedText = mb_strtolower(trim($text));

        return in_array($normalizedText, ['pending', 'running', 'blocked', 'failed', 'completed', 'cancelled'], true)
            ? $normalizedText
            : null;
    }

    /**
     * Extract one optional branch reference from conversational lookup text.
     *
     * @param  string  $text
     * @return string|null
     */
    protected function extractBranchReference(string $text): ?string
    {
        if (preg_match('/\bbranch\s+([^\s]+)\b/i', $text, $matches) !== 1) {
            return null;
        }

        $branchName = trim((string) $matches[1]);

        return $branchName !== '' ? $branchName : null;
    }

    /**
     * Extract one optional pull-request number from conversational lookup text.
     *
     * @param  string  $text
     * @return int|null
     */
    protected function extractPullRequestNumber(string $text): ?int
    {
        if (preg_match('/\b(?:pr|pull\s+request)\s+#?(\d+)\b/i', $text, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Extract one optional issue key from conversational Telegram input.
     *
     * @param  string  $text
     * @return string|null
     */
    protected function extractIssueKey(string $text): ?string
    {
        if (preg_match('/\b([A-Za-z][A-Za-z0-9_-]*-\d+)\b/', $text, $matches) !== 1) {
            return null;
        }

        $issueKey = strtoupper(trim($matches[1]));

        return $issueKey !== '' ? $issueKey : null;
    }

    /**
     * Remove one extracted issue key from conversational text.
     *
     * @param  string  $text
     * @param  string|null  $issueKey
     * @return string
     */
    protected function stripIssueKey(string $text, ?string $issueKey): string
    {
        if (! is_string($issueKey) || $issueKey === '') {
            return trim($text);
        }

        return trim((string) preg_replace('/\b'.preg_quote($issueKey, '/').'\b/i', '', $text));
    }
}
