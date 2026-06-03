<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Enums\AutoCodingExecutionStatus;

class AutoCodingTelegramCommandParser
{
    public function __construct(
        private readonly AutoCodingTelegramTextService $textService,
    ) {}

    /**
     * Parse one Telegram update into a normalized remote-control action.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public function parse(array $update): array
    {
        $callbackQuery = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;
        /** @var array<string, mixed> $message */
        $message = is_array($callbackQuery['message'] ?? null)
            ? $callbackQuery['message']
            : (is_array($update['message'] ?? null) ? $update['message'] : []);
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = is_array($callbackQuery['from'] ?? null)
            ? $callbackQuery['from']
            : (is_array($message['from'] ?? null) ? $message['from'] : []);

        $messageContext = [
            'chat_id' => $chat['id'] ?? null,
            'chat_type' => is_string($chat['type'] ?? null) ? $chat['type'] : null,
            'user_id' => $from['id'] ?? null,
            'username' => is_string($from['username'] ?? null) ? $from['username'] : null,
            'message_id' => is_numeric($message['message_id'] ?? null) ? (int) $message['message_id'] : null,
            'message_thread_id' => is_numeric($message['message_thread_id'] ?? null) ? (int) $message['message_thread_id'] : null,
            'callback_query_id' => is_string($callbackQuery['id'] ?? null) ? $callbackQuery['id'] : null,
        ];

        if ($callbackQuery !== null) {
            return $this->parseCallbackQuery(
                is_string($callbackQuery['data'] ?? null) ? $callbackQuery['data'] : '',
                $messageContext
            );
        }

        $text = $this->resolveMessageText($message);

