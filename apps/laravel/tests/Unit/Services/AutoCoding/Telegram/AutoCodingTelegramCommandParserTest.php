<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding\Telegram;

use App\Services\AutoCoding\Telegram\AutoCodingTelegramCommandParser;
use Tests\TestCase;

class AutoCodingTelegramCommandParserTest extends TestCase
{
    /**
     * Confirm the parser extracts structured task flags from a Telegram code command.
     *
     * @return void
     */
    public function test_it_parses_structured_task_flags_from_a_code_command(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'message' => [
                'message_id' => 1,
                'text' => '/code Build Telegram phase 3 --issue OPAS-0072 --path /workspace/repo --provider ollama --model qwen2.5:14b --scope apps/laravel/app,docs --scope-policy block --dirty-policy allow --no-validate',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('create_task', $action['action']);
        self::assertSame('code', $action['command']);
        self::assertSame('Build Telegram phase 3', $action['task_payload']['summary'] ?? null);
        self::assertSame('OPAS-0072', $action['task_payload']['issue_key'] ?? null);
        self::assertSame('/workspace/repo', $action['task_payload']['repository_path'] ?? null);
        self::assertSame('ollama', $action['task_payload']['provider'] ?? null);
        self::assertSame(['model' => 'qwen2.5:14b'], $action['task_payload']['provider_options'] ?? null);
        self::assertSame(['apps/laravel/app', 'docs'], $action['task_payload']['scope_paths'] ?? null);
        self::assertSame('block', $action['task_payload']['scope_policy'] ?? null);
        self::assertSame('allow', $action['task_payload']['dirty_workspace_policy'] ?? null);
        self::assertFalse($action['task_payload']['validate'] ?? true);
    }

    /**
     * Confirm the parser applies validation-command defaults for Telegram validate requests.
     *
     * @return void
     */
    public function test_it_applies_validate_defaults_for_validate_commands(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'message' => [
                'message_id' => 2,
                'text' => '/validate --scope apps/laravel/app',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('create_task', $action['action']);
        self::assertSame('null', $action['task_payload']['provider'] ?? null);
        self::assertTrue($action['task_payload']['validate'] ?? false);
        self::assertSame('Validation request: Validate the current repository state.', $action['task_payload']['summary'] ?? null);
        self::assertSame(['apps/laravel/app'], $action['task_payload']['scope_paths'] ?? null);
    }

    /**
     * Confirm the parser keeps non-command Telegram text in conversational mode.
     *
     * @return void
     */
    public function test_it_parses_non_command_text_as_conversation(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'message' => [
                'message_id' => 3,
                'text' => 'Fix Telegram status formatting for blocked tasks',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('conversation', $action['action']);
        self::assertSame('Fix Telegram status formatting for blocked tasks', $action['text']);
    }

    /**
     * Confirm photo captions stay in conversational mode instead of resetting to help.
     *
     * @return void
     */
    public function test_it_parses_photo_caption_as_conversation(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'message' => [
                'message_id' => 4,
                'caption' => 'Sửa icon admin bị lệch như ảnh',
                'photo' => [
                    ['file_id' => 'photo-small'],
                ],
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('conversation', $action['action']);
        self::assertSame('Sửa icon admin bị lệch như ảnh', $action['text']);
    }

    /**
     * Confirm media-only Telegram messages do not fall back to the full help menu.
     *
     * @return void
     */
    public function test_it_parses_media_without_caption_as_media_message(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'message' => [
                'message_id' => 5,
                'photo' => [
                    ['file_id' => 'photo-small'],
                ],
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('media_message', $action['action']);
    }

    /**
     * Confirm the parser can normalize direct chat-session Telegram commands.
     *
     * @return void
     */
    public function test_it_parses_chat_session_commands(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $start = $parser->parse([
            'message' => [
                'message_id' => 30,
                'text' => '/start',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);
        $mode = $parser->parse([
            'message' => [
                'message_id' => 31,
                'text' => '/chat_mode off',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);
        $callback = $parser->parse([
            'callback_query' => [
                'id' => 'callback-chat-status-1',
                'data' => 'ac:chat:status',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 32,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('chat_start', $start['action']);
        self::assertSame('chat_stop', $mode['action']);
        self::assertSame('chat_status', $callback['action']);
    }

    /**
     * Confirm plain text chat-session control phrases stay in conversational mode.
     *
     * @return void
     */
    public function test_it_keeps_plain_text_chat_session_phrases_as_conversation(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'message' => [
                'message_id' => 33,
                'text' => 'chat status',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('conversation', $action['action']);
        self::assertSame('chat status', $action['text']);
    }

    /**
     * Confirm the parser can normalize clear-chat cleanup requests.
     *
     * @return void
     */
    public function test_it_parses_clear_chat_cleanup_commands(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $currentSessionAction = $parser->parse([
            'message' => [
                'message_id' => 34,
                'text' => '/clear --force',
                'chat' => [
                    'id' => 123456,
                    'type' => 'supergroup',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);
        $allAction = $parser->parse([
            'message' => [
                'message_id' => 35,
                'text' => '/clear_all --force',
                'chat' => [
                    'id' => 123456,
                    'type' => 'supergroup',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('reset', $currentSessionAction['action']);
        self::assertSame('session', $currentSessionAction['scope'] ?? null);
        self::assertTrue($currentSessionAction['force_cleanup'] ?? false);
        self::assertSame('reset', $allAction['action']);
        self::assertSame('all', $allAction['scope'] ?? null);
        self::assertTrue($allAction['force_cleanup'] ?? false);
    }

    /**
     * Confirm the parser can normalize clarification and confirmation callbacks.
     *
     * @return void
     */
    public function test_it_parses_clarification_and_confirmation_callbacks(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $clarify = $parser->parse([
            'callback_query' => [
                'id' => 'callback-clarify-code-1',
                'data' => 'ac:clarify:code',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 28,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('clarify_intent', $clarify['action']);
        self::assertSame('code', $clarify['intent']);

        $confirm = $parser->parse([
            'callback_query' => [
                'id' => 'callback-confirm-yes-1',
                'data' => 'ac:confirm:yes',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 29,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('confirm_pending', $confirm['action']);
        self::assertSame('yes', $confirm['decision']);
    }

    /**
     * Confirm the parser can route Telegram menu callbacks into named submenu actions.
     *
     * @return void
     */
    public function test_it_parses_named_menu_callbacks(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'callback_query' => [
                'id' => 'callback-menu-reports-1',
                'data' => 'ac:menu:reports',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 21,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('menu', $action['action']);
        self::assertSame('reports', $action['menu_key']);
    }

    /**
     * Confirm the parser can extract status filters from Telegram queue callbacks.
     *
     * @return void
     */
    public function test_it_parses_queue_status_filters_from_callbacks(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $action = $parser->parse([
            'callback_query' => [
                'id' => 'callback-queue-failed-1',
                'data' => 'ac:queue:failed',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 22,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('queue', $action['action']);
        self::assertSame('failed', $action['status_filter']);
    }

    /**
     * Confirm the parser can resolve latest-by-status and resume-latest callback shortcuts.
     *
     * @return void
     */
    public function test_it_parses_latest_status_shortcuts_from_callbacks(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $latestFailed = $parser->parse([
            'callback_query' => [
                'id' => 'callback-latest-failed-1',
                'data' => 'ac:latest:summary:failed',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 23,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('summary', $latestFailed['action']);
        self::assertSame('latest:failed', $latestFailed['task_reference']);

        $resumeLatestBlocked = $parser->parse([
            'callback_query' => [
                'id' => 'callback-resume-latest-blocked-1',
                'data' => 'ac:resume-latest:blocked:allow',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 24,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('resume', $resumeLatestBlocked['action']);
        self::assertSame('latest:blocked', $resumeLatestBlocked['task_reference']);
        self::assertSame('allow', $resumeLatestBlocked['response']);

        $latestBlockedNext = $parser->parse([
            'callback_query' => [
                'id' => 'callback-latest-next-blocked-1',
                'data' => 'ac:latest:next:blocked',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 25,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('next_action', $latestBlockedNext['action']);
        self::assertSame('latest:blocked', $latestBlockedNext['task_reference']);

        $legacyWorkerStatus = $parser->parse([
            'callback_query' => [
                'id' => 'callback-worker-status-1',
                'data' => 'ac:worker',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 26,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('help', $legacyWorkerStatus['action']);

        $legacyCommandsSync = $parser->parse([
            'callback_query' => [
                'id' => 'callback-commands-sync-1',
                'data' => 'ac:commands-sync',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 27,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('help', $legacyCommandsSync['action']);
    }

    /**
     * Confirm the parser can normalize Telegram cancel commands and callbacks.
     *
     * @return void
     */
    public function test_it_parses_cancel_commands_and_callbacks(): void
    {
        $parser = $this->app->make(AutoCodingTelegramCommandParser::class);

        $cancelAction = $parser->parse([
            'message' => [
                'message_id' => 31,
                'text' => '/cancel 42',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('cancel_task', $cancelAction['action']);
        self::assertSame('42', $cancelAction['task_reference']);

        $cancelAllAction = $parser->parse([
            'message' => [
                'message_id' => 32,
                'text' => '/cancelall',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('cancel_tasks', $cancelAllAction['action']);
        self::assertSame('active', $cancelAllAction['scope']);

        $issueAction = $parser->parse([
            'message' => [
                'message_id' => 33,
                'text' => '/issue OPAS-0099 Fix Telegram GitHub report formatting',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('create_task', $issueAction['action']);
        self::assertSame('OPAS-0099', $issueAction['task_payload']['issue_key'] ?? null);
        self::assertSame('Fix Telegram GitHub report formatting', $issueAction['task_payload']['summary'] ?? null);

        $githubAction = $parser->parse([
            'message' => [
                'message_id' => 34,
                'text' => '/github latest',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('github_status', $githubAction['action']);
        self::assertSame('latest', $githubAction['task_reference']);

        $deleteAction = $parser->parse([
            'message' => [
                'message_id' => 35,
                'text' => '/delete 42',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('delete_task', $deleteAction['action']);
        self::assertSame('42', $deleteAction['task_reference']);

        $deleteAllAction = $parser->parse([
            'message' => [
                'message_id' => 36,
                'text' => '/deleteall',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('delete_tasks', $deleteAllAction['action']);
        self::assertSame('pending', $deleteAllAction['scope']);

        $deleteAllScopeAllAction = $parser->parse([
            'message' => [
                'message_id' => 361,
                'text' => '/deleteall --force',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('delete_tasks', $deleteAllScopeAllAction['action']);
        self::assertSame('all', $deleteAllScopeAllAction['scope']);

        $nextActionAlias = $parser->parse([
            'message' => [
                'message_id' => 362,
                'text' => '/next_action latest',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('next_action', $nextActionAlias['action']);
        self::assertSame('latest', $nextActionAlias['task_reference']);

        $followUpAlias = $parser->parse([
            'message' => [
                'message_id' => 363,
                'text' => '/follow_up latest:blocked',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('follow_up', $followUpAlias['action']);
        self::assertSame('latest:blocked', $followUpAlias['task_reference']);

        $validationReportAlias = $parser->parse([
            'message' => [
                'message_id' => 364,
                'text' => '/validation_report latest',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('validation', $validationReportAlias['action']);
        self::assertSame('latest', $validationReportAlias['task_reference']);

        $cancelAllAlias = $parser->parse([
            'message' => [
                'message_id' => 365,
                'text' => '/cancel_all',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('cancel_tasks', $cancelAllAlias['action']);

        $legacyWorkerStatusAlias = $parser->parse([
            'message' => [
                'message_id' => 366,
                'text' => '/worker_status',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('help', $legacyWorkerStatusAlias['action']);

        $cancelLatestRunning = $parser->parse([
            'callback_query' => [
                'id' => 'callback-cancel-latest-running-1',
                'data' => 'ac:cancel:latest:running',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 33,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('cancel_task', $cancelLatestRunning['action']);
        self::assertSame('latest:running', $cancelLatestRunning['task_reference']);

        $githubLatestBlocked = $parser->parse([
            'callback_query' => [
                'id' => 'callback-github-latest-1',
                'data' => 'ac:latest:github:failed',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 37,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        self::assertSame('github_status', $githubLatestBlocked['action']);
        self::assertSame('latest:failed', $githubLatestBlocked['task_reference']);

        $legacyPurgeTerminal = $parser->parse([
            'message' => [
                'message_id' => 38,
                'text' => '/purge terminal',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('help', $legacyPurgeTerminal['action']);

        $legacyPurgeForce = $parser->parse([
            'message' => [
                'message_id' => 381,
                'text' => '/purge --force',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ]);

        self::assertSame('help', $legacyPurgeForce['action']);
    }
}