        return $this->parseMessageText($text, $messageContext);
    }

    /**
     * Resolve text-like content from one Telegram message.
     *
     * @param  array<string, mixed>  $message
     * @return string
     */
    protected function resolveMessageText(array $message): string
    {
        if (is_string($message['text'] ?? null)) {
            return trim((string) $message['text']);
        }

        if (is_string($message['caption'] ?? null)) {
            return trim((string) $message['caption']);
        }

        if (is_array($message['photo'] ?? null) || is_array($message['document'] ?? null)) {
            return '__telegram_media_message__';
        }

        return '';
    }

    /**
     * Parse one Telegram bot command message.
     *
     * @param  string  $text
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function parseMessageText(string $text, array $messageContext): array
    {
        if ($text === '') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
            ];
        }

        if ($text === '__telegram_media_message__') {
            return [
                'action' => 'media_message',
                'message_context' => $messageContext,
            ];
        }

        if (! str_starts_with($text, '/')) {
            return [
                'action' => 'conversation',
                'text' => $text,
                'message_context' => $messageContext,
            ];
        }

        [$command, $arguments] = $this->splitCommand($text);

        return match ($command) {
            'help' => [
                'action' => 'help',
                'message_context' => $messageContext,
            ],
            'start', 'chat_start' => [
                'action' => 'chat_start',
                'message_context' => $messageContext,
            ],
            'chat_status' => [
                'action' => 'chat_status',
                'message_context' => $messageContext,
            ],
            'stop', 'chat_stop' => [
                'action' => 'chat_stop',
                'message_context' => $messageContext,
            ],
            'chat_reset' => [
                'action' => 'chat_reset',
                'message_context' => $messageContext,
            ],
            'chat_mode' => $this->buildChatModeAction($arguments, $messageContext),
            'menu' => $this->buildMenuAction($arguments, $messageContext),
            'code', 'coding' => $this->buildCreateTaskAction('code', $arguments, $messageContext),
            'issue' => $this->buildIssueTaskAction($arguments, $messageContext),
            'review' => $this->buildCreateTaskAction('review', $arguments, $messageContext),
            'validate' => $this->buildCreateTaskAction('validate', $arguments, $messageContext),
            'status' => $this->buildTaskLookupAction('status', $arguments, $messageContext),
            'next', 'next_action' => $this->buildTaskLookupAction('next_action', $arguments, $messageContext),
            'followup', 'follow_up' => $this->buildTaskLookupAction('follow_up', $arguments, $messageContext),
            'validation', 'validation_report' => $this->buildTaskLookupAction('validation', $arguments, $messageContext),
            'github' => $this->buildTaskLookupAction('github_status', $arguments, $messageContext),
            'changes' => $this->buildTaskLookupAction('changes', $arguments, $messageContext),
            'summary' => $this->buildTaskLookupAction('summary', $arguments, $messageContext),
            'queue' => $this->buildQueueAction($arguments, $messageContext),
            'cancel' => $this->buildCancelTaskAction($arguments, $messageContext),
            'cancelall', 'cancel_all' => [
                'action' => 'cancel_tasks',
                'scope' => 'active',
                'message_context' => $messageContext,
            ],
            'delete' => $this->buildDeleteTaskAction($arguments, $messageContext),
            'deleteall', 'delete_all' => $this->buildDeleteAllTasksAction($arguments, $messageContext),
            'clear', 'reset' => $this->buildResetAction($arguments, $messageContext, 'session'),
            'clearall', 'clear_all' => $this->buildResetAction($arguments, $messageContext, 'all'),
            'resume' => $this->buildResumeAction($arguments, $messageContext),
            default => [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.unknown_command', [
                    'command' => $command,
                ]),
            ],
        };
    }

    /**
     * Parse one Telegram inline-button callback payload.
     *
     * @param  string  $data
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function parseCallbackQuery(string $data, array $messageContext): array
    {
        $parts = explode(':', trim($data));

        if ($parts[0] !== 'ac') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.unsupported_action_payload'),
            ];
        }

        $action = $parts[1] ?? 'help';
        $taskReference = $parts[2] ?? 'latest';

        return match ($action) {
            'status', 'changes', 'summary', 'next', 'followup', 'validation', 'github' => [
                'action' => match ($action) {
                    'next' => 'next_action',
                    'followup' => 'follow_up',
                    'validation' => 'validation',
                    'github' => 'github_status',
                    default => $action,
                },
                'task_reference' => $taskReference,
                'message_context' => $messageContext,
            ],
            'latest' => [
                'action' => isset($parts[2]) && in_array($parts[2], ['status', 'summary', 'changes', 'next', 'followup', 'validation', 'github'], true)
                    ? match (trim((string) $parts[2])) {
                        'next' => 'next_action',
                        'followup' => 'follow_up',
                        'validation' => 'validation',
                        'github' => 'github_status',
                        default => trim((string) $parts[2]),
                    }
                    : 'help',
                'task_reference' => isset($parts[3]) ? sprintf('latest:%s', trim((string) $parts[3])) : 'latest',
                'message_context' => $messageContext,
            ],
            'menu' => [
                'action' => 'menu',
                'menu_key' => isset($parts[2]) ? trim((string) $parts[2]) : 'root',
                'message_context' => $messageContext,
            ],
            'chat' => $this->buildCallbackChatAction($parts, $messageContext),
            'create' => $this->buildCreateTaskAction(
                isset($parts[2]) ? trim((string) $parts[2]) : '',
                '',
                $messageContext
            ),
            'queue', 'help', 'reset', 'cancel', 'cancel-all', 'delete', 'delete-all' => [
                'action' => match ($action) {
                    'cancel' => 'cancel_task',
                    'cancel-all' => 'cancel_tasks',
                    'delete' => 'delete_task',
                    'delete-all' => 'delete_tasks',
                    default => $action,
                },
                'status_filter' => $action === 'queue' ? $this->normalizeQueueStatusFilter($parts[2] ?? null) : null,
                'task_reference' => in_array($action, ['cancel', 'delete'], true)
                    ? implode(':', array_values(array_filter([
                        $parts[2] ?? null,
                        $parts[3] ?? null,
                    ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')))
                    : null,
                'scope' => match ($action) {
                    'cancel-all' => 'active',
                    'delete-all' => 'pending',
                    'reset' => $this->normalizeClearScope($parts[2] ?? null),
                    default => null,
                },
                'message_context' => $messageContext,
            ],
            'resume' => [
                'action' => 'resume',
                'task_reference' => $taskReference,
                'response' => isset($parts[3]) ? trim($parts[3]) : '',
                'message_context' => $messageContext,
            ],
            'resume-latest' => [
                'action' => 'resume',
                'task_reference' => isset($parts[2]) ? sprintf('latest:%s', trim((string) $parts[2])) : 'latest:blocked',
                'response' => isset($parts[3]) ? trim((string) $parts[3]) : 'allow',
                'message_context' => $messageContext,
            ],
            'clarify' => [
                'action' => 'clarify_intent',
                'intent' => isset($parts[2]) ? trim((string) $parts[2]) : '',
                'message_context' => $messageContext,
            ],
            'issue-context' => [
                'action' => 'clarify_issue_context',
                'selection' => isset($parts[2]) ? trim((string) $parts[2]) : '',
                'message_context' => $messageContext,
            ],
            'confirm' => [
                'action' => 'confirm_pending',
                'decision' => isset($parts[2]) ? trim((string) $parts[2]) : '',
                'message_context' => $messageContext,
            ],
            'ra' => [
                'action' => 'resume',
                'task_reference' => $taskReference,
                'response' => isset($parts[4]) ? trim($parts[4]) : '',
                'question_reference' => isset($parts[3]) ? trim($parts[3]) : '',
                'message_context' => $messageContext,
            ],
            default => [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.unsupported_action_payload'),
            ],
        };
    }

    /**
     * Build one reset-chat action from optional Telegram command flags.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildResetAction(string $arguments, array $messageContext, string $defaultScope = 'session'): array
    {
        return [
            'action' => 'reset',
            'force_cleanup' => $this->hasForceFlag($arguments),
            'scope' => $this->normalizeClearScope($arguments, $defaultScope),
            'message_context' => $messageContext,
        ];
    }

    /**
     * Build one normalized menu-navigation action from a Telegram command body.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildMenuAction(string $arguments, array $messageContext): array
    {
        $menuKey = trim($arguments) !== '' ? trim($arguments) : 'root';

        return [
            'action' => 'menu',
            'menu_key' => $menuKey,
            'message_context' => $messageContext,
        ];
    }

    /**
     * Build one normalized chat-mode action from a Telegram command body.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildChatModeAction(string $arguments, array $messageContext): array
    {
        $mode = trim(strtolower($arguments));

        return match ($mode) {
            'on', 'start' => [
                'action' => 'chat_start',
                'message_context' => $messageContext,
            ],
            'off', 'stop' => [
                'action' => 'chat_stop',
                'message_context' => $messageContext,
            ],
            'reset' => [
                'action' => 'chat_reset',
                'message_context' => $messageContext,
            ],
            default => [
                'action' => 'chat_status',
                'message_context' => $messageContext,
            ],
        };
    }

    /**
     * Build one normalized chat-session action from Telegram callback data.
     *
     * @param  array<int, string>  $parts
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildCallbackChatAction(array $parts, array $messageContext): array
    {
        $subAction = isset($parts[2]) ? trim(strtolower((string) $parts[2])) : 'status';

        return match ($subAction) {
            'start', 'on' => [
                'action' => 'chat_start',
                'message_context' => $messageContext,
            ],
            'stop', 'off' => [
                'action' => 'chat_stop',
                'message_context' => $messageContext,
            ],
            'reset' => [
                'action' => 'chat_reset',
                'message_context' => $messageContext,
            ],
            default => [
                'action' => 'chat_status',
                'message_context' => $messageContext,
            ],
        };
    }

    /**
     * Build one normalized queue action, optionally filtered by task status.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildQueueAction(string $arguments, array $messageContext): array
    {
        return [
            'action' => 'queue',
            'status_filter' => $this->normalizeQueueStatusFilter($arguments),
            'message_context' => $messageContext,
        ];
    }

    /**
     * Normalize one queue status filter from Telegram input.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function normalizeQueueStatusFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim(strtolower($value));

        return in_array($normalizedValue, AutoCodingExecutionStatus::allValues(), true)
            ? $normalizedValue
            : null;
    }

    /**
     * Build one normalized cancel-task action from a Telegram command body.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildCancelTaskAction(string $arguments, array $messageContext): array
    {
        $taskReference = trim($arguments) !== '' ? trim($arguments) : 'latest:running';

        if (in_array(strtolower($taskReference), ['all', 'active'], true)) {
            return [
                'action' => 'cancel_tasks',
                'scope' => 'active',
                'message_context' => $messageContext,
            ];
        }

        return [
            'action' => 'cancel_task',
            'task_reference' => $taskReference,
            'message_context' => $messageContext,
        ];
    }

    /**
     * Build one normalized delete-task action from a Telegram command body.
     *
     * Delete commands are intentionally limited to pending tasks so operators do not
     * remove running or completed workflow history by mistake.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildDeleteTaskAction(string $arguments, array $messageContext): array
    {
        $taskReference = trim($arguments) !== '' ? trim($arguments) : 'latest:pending';

        if (in_array(strtolower($taskReference), ['all', 'pending'], true)) {
            return [
                'action' => 'delete_tasks',
                'scope' => 'pending',
                'message_context' => $messageContext,
            ];
        }

        return [
            'action' => 'delete_task',
            'task_reference' => $taskReference,
            'message_context' => $messageContext,
        ];
    }

    /**
     * Build one normalized bulk-delete action from a Telegram command body.
     *
     * `/deleteall` keeps the safe pending-only behavior by default.
     * `/deleteall all` is the explicit operator opt-in for clearing every task row.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildDeleteAllTasksAction(string $arguments, array $messageContext): array
    {
        $normalizedScope = trim(strtolower($arguments));

        return [
            'action' => 'delete_tasks',
            'scope' => $this->resolveDeleteAllScope($normalizedScope),
            'message_context' => $messageContext,
        ];
    }

    /**
     * Determine whether one raw argument string contains the force flag.
     *
     * @param  string  $arguments
     * @return bool
     */
    protected function hasForceFlag(string $arguments): bool
    {
        return preg_match('/(^|\s)--force(\s|$)/', trim($arguments)) === 1;
    }

    /**
     * Normalize one clear-chat scope value from Telegram input.
     *
     * @param  mixed  $value
     * @param  string  $defaultScope
     * @return string
     */
    protected function normalizeClearScope(mixed $value, string $defaultScope = 'session'): string
    {
        $normalizedValue = is_string($value) ? trim(strtolower($value)) : '';

        if (in_array($normalizedValue, ['all', 'clear_all', 'clearall', '--all'], true)) {
            return 'all';
        }

        return $defaultScope === 'all' ? 'all' : 'session';
    }

    /**
     * Resolve the destructive scope for one delete-all command body.
     *
     * `--force` is the preferred operator-facing form for clearing every task row.
     * `all` stays supported as a backward-compatible alias.
     *
     * @param  string  $arguments
     * @return string
     */
    protected function resolveDeleteAllScope(string $arguments): string
    {
        return in_array($arguments, ['all', 'force', '--force'], true)
            ? 'all'
            : 'pending';
    }

    /**
     * Build one normalized create-task action from a Telegram command body.
     *
     * @param  string  $command
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildCreateTaskAction(string $command, string $arguments, array $messageContext): array
    {
        $options = $this->extractTaskOptions($arguments);
        $issueKey = $options['issue_key'];
        $summary = $options['summary'];
        $normalizedSummary = trim($summary);

        if ($normalizedSummary === '') {
            $normalizedSummary = match ($command) {
                'review' => 'Review the latest repository changes.',
                'validate' => 'Validate the current repository state.',
                default => '',
            };
        }

        if ($normalizedSummary === '') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.code_summary_required'),
            ];
        }

        $taskPayload = [
            'summary' => $this->resolveTaskSummary($command, $normalizedSummary),
            'issue_key' => $issueKey,
            'repository_path' => $options['repository_path'],
            'validate' => $this->resolveValidationPreference($command, $options),
            'provider' => $this->resolveProviderPreference($command, $options),
            'provider_options' => $this->resolveProviderOptions($options),
            'dirty_workspace_policy' => $options['dirty_workspace_policy'],
            'scope_paths' => $options['scope_paths'],
            'scope_policy' => $options['scope_policy'],
            'context_metadata' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'command' => $command,
                    'telegram' => [
                        'chat_id' => $messageContext['chat_id'] ?? null,
                        'user_id' => $messageContext['user_id'] ?? null,
                        'username' => $messageContext['username'] ?? null,
                        'chat_type' => $messageContext['chat_type'] ?? null,
                        'message_thread_id' => $messageContext['message_thread_id'] ?? null,
                    ],
                ],
            ],
        ];

        return [
            'action' => 'create_task',
            'command' => $command,
            'message_context' => $messageContext,
            'task_payload' => array_filter($taskPayload, static fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * Build one issue-driven coding task action from a Telegram command body.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildIssueTaskAction(string $arguments, array $messageContext): array
    {
        $normalizedArguments = trim($arguments);

        if ($normalizedArguments === '') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.issue_key_required'),
            ];
        }

        $parts = preg_split('/\s+/', $normalizedArguments, 2) ?: [];
        $issueKey = is_string($parts[0] ?? null) ? strtoupper(trim((string) $parts[0])) : '';
        $summary = is_string($parts[1] ?? null) ? trim((string) $parts[1]) : '';

        if ($issueKey === '') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.issue_key_required'),
            ];
        }

        if ($summary === '') {
            $summary = sprintf('Review GitHub issue %s and implement the requested changes.', $issueKey);
        }

        return $this->buildCreateTaskAction(
            'code',
            sprintf('%s --issue %s', $summary, $issueKey),
            $messageContext
        );
    }

    /**
     * Build one normalized task-lookup action from a Telegram command body.
     *
     * @param  string  $action
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildTaskLookupAction(string $action, string $arguments, array $messageContext): array
    {
        $taskReference = trim($arguments) !== '' ? trim($arguments) : 'latest';

        return [
            'action' => $action,
            'task_reference' => $taskReference,
            'message_context' => $messageContext,
        ];
    }

    /**
     * Build one normalized resume action from a Telegram command body.
     *
     * @param  string  $arguments
     * @param  array<string, mixed>  $messageContext
     * @return array<string, mixed>
     */
    protected function buildResumeAction(string $arguments, array $messageContext): array
    {
        $parts = preg_split('/\s+/', trim($arguments), 2);
        $taskReference = is_array($parts) && isset($parts[0]) ? trim((string) $parts[0]) : '';
        $response = is_array($parts) && isset($parts[1]) ? trim((string) $parts[1]) : '';

        if ($taskReference === '' || $response === '') {
            return [
                'action' => 'help',
                'message_context' => $messageContext,
                'error' => $this->textService->line('errors.resume_usage_required'),
            ];
        }

        return [
            'action' => 'resume',
            'task_reference' => $taskReference,
            'response' => $response,
            'message_context' => $messageContext,
        ];
    }

    /**
     * Split one Telegram command into the command key and free-form arguments.
     *
     * @param  string  $text
     * @return array{0:string,1:string}
     */
    protected function splitCommand(string $text): array
    {
        $normalizedText = ltrim($text, '/');
        $parts = preg_split('/\s+/', $normalizedText, 2);
        $rawCommand = is_array($parts) && isset($parts[0]) ? strtolower(trim((string) $parts[0])) : 'help';
        $command = explode('@', $rawCommand)[0];
        $arguments = is_array($parts) && isset($parts[1]) ? trim((string) $parts[1]) : '';

        return [$command, $arguments];
    }

    /**
     * Extract one optional issue key from the Telegram command body.
     *
     * @param  string  $arguments
     * @return string|null
     */
    protected function extractIssueKey(string $arguments): ?string
    {
        if (preg_match('/(?:^|\s)--issue(?:=|\s+)([A-Za-z][A-Za-z0-9_-]*-\d+)/', $arguments, $matches) !== 1) {
            return null;
        }

        $issueKey = strtoupper(trim($matches[1]));

        return $issueKey !== '' ? $issueKey : null;
    }

    /**
     * Remove the optional issue flag from one Telegram command body.
     *
     * @param  string  $arguments
     * @return string
     */
    protected function stripIssueFlag(string $arguments): string
    {
        return trim((string) preg_replace('/(?:^|\s)--issue(?:=|\s+)[A-Za-z][A-Za-z0-9_-]*-\d+/', ' ', $arguments));
    }

    /**
     * Extract the normalized task options encoded in one Telegram command body.
     *
     * @param  string  $arguments
     * @return array{
     *   issue_key:string|null,
     *   repository_path:string|null,
     *   provider:string|null,
     *   model:string|null,
     *   validate:bool|null,
     *   dirty_workspace_policy:string|null,
     *   scope_policy:string|null,
     *   scope_paths:array<int, string>,
     *   summary:string
     * }
     */
    protected function extractTaskOptions(string $arguments): array
    {
        $issueKey = null;
        $repositoryPath = null;
        $provider = null;
        $model = null;
        $validate = null;
        $dirtyWorkspacePolicy = null;
        $scopePolicy = null;
        $scopePaths = [];
        $summarySegments = [];

        $tokens = preg_split('/\s+/', trim($arguments)) ?: [];
        $tokenCount = count($tokens);

        for ($index = 0; $index < $tokenCount; $index++) {
            $token = trim((string) $tokens[$index]);

            if ($token === '') {
                continue;
            }

            if ($this->isFlagToken($token, 'issue')) {
                $issueKey = $this->resolveOptionValue($tokens, $index, $token);

                continue;
            }

            if ($this->isFlagToken($token, 'path')) {
                $repositoryPath = $this->resolveOptionValue($tokens, $index, $token);

                continue;
            }

            if ($this->isFlagToken($token, 'provider')) {
                $provider = $this->resolveOptionValue($tokens, $index, $token);

                continue;
            }

            if ($this->isFlagToken($token, 'model')) {
                $model = $this->resolveOptionValue($tokens, $index, $token);

                continue;
            }

            if ($this->isFlagToken($token, 'validate')) {
                $validate = true;

                continue;
            }

            if ($this->isFlagToken($token, 'no-validate')) {
                $validate = false;

                continue;
            }

            if ($this->isFlagToken($token, 'dirty-policy')) {
                $dirtyWorkspacePolicy = $this->normalizePolicyValue(
                    $this->resolveOptionValue($tokens, $index, $token)
                );

                continue;
            }

            if ($this->isFlagToken($token, 'scope-policy')) {
                $scopePolicy = $this->normalizePolicyValue(
                    $this->resolveOptionValue($tokens, $index, $token)
                );

                continue;
            }

            if ($this->isFlagToken($token, 'scope')) {
                $rawScopeValue = $this->resolveOptionValue($tokens, $index, $token);
                $scopePaths = $rawScopeValue === null ? [] : $this->normalizeScopePaths($rawScopeValue);

                continue;
            }

            $summarySegments[] = $token;
        }

        return [
            'issue_key' => $this->normalizeOptionalString($issueKey),
            'repository_path' => $this->normalizeOptionalString($repositoryPath),
            'provider' => $this->normalizeOptionalString($provider),
            'model' => $this->normalizeOptionalString($model),
            'validate' => $validate,
            'dirty_workspace_policy' => $dirtyWorkspacePolicy,
            'scope_policy' => $scopePolicy,
            'scope_paths' => $scopePaths,
            'summary' => trim(implode(' ', $summarySegments)),
        ];
    }

    /**
     * Determine whether one token matches a named long-form Telegram command flag.
     *
     * @param  string  $token
     * @param  string  $flagName
     * @return bool
     */
    protected function isFlagToken(string $token, string $flagName): bool
    {
        return $token === sprintf('--%s', $flagName)
            || str_starts_with($token, sprintf('--%s=', $flagName));
    }

    /**
     * Resolve the value of one Telegram command flag and advance the token pointer when needed.
     *
     * @param  array<int, string>  $tokens
     * @param  int  $index
     * @param  string  $token
     * @return string|null
     */
    protected function resolveOptionValue(array $tokens, int &$index, string $token): ?string
    {
        $segments = explode('=', $token, 2);

        if (isset($segments[1])) {
            $value = trim($segments[1]);

            return $value !== '' ? $value : null;
        }

        $nextIndex = $index + 1;

        if (! isset($tokens[$nextIndex])) {
            return null;
        }

        $nextValue = trim((string) $tokens[$nextIndex]);

        if ($nextValue === '' || str_starts_with($nextValue, '--')) {
            return null;
        }

        $index = $nextIndex;

        return $nextValue;
    }

    /**
     * Normalize one optional Telegram string option.
     *
     * @param  string|null  $value
     * @return string|null
     */
    protected function normalizeOptionalString(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    /**
     * Normalize one Telegram policy option to the allowed execution-policy set.
     *
     * @param  string|null  $value
     * @return string|null
     */
    protected function normalizePolicyValue(?string $value): ?string
    {
        $normalizedValue = $this->normalizeOptionalString($value);

        if ($normalizedValue === null) {
            return null;
        }

        return in_array($normalizedValue, ['warn', 'block', 'allow'], true)
            ? $normalizedValue
            : null;
    }

    /**
     * Normalize one Telegram comma-separated scope list into stable path prefixes.
     *
     * @param  string  $rawScopeValue
     * @return array<int, string>
     */
    protected function normalizeScopePaths(string $rawScopeValue): array
    {
        $scopePaths = array_map('trim', explode(',', $rawScopeValue));
        $scopePaths = array_values(array_filter($scopePaths, static fn (string $path): bool => $path !== ''));

        return array_values(array_unique($scopePaths));
    }

    /**
     * Resolve whether one Telegram command should run validation.
     *
     * @param  string  $command
     * @param  array{
     *   validate:bool|null
     * }  $options
     * @return bool
     */
    protected function resolveValidationPreference(string $command, array $options): bool
    {
        if (is_bool($options['validate'])) {
            return $options['validate'];
        }

        return true;
    }

    /**
     * Resolve the provider preference encoded in one Telegram command.
     *
     * @param  string  $command
     * @param  array{
     *   provider:string|null
     * }  $options
     * @return string|null
     */
    protected function resolveProviderPreference(string $command, array $options): ?string
    {
        if (is_string($options['provider']) && $options['provider'] !== '') {
            return $options['provider'];
        }

        return $command === 'validate' ? 'null' : null;
    }

    /**
     * Resolve any provider-specific task options encoded in one Telegram command.
     *
     * @param  array{
     *   model:string|null
     * }  $options
     * @return array<string, mixed>|null
     */
    protected function resolveProviderOptions(array $options): ?array
    {
        $providerOptions = [];

        if (is_string($options['model']) && $options['model'] !== '') {
            $providerOptions['model'] = $options['model'];
        }

        return $providerOptions !== [] ? $providerOptions : null;
    }

    /**
     * Resolve the persisted task summary from the Telegram command type.
     *
     * @param  string  $command
     * @param  string  $summary
     * @return string
     */
    protected function resolveTaskSummary(string $command, string $summary): string
    {
        return match ($command) {
            'review' => sprintf('Review request: %s', $summary),
            'validate' => sprintf('Validation request: %s', $summary),
            default => $summary,
        };
    }
}
