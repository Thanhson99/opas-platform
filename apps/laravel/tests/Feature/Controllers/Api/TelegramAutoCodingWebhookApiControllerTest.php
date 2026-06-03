<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Models\TelegramBotConfig;
use App\Services\AutoCoding\AutoCodingTaskDispatchService;
use App\Services\AutoCoding\LocalAutoCodingWorkerService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramChatStateService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramNotificationService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramRuntimeConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class TelegramAutoCodingWebhookApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the Telegram webhook can enqueue one remote coding task for an authorized chat.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_enqueue_one_remote_task(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 1,
                'text' => '/code Build Telegram remote coding control --issue OPAS-0072',
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

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Build Telegram remote coding control', $task->summary);
        self::assertSame('OPAS-0072', $task->issue_key);
        self::assertSame('telegram', $task->context_payload['transport_context']['source'] ?? null);
        self::assertSame(123456, $task->context_payload['transport_context']['telegram']['chat_id'] ?? null);
        self::assertSame(654321, $task->context_payload['transport_context']['telegram']['user_id'] ?? null);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && ($data['chat_id'] ?? null) === 123456
                && str_contains((string) ($data['text'] ?? ''), 'Queued task #');
        });
    }

    /**
     * Confirm the Telegram webhook can create one coding task directly from one issue key.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_create_one_task_from_issue_key(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 2,
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

        $response->assertOk();

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Fix Telegram GitHub report formatting', $task->summary);
        self::assertSame('OPAS-0099', $task->issue_key);
    }

    /**
     * Confirm action-not-allowed help responses still show the real dashboard task snapshot.
     *
     * @return void
     */
    public function test_action_not_allowed_help_still_shows_current_tasks(): void
    {
        $this->configureTelegram('vi');
        TelegramBotConfig::query()->where('key', 'default')->update([
            'allowed_actions' => [
                'help',
                'menu',
                'status',
                'queue',
                'summary',
                'reset',
            ],
        ]);
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Test Telegram remote coding',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 999,
                'text' => '/delete_all --force',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Hanh dong "delete_tasks" khong duoc phep')
                && str_contains($text, 'Tong quan hoat dong')
                && str_contains($text, 'Test Telegram remote coding');
        });
    }

    /**
     * Confirm issue-only Telegram commands can reuse the latest issue summary and context.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_enrich_one_issue_only_task_from_latest_issue_context(): void
    {
        $this->configureTelegram();
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Refactor unrelated issue flow',
            'issue_key' => 'OPAS-0107',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0107-unrelated',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Unrelated issue headline.',
                ],
            ],
        ]);

        $existingIssueTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue-only enrichment flow',
            'issue_key' => 'OPAS-0108',
            'repository_path' => '/tmp/opas-0108-repo',
            'branch_name' => 'feature/opas-0108-enrichment',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'provider_name' => 'ollama',
                'provider_options' => [
                    'model' => 'qwen2.5:14b',
                ],
                'dirty_workspace_policy' => 'allow',
                'scope_paths' => ['apps/laravel/app', 'docs'],
                'scope_policy' => 'block',
            ],
            'latest_report' => [
                'github' => [
                    'headline' => 'Existing issue work is ready for continuation.',
                    'issue' => [
                        'key' => 'OPAS-0108',
                    ],
                    'repository_slug' => 'Thanhson99/laravel-n8n-automation',
                    'branch_name' => 'feature/opas-0108-enrichment',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/108',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 2,
                'text' => '/issue OPAS-0108',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertNotSame($existingIssueTask->id, $task->id);
        self::assertSame('Fix Telegram issue-only enrichment flow', $task->summary);
        self::assertSame('/tmp/opas-0108-repo', $task->repository_path);
        self::assertSame('ollama', $task->context_payload['provider_name'] ?? null);
        self::assertSame(['model' => 'qwen2.5:14b'], $task->context_payload['provider_options'] ?? null);
        self::assertSame('allow', $task->context_payload['dirty_workspace_policy'] ?? null);
        self::assertSame(['apps/laravel/app', 'docs'], $task->context_payload['scope_paths'] ?? null);
        self::assertSame('block', $task->context_payload['scope_policy'] ?? null);
        self::assertSame($existingIssueTask->id, $task->context_payload['issue_context']['source_task_id'] ?? null);
        self::assertSame(
            'https://github.com/Thanhson99/laravel-n8n-automation/pull/108',
            $task->context_payload['issue_context']['pull_request']['url'] ?? null
        );

        Http::assertSent(function ($request) use ($existingIssueTask): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('Issue: %s', 'OPAS-0108'))
                && str_contains($text, sprintf('Source task: Reused issue context from task #%d.', $existingIssueTask->id))
                && str_contains($text, 'Provider: ollama (qwen2.5:14b)')
                && str_contains($text, 'Scope: apps/laravel/app, docs')
                && str_contains($text, 'Workspace policy: allow');
        });
    }

    /**
     * Confirm review commands with one issue key can reuse the latest issue summary and context.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_enrich_one_review_issue_task_from_latest_issue_context(): void
    {
        $this->configureTelegram();
        Http::fake();

        $existingIssueTask = AutoCodingTask::query()->create([
            'summary' => 'Review Telegram issue-linked summary reuse',
            'issue_key' => 'OPAS-0110',
            'repository_path' => '/tmp/opas-0110-review-repo',
            'branch_name' => 'feature/opas-0110-review',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'scope_paths' => ['apps/laravel/app', 'docs'],
                'scope_policy' => 'block',
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0110',
                    ],
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/110',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 47,
                'text' => '/review --issue OPAS-0110',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertNotSame($existingIssueTask->id, $task->id);
        self::assertSame('Review Telegram issue-linked summary reuse', $task->summary);
        self::assertSame('/tmp/opas-0110-review-repo', $task->repository_path);
        self::assertSame($existingIssueTask->id, $task->context_payload['issue_context']['source_task_id'] ?? null);
        self::assertSame(
            ['repository_path', 'scope_paths', 'scope_policy'],
            $task->context_payload['issue_enrichment']['reused_fields'] ?? null
        );
        self::assertNull($task->context_payload['provider_name'] ?? null);
        self::assertSame([], $task->context_payload['provider_options'] ?? null);
        self::assertSame('warn', $task->context_payload['dirty_workspace_policy'] ?? null);
    }

    /**
     * Confirm validate commands with one issue key can reuse the latest issue summary and context.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_enrich_one_validate_issue_task_from_latest_issue_context(): void
    {
        $this->configureTelegram();
        Http::fake();

        $existingIssueTask = AutoCodingTask::query()->create([
            'summary' => 'Validate Telegram issue-linked enrichment behavior',
            'issue_key' => 'OPAS-0111',
            'repository_path' => '/tmp/opas-0111-validate-repo',
            'branch_name' => 'feature/opas-0111-validate',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0111',
                    ],
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/111',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 48,
                'text' => '/validate --issue OPAS-0111',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertNotSame($existingIssueTask->id, $task->id);
        self::assertSame('Validate Telegram issue-linked enrichment behavior', $task->summary);
        self::assertSame('/tmp/opas-0111-validate-repo', $task->repository_path);
        self::assertSame($existingIssueTask->id, $task->context_payload['issue_context']['source_task_id'] ?? null);
    }

    /**
     * Confirm plain Telegram chat text can create one conversational coding task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_create_one_task_from_plain_text_chat(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 3,
                'text' => 'Fix Telegram remote progress report formatting',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Telegram remote progress report formatting', $task->summary);
        self::assertSame('conversation', $task->context_payload['transport_context']['command'] ?? null);
        self::assertSame('code', $task->context_payload['transport_context']['intent'] ?? null);
    }

    /**
     * Confirm Telegram can start one explicit direct chat session for remote coding.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_start_one_chat_session(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 53,
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

        $response->assertOk();

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $session = $chatStateService->getChatSession(123456);

        self::assertIsArray($session);
        self::assertTrue($session['enabled'] ?? false);
        self::assertSame('codex_chat', $session['mode'] ?? null);

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $text = (string) ($request->data()['text'] ?? '');
            $keyboard = $payload['reply_markup']['inline_keyboard'] ?? [];

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Connected to Codex')
                && count($keyboard) === 1
                && ($keyboard[0][0]['text'] ?? null) === 'Exit Codex'
                && ($keyboard[0][0]['callback_data'] ?? null) === 'ac:chat:stop';
        });
    }

    /**
     * Confirm connectivity-check chat messages do not create coding tasks.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_treats_connectivity_check_as_chat_status(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 54,
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
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 55,
                'text' => 'test bạn có nhận được tin nhắn từ telegram không ?',
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

        $response->assertOk();

        self::assertSame(0, AutoCodingTask::query()->count());

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Telegram bot received your message.')
                && str_contains($text, 'no Codex coding task was created')
                && ! str_contains($text, 'Recent activity');
        });
    }

    /**
     * Confirm plain conversational chat messages ask Codex for a direct reply instead of a coding task report.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_routes_plain_chat_questions_to_codex_reply_mode(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 56,
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
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 57,
                'text' => '1 + 1 bằng mấy',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('1 + 1 bằng mấy', $task->summary);
        self::assertFalse((bool) ($task->context_payload['should_run_validation'] ?? true));
        self::assertSame('telegram_direct_chat', $task->context_payload['provider_options']['mode'] ?? null);
        self::assertSame('codex_chat_reply', $task->context_payload['transport_context']['intent'] ?? null);

        Http::assertNotSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && (str_contains($text, 'Codex is preparing a reply')
                    || str_contains($text, 'Codex is replying'));
        });
    }

    /**
     * Confirm direct chat replies keep only the exit-Codex keyboard.
     *
     * @return void
     */
    public function test_direct_chat_outcome_is_sent_with_exit_codex_keyboard_only(): void
    {
        $this->configureTelegram();
        Http::fake();

        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'telegram-direct-chat-machine',
            'hostname' => 'telegram-chat-host',
            'display_name' => 'Telegram Chat Host',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
            'repository_path' => base_path('..'),
        ]);
        $task = AutoCodingTask::query()->create([
            'summary' => '1 + 1 bằng mấy',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'provider_options' => [
                    'mode' => 'telegram_direct_chat',
                ],
                'transport_context' => [
                    'source' => 'telegram',
                    'intent' => 'codex_chat_reply',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [],
        ]);
        $run = AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'repository_snapshot' => [],
            'changed_files' => [],
            'provider_result' => [
                'content' => '1 + 1 = 2',
            ],
            'validation_results' => [],
            'final_report' => [
                'provider_result' => [
                    'content' => "```bash\norigin https://example.com/repo.git",
                ],
            ],
        ]);

        $this->app->make(AutoCodingTelegramNotificationService::class)->notifyOutcome($task, $run);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $keyboard = $payload['reply_markup']['inline_keyboard'] ?? [];

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && ($payload['chat_id'] ?? null) === 123456
                && ($payload['text'] ?? null) === "```bash\norigin https://example.com/repo.git\n```"
                && count($keyboard) === 1
                && count($keyboard[0]) === 1
                && ($keyboard[0][0]['text'] ?? null) === 'Exit Codex'
                && ($keyboard[0][0]['callback_data'] ?? null) === 'ac:chat:stop';
        });
    }

    /**
     * Confirm a media-only Telegram message does not reset direct chat into the full help menu.
     *
     * @return void
     */
    public function test_media_without_caption_does_not_reset_direct_chat_to_help(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 604],
            ]),
        ]);

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 58,
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
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 59,
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

        $response->assertOk();
        self::assertSame(0, AutoCodingTask::query()->count());

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $text = (string) ($payload['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Đã nhận ảnh')
                && ! str_contains($text, 'Tổng quan trang chủ')
                && ! array_key_exists('reply_markup', $payload);
        });
    }

    /**
     * Confirm coding-like Telegram chat text inside direct chat mode is routed to direct Codex replies.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_routes_coding_like_text_to_direct_chat_when_session_is_active(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 54,
                'text' => '/chat_start',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 55,
                'text' => 'cho tôi biết trong source code đang có bao nhiêu file change mà chưa commit',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('cho tôi biết trong source code đang có bao nhiêu file change mà chưa commit', $task->summary);
        self::assertFalse((bool) ($task->context_payload['should_run_validation'] ?? true));
        self::assertSame('telegram_direct_chat', $task->context_payload['provider_options']['mode'] ?? null);
        self::assertSame('codex_chat_reply', $task->context_payload['transport_context']['intent'] ?? null);
        self::assertSame('codex_chat', $task->context_payload['transport_context']['chat_session']['mode'] ?? null);
        self::assertNotSame('', $task->context_payload['transport_context']['chat_session']['session_id'] ?? '');

        Http::assertNotSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && (str_contains($text, 'Queued task')
                    || str_contains($text, 'Task đã vào hàng chờ')
                    || str_contains($text, 'Task is running')
                    || str_contains($text, 'Task đang chạy'));
        });
    }

    /**
     * Confirm plain-text chat-session status requests do not create a task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_chat_session_status_from_plain_text(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 58,
                'text' => '/chat_start',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 59,
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

        $response->assertOk();
        self::assertSame(0, AutoCodingTask::query()->count());

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $text = (string) ($request->data()['text'] ?? '');
            $keyboard = $payload['reply_markup']['inline_keyboard'] ?? [];

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Chat session')
                && str_contains($text, 'Status: active')
                && count($keyboard) === 1
                && ($keyboard[0][0]['text'] ?? null) === 'Exit Codex';
        });
    }

    /**
     * Confirm chat-session status can show a recent queued-task timeline entry.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_recent_activity_timeline_for_chat_session(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 58,
                'text' => '/chat_start',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 59,
                'text' => '/code Build chat session timeline',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 60,
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Recent activity')
                && str_contains($text, 'Queued task #')
                && str_contains($text, 'Build chat session timeline');
        });
    }

    /**
     * Confirm explicit status commands inside one chat session prefer the linked active task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_prefers_chat_active_task_for_status_command(): void
    {
        $this->configureTelegram();
        Http::fake();

        $otherTask = AutoCodingTask::query()->create([
            'summary' => 'Latest unrelated running task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $chatTask = AutoCodingTask::query()->create([
            'summary' => 'Chat-linked task status target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $chatTask->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 60,
                'text' => '/status',
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

        $response->assertOk();
        self::assertNotSame($otherTask->id, $chatTask->id);

        Http::assertSent(function ($request) use ($chatTask): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $chatTask->id))
                && str_contains($text, 'Chat-linked task status target');
        });
    }

    /**
     * Confirm plain-text stop requests inside chat mode target the linked active task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_cancel_current_work_from_plain_text(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Stop current work from chat mode',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 61,
                'text' => 'stop current work',
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

        $response->assertOk();

        $pendingInteraction = $chatStateService->getPendingInteraction(123456);

        self::assertIsArray($pendingInteraction);
        self::assertSame('dangerous_action', $pendingInteraction['type'] ?? null);

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Confirm action')
                && str_contains($text, sprintf('#%d %s', $task->id, $task->summary));
        });
    }

    /**
     * Confirm chat-style progress questions inspect the active task linked to the chat session.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_answer_what_are_you_doing_for_chat_session(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Dang xu ly chat-style progress question',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [
                'workflow' => [
                    'current_step' => 'run_validation',
                    'current_decision_point' => [
                        'type' => 'in_progress',
                        'step' => 'run_validation',
                    ],
                ],
                'recommended_action' => [
                    'action' => 'Chạy lại validation sau khi hoàn tất chỉnh sửa hiện tại.',
                ],
            ],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 62,
                'text' => 'đang làm gì',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'Dang xu ly chat-style progress question')
                && str_contains($text, 'Giai đoạn: Chạy validation')
                && str_contains($text, 'Chạy lại validation');
        });
    }

    /**
     * Confirm chat-style next-step questions inspect the active task linked to the chat session.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_answer_what_next_for_chat_session(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Chat session next action target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [
                'recommended_action' => [
                    'action' => 'Resolve CI blockers before merge.',
                    'reason' => 'The last validation run still has one failing command.',
                ],
            ],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 64,
                'text' => 'what next',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'Resolve CI blockers before merge.')
                && str_contains($text, 'failing command');
        });
    }

    /**
     * Confirm chat-style follow-up questions inspect the active task linked to the chat session.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_answer_follow_up_questions_for_chat_session(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Chat session follow-up target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [
                'follow_up' => [
                    'required' => true,
                    'message' => 'Choose whether the worker can continue with the dirty workspace.',
                    'input_contract' => [
                        'type' => 'confirmation',
                        'accepted_values' => ['allow', 'stop'],
                    ],
                ],
            ],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 65,
                'text' => 'need anything from me',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'dirty workspace')
                && str_contains($text, 'allow, stop');
        });
    }

    /**
     * Confirm chat-style blocker questions reuse the follow-up contract for the active task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_answer_blocker_questions_for_chat_session(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Chat session blocker question target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [
                'follow_up' => [
                    'required' => true,
                    'message' => 'Worker đang chờ bạn xác nhận có thể tiếp tục với workspace hiện tại hay không.',
                    'input_contract' => [
                        'type' => 'confirmation',
                        'accepted_values' => ['allow', 'stop'],
                    ],
                ],
            ],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 66,
                'text' => 'đang kẹt gì',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'workspace hiện tại')
                && str_contains($text, 'allow, stop');
        });
    }

    /**
     * Confirm chat-style change questions can use more natural Vietnamese phrasing.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_answer_what_changed_with_natural_vietnamese_chat(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Chat session changed-files target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [],
        ]);

        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'telegram-chat-changes-machine',
            'hostname' => 'telegram-chat-host',
            'display_name' => 'Telegram Chat Host',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
            'repository_path' => base_path('..'),
        ]);

        AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'repository_snapshot' => [
                'repository_path' => base_path('..'),
                'branch_name' => 'main',
                'is_dirty' => false,
                'changed_files' => [],
                'raw_status' => [],
            ],
            'changed_files' => [
                [
                    'path' => 'apps/laravel/app/Services/AutoCoding/Telegram/AutoCodingTelegramIntentResolver.php',
                    'status' => 'modified',
                ],
                [
                    'path' => 'apps/laravel/tests/Feature/Controllers/Api/TelegramAutoCodingWebhookApiControllerTest.php',
                    'status' => 'modified',
                ],
            ],
            'provider_result' => [],
            'validation_results' => [],
            'final_report' => [],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 66,
                'text' => 'đã sửa gì rồi',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'IntentResolver.php')
                && str_contains($text, 'WebhookApiControllerTest.php');
        });
    }

    /**
     * Confirm chat-style pause requests still go through safe cancellation confirmation.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_pause_current_work_from_plain_text_chat(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Tam dung task chat mode',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 68,
                'text' => 'tạm dừng',
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

        $response->assertOk();

        $pendingInteraction = $chatStateService->getPendingInteraction(123456);

        self::assertIsArray($pendingInteraction);
        self::assertSame('dangerous_action', $pendingInteraction['type'] ?? null);

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d %s', $task->id, $task->summary));
        });
    }

    /**
     * Confirm chat-style continue requests keep focus on the linked active task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_continue_working_from_plain_text_chat(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Continue working chat task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [],
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberActiveTaskId(123456, (int) $task->id);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 63,
                'text' => 'tiếp tục đi',
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

        $response->assertOk();
        self::assertSame(1, AutoCodingTask::query()->count());

        Http::assertSent(function ($request) use ($task): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'Continue working chat task');
        });
    }

    /**
     * Confirm Telegram can stop one direct chat session cleanly.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_stop_one_chat_session(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 56,
                'text' => '/chat_start',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 57,
                'text' => '/chat_stop',
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

        $response->assertOk();

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);

        self::assertNull($chatStateService->getChatSession(123456));
    }

    /**
     * Confirm plain Telegram chat text can request the latest task status without creating a new task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_status_from_plain_text_chat(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'Inspect Telegram command routing',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 4,
                'text' => 'status',
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

        $response->assertOk();
        self::assertSame(1, AutoCodingTask::query()->count());

        Http::assertSent(function ($request) use ($task): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'Inspect Telegram command routing');
        });
    }

    /**
     * Confirm plain Telegram issue text can create one coding task with a clean summary.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_create_one_task_from_plain_text_issue_intent(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 41,
                'text' => 'issue OPAS-0101 Fix Telegram GitHub intent routing',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Fix Telegram GitHub intent routing', $task->summary);
        self::assertSame('OPAS-0101', $task->issue_key);
        self::assertSame('conversation', $task->context_payload['transport_context']['command'] ?? null);
        self::assertSame('code', $task->context_payload['transport_context']['intent'] ?? null);
    }

    /**
     * Confirm plain Telegram short issue prompts can reuse the latest issue summary.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_enrich_one_plain_text_fix_issue_prompt(): void
    {
        $this->configureTelegram();
        Http::fake();

        $existingIssueTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram short issue prompt handling',
            'issue_key' => 'OPAS-0114',
            'repository_path' => '/tmp/opas-0114-repo',
            'branch_name' => 'feature/opas-0114-short-prompt',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'provider_name' => 'ollama',
                'provider_options' => [
                    'model' => 'qwen2.5:14b',
                ],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0114',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 49,
                'text' => 'fix OPAS-0114',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertNotSame($existingIssueTask->id, $task->id);
        self::assertSame('Fix Telegram short issue prompt handling', $task->summary);
        self::assertSame('/tmp/opas-0114-repo', $task->repository_path);
        self::assertSame('conversation', $task->context_payload['transport_context']['command'] ?? null);
        self::assertSame('code', $task->context_payload['transport_context']['intent'] ?? null);
    }

    /**
     * Confirm plain Telegram short issue prompts without history fall back to issue-aware defaults.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_use_issue_aware_default_for_plain_text_fix_prompt_without_history(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 50,
                'text' => 'fix OPAS-0115',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('Review GitHub issue OPAS-0115 and implement the requested changes.', $task->summary);
        self::assertSame('OPAS-0115', $task->issue_key);
    }

    /**
     * Confirm plain Telegram fix prompts prefer code history over newer validate history for the same issue.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_prefers_same_type_issue_history_for_plain_text_fix_prompt(): void
    {
        $this->configureTelegram();
        Http::fake();

        $codeTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram same-type issue history selection',
            'issue_key' => 'OPAS-0116',
            'repository_path' => '/tmp/opas-0116-code-repo',
            'branch_name' => 'feature/opas-0116-code',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0116',
                    ],
                ],
            ],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Validation request: Validate Telegram same-type issue history selection.',
            'issue_key' => 'OPAS-0116',
            'repository_path' => '/tmp/opas-0116-validate-repo',
            'branch_name' => 'feature/opas-0116-validate',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'validate',
                ],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0116',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 51,
                'text' => 'fix OPAS-0116',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('Fix Telegram same-type issue history selection', $task->summary);
        self::assertSame('/tmp/opas-0116-code-repo', $task->repository_path);
        self::assertSame($codeTask->id, $task->context_payload['issue_context']['source_task_id'] ?? null);
    }

    /**
     * Confirm conflicting issue histories trigger one clarification prompt instead of silent reuse.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_clarify_conflicting_issue_context_candidates(): void
    {
        $this->configureTelegram();
        Http::fake();

        $appTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue context from app service history',
            'issue_key' => 'OPAS-0117',
            'repository_path' => '/tmp/opas-0117-app',
            'branch_name' => 'feature/opas-0117-app',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'scope_paths' => ['apps/laravel/app'],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0117',
                    ],
                ],
            ],
        ]);

        $docsTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue context from docs history',
            'issue_key' => 'OPAS-0117',
            'repository_path' => '/tmp/opas-0117-docs',
            'branch_name' => 'feature/opas-0117-docs',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'scope_paths' => ['docs'],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0117',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 52,
                'text' => 'fix OPAS-0117',
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

        $response->assertOk();
        self::assertSame(2, AutoCodingTask::query()->count());

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $pendingInteraction = $chatStateService->getPendingInteraction(123456);

        self::assertIsArray($pendingInteraction);
        self::assertSame('clarify_issue_context', $pendingInteraction['type'] ?? null);

        Http::assertSent(function ($request) use ($appTask, $docsTask): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');
            $keyboard = $data['reply_markup']['inline_keyboard'] ?? [];
            $callbackData = [];

            foreach ($keyboard as $row) {
                foreach ($row as $button) {
                    $callbackData[] = $button['callback_data'] ?? null;
                }
            }

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'OPAS-0117')
                && str_contains($text, sprintf('#%d', $appTask->id))
                && str_contains($text, sprintf('#%d', $docsTask->id))
                && in_array(sprintf('ac:issue-context:%d', $appTask->id), $callbackData, true)
                && in_array(sprintf('ac:issue-context:%d', $docsTask->id), $callbackData, true);
        });
    }

    /**
     * Confirm one clarification text reply can choose the intended issue-context source task.
     *
     * @return void
     */
    public function test_issue_context_clarification_text_reply_can_choose_one_source_task(): void
    {
        $this->configureTelegram();
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue context from app service history',
            'issue_key' => 'OPAS-0118',
            'repository_path' => '/tmp/opas-0118-app',
            'branch_name' => 'feature/opas-0118-app',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'scope_paths' => ['apps/laravel/app'],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0118',
                    ],
                ],
            ],
        ]);

        $docsTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue context from docs history',
            'issue_key' => 'OPAS-0118',
            'repository_path' => '/tmp/opas-0118-docs',
            'branch_name' => 'feature/opas-0118-docs',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'scope_paths' => ['docs'],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0118',
                    ],
                ],
            ],
        ]);

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 53,
                'text' => 'fix OPAS-0118',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 54,
                'text' => (string) $docsTask->id,
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('Fix Telegram issue context from docs history', $task->summary);
        self::assertSame('/tmp/opas-0118-docs', $task->repository_path);
        self::assertSame($docsTask->id, $task->context_payload['issue_context']['source_task_id'] ?? null);
        self::assertSame(['docs'], $task->context_payload['scope_paths'] ?? null);
    }

    /**
     * Confirm natural GitHub lookup text stays a report action instead of creating validation work.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_github_status_from_plain_text_lookup(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = AutoCodingTask::query()->create([
            'summary' => 'GitHub conversational lookup target',
            'issue_key' => 'OPAS-0099',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0099-github-status',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'PR is open and CI is failing.',
                    'issue' => [
                        'key' => 'OPAS-0099',
                    ],
                    'repository_slug' => 'Thanhson99/laravel-n8n-automation',
                    'branch_name' => 'feature/opas-0099-github-status',
                    'compare_url' => 'https://github.com/Thanhson99/laravel-n8n-automation/compare/main...feature/opas-0099-github-status',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/99',
                    ],
                    'ci' => [
                        'status' => 'failed',
                        'summary' => '1 failing check out of 4.',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 42,
                'text' => 'check github',
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

        $response->assertOk();
        self::assertSame(1, AutoCodingTask::query()->count());

        Http::assertSent(function ($request) use ($task): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $task->id))
                && str_contains($text, 'PR is open and CI is failing')
                && str_contains($text, 'pull/99');
        });
    }

    /**
     * Confirm plain Telegram GitHub lookup can target the latest task for one issue key.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_github_status_for_plain_text_issue_reference(): void
    {
        $this->configureTelegram();
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Newest unrelated task',
            'issue_key' => 'OPAS-0200',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0200-unrelated',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Unrelated task headline.',
                ],
            ],
        ]);

        $issueTask = AutoCodingTask::query()->create([
            'summary' => 'Issue-targeted GitHub snapshot',
            'issue_key' => 'OPAS-0102',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0102-github',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Issue-targeted PR is ready for review.',
                    'issue' => [
                        'key' => 'OPAS-0102',
                    ],
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/102',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 43,
                'text' => 'github issue OPAS-0102',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($issueTask): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $issueTask->id))
                && str_contains($text, 'Issue-targeted PR is ready for review')
                && str_contains($text, 'pull/102');
        });
    }

    /**
     * Confirm plain Telegram queue text can apply one status filter.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_filtered_queue_from_plain_text_chat(): void
    {
        $this->configureTelegram();
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Blocked queue target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [],
            'latest_report' => [],
        ]);
        AutoCodingTask::query()->create([
            'summary' => 'Running queue should stay hidden',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 44,
                'text' => 'queue blocked',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Blocked queue target')
                && ! str_contains($text, 'Running queue should stay hidden');
        });
    }

    /**
     * Confirm plain Telegram GitHub lookup can target the latest task for one branch reference.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_github_status_for_plain_text_branch_reference(): void
    {
        $this->configureTelegram();
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Different branch task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/other-branch',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Other branch headline.',
                ],
            ],
        ]);

        $branchTask = AutoCodingTask::query()->create([
            'summary' => 'Branch-targeted GitHub snapshot',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0103-branch-target',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Branch-targeted PR is waiting for CI.',
                    'branch_name' => 'feature/opas-0103-branch-target',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/103',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 45,
                'text' => 'github branch feature/opas-0103-branch-target',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($branchTask): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $branchTask->id))
                && str_contains($text, 'Branch-targeted PR is waiting for CI')
                && str_contains($text, 'pull/103');
        });
    }

    /**
     * Confirm plain Telegram GitHub lookup can target the latest task for one PR number.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_github_status_for_plain_text_pr_reference(): void
    {
        $this->configureTelegram();
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'Different PR task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/pr-104-other',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Different PR headline.',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/104',
                    ],
                ],
            ],
        ]);

        $prTask = AutoCodingTask::query()->create([
            'summary' => 'PR-targeted GitHub snapshot',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/pr-105-target',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'PR-targeted conversation lookup worked.',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/105',
                    ],
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 46,
                'text' => 'check pr 105',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($prTask): bool {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, sprintf('#%d', $prTask->id))
                && str_contains($text, 'PR-targeted conversation lookup worked')
                && str_contains($text, 'pull/105');
        });
    }

    /**
     * Confirm ambiguous Telegram chat text triggers one clarification prompt instead of creating a task.
     *
     * @return void
     */
    public function test_ambiguous_plain_text_chat_triggers_one_clarification_prompt(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 5,
                'text' => 'làm tiếp',
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

        $response->assertOk();
        self::assertSame(0, AutoCodingTask::query()->count());

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));
            $replyMarkup = is_array($data['reply_markup'] ?? null) ? $data['reply_markup'] : [];
            $rows = is_array($replyMarkup['inline_keyboard'] ?? null) ? $replyMarkup['inline_keyboard'] : [];
            $callbackData = collect($rows)
                ->flatten(1)
                ->map(static fn (mixed $button): ?string => is_array($button) && is_string($button['callback_data'] ?? null)
                    ? $button['callback_data']
                    : null)
                ->filter()
                ->values()
                ->all();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Lam ro yeu cau')
                && str_contains($text, 'lam tiep')
                && in_array('ac:clarify:code', $callbackData, true)
                && in_array('ac:clarify:review', $callbackData, true)
                && in_array('ac:clarify:cancel', $callbackData, true);
        });
    }

    /**
     * Confirm one clarification callback can convert an ambiguous request into a coding task.
     *
     * @return void
     */
    public function test_clarification_callback_can_create_one_task_from_ambiguous_plain_text(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 306],
            ]),
        ]);

        $this->app->make(AutoCodingTelegramChatStateService::class)->rememberPendingInteraction(123456, [
            'type' => 'clarify_intent',
            'original_text' => 'Fix Telegram callback regression',
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-clarify-code-1',
                'data' => 'ac:clarify:code',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 13,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Fix Telegram callback regression', $task->summary);
    }

    /**
     * Confirm one plain-text clarification reply can convert an ambiguous request into a coding task.
     *
     * @return void
     */
    public function test_clarification_text_reply_can_create_one_task_from_ambiguous_plain_text(): void
    {
        $this->configureTelegram();
        Http::fake();

        $this->app->make(AutoCodingTelegramChatStateService::class)->rememberPendingInteraction(123456, [
            'type' => 'clarify_intent',
            'original_text' => 'Fix Telegram clarification text reply regression',
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 6,
                'text' => 'code',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->first();

        self::assertNotNull($task);
        self::assertSame('Fix Telegram clarification text reply regression', $task->summary);
    }

    /**
     * Confirm the Telegram onboarding help can render in Vietnamese and include the latest tasks.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_render_vietnamese_help_with_task_context(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $dispatchService = $this->app->make(AutoCodingTaskDispatchService::class);
        $dispatchService->createPendingTaskFromPayload([
            'summary' => 'Theo doi task Telegram',
            'validate' => true,
        ]);
        AutoCodingMachine::query()->create([
            'machine_key' => 'telegram-home-machine',
            'hostname' => 'macbook-pro',
            'operating_system' => 'macos',
            'last_seen_at' => now(),
            'repository_path' => base_path('..'),
        ]);
        AutoCodingTask::query()->create([
            'summary' => 'Task blocked can xu ly',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 8,
                'text' => '/help',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));
            $replyMarkup = is_array($data['reply_markup'] ?? null) ? $data['reply_markup'] : [];
            $rows = is_array($replyMarkup['inline_keyboard'] ?? null) ? $replyMarkup['inline_keyboard'] : [];
            $callbackData = collect($rows)
                ->flatten(1)
                ->map(static fn (mixed $button): ?string => is_array($button) && is_string($button['callback_data'] ?? null)
                    ? $button['callback_data']
                    : null)
                ->filter()
                ->values()
                ->all();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'OPAS dieu khien coding tu xa')
                && str_contains($text, 'Tong quan trang chu')
                && str_contains($text, 'Worker: telegram-home-machine')
                && str_contains($text, 'Host: macbook-pro')
                && str_contains($text, 'Bat dau nhanh')
                && str_contains($text, 'Tong quan hoat dong')
                && str_contains($text, 'dang cho: 1')
                && str_contains($text, 'Can chu y')
                && str_contains($text, 'Task blocked can xu ly')
                && str_contains($text, 'Task hien tai')
                && str_contains($text, '[dang cho] Theo doi task Telegram')
                && in_array('ac:chat:start', $callbackData, true)
                && in_array('ac:queue', $callbackData, true)
                && in_array('ac:delete:latest:pending', $callbackData, true)
                && in_array('ac:delete-all', $callbackData, true)
                && in_array('ac:reset:session', $callbackData, true)
                && in_array('ac:reset:all', $callbackData, true)
                && ! in_array('ac:changes:latest', $callbackData, true)
                && ! in_array('ac:chat:status', $callbackData, true)
                && ! in_array('ac:chat:reset', $callbackData, true)
                && ! in_array('ac:menu:root', $callbackData, true);
        });
    }

    /**
     * Confirm stale report-menu callbacks return the simplified dashboard.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_return_the_simplified_dashboard_from_stale_reports_menu(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 302],
            ]),
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-menu-reports-1',
                'data' => 'ac:menu:reports',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 12,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'OPAS dieu khien coding tu xa')
                && str_contains($text, '/start')
                && str_contains($text, '/queue')
                && str_contains($text, '/clear_all');
        });

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/answerCallbackQuery'
                && str_contains($text, 'Dang mo menu');
        });
    }

    /**
     * Confirm the Telegram webhook can parse structured task flags for remote coding requests.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_parse_structured_task_flags(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 10,
                'text' => '/code Build parser optimization --issue OPAS-0072 --provider ollama --model qwen2.5:14b --scope apps/laravel/app,docs --scope-policy block --dirty-policy allow --no-validate',
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

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('Build parser optimization', $task->summary);
        self::assertSame('OPAS-0072', $task->issue_key);
        self::assertSame('ollama', $task->context_payload['provider_name'] ?? null);
        self::assertSame(['model' => 'qwen2.5:14b'], $task->context_payload['provider_options'] ?? null);
        self::assertSame(['apps/laravel/app', 'docs'], $task->context_payload['scope_paths'] ?? null);
        self::assertSame('block', $task->context_payload['scope_policy'] ?? null);
        self::assertSame('allow', $task->context_payload['dirty_workspace_policy'] ?? null);
        self::assertFalse($task->context_payload['should_run_validation'] ?? true);
    }

    /**
     * Confirm the Telegram webhook can show one GitHub status snapshot for the latest task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_show_github_status_for_one_task(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        AutoCodingTask::query()->create([
            'summary' => 'GitHub status target',
            'issue_key' => 'OPAS-0099',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0099-github-status',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0099',
                    ],
                    'repository_slug' => 'Thanhson99/laravel-n8n-automation',
                    'branch_name' => 'feature/opas-0099-github-status',
                    'compare_url' => 'https://github.com/Thanhson99/laravel-n8n-automation/compare/main...feature/opas-0099-github-status',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/99',
                        'reason' => 'Waiting for final maintainer review.',
                    ],
                    'ci' => [
                        'status' => 'failed',
                        'reason' => 'One required check is failing on the latest commit.',
                        'failed_checks' => 1,
                        'total_checks' => 5,
                    ],
                    'headline' => 'CI needs attention before merge.',
                    'blockers' => [
                        'One required check is failing on the latest commit.',
                    ],
                    'next_action' => 'Resolve the failing CI checks before merge.',
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 11,
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'GitHub cua task')
                && str_contains($text, 'OPAS-0099')
                && str_contains($text, 'Thanhson99/laravel-n8n-automation')
                && str_contains($text, 'CI needs attention before merge')
                && str_contains($text, '1/5')
                && str_contains($text, 'Resolve the failing CI checks before merge')
                && str_contains($text, 'compare/main...feature/opas-0099-github-status');
        });
    }

    /**
     * Confirm the Telegram root menu can create one validate task by callback without manual command typing.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_create_one_validate_task_from_the_root_menu(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 301],
            ]),
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-create-validate-1',
                'data' => 'ac:create:validate',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 11,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $task = AutoCodingTask::query()->latest('id')->first();

        self::assertNotNull($task);
        self::assertSame('Validation request: Validate the current repository state.', $task->summary);
        self::assertTrue($task->context_payload['should_run_validation'] ?? false);
    }

    /**
     * Confirm the Telegram reports menu can show only failed tasks when the failed filter is selected.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_filter_the_queue_to_failed_tasks(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 303],
            ]),
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Failed Telegram task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Failed,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Running Telegram task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-queue-failed-1',
                'data' => 'ac:queue:failed',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 13,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'that bai')
                && str_contains($text, 'Failed Telegram task')
                && ! str_contains($text, 'Running Telegram task');
        });
    }

    /**
     * Confirm the Telegram webhook can cancel all active tasks from one command.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_cancel_all_active_tasks(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 308],
            ]),
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Pending task to cancel',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Blocked task to cancel',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Running task request cancel',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 900,
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

        $response->assertOk();

        self::assertSame(0, AutoCodingTask::query()->where('status', AutoCodingExecutionStatus::Cancelled->value)->count());

        $confirmResponse = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-confirm-cancel-all-1',
                'data' => 'ac:confirm:yes',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 901,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $confirmResponse->assertOk();

        self::assertSame(2, AutoCodingTask::query()->where('status', AutoCodingExecutionStatus::Cancelled->value)->count());

        /** @var AutoCodingTask $runningTask */
        $runningTask = AutoCodingTask::query()
            ->where('summary', 'Running task request cancel')
            ->firstOrFail();

        self::assertSame(AutoCodingExecutionStatus::Running, $runningTask->status);
        self::assertIsArray($runningTask->context_payload);
        self::assertArrayHasKey('cancellation_requested_at', $runningTask->context_payload);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Hoan tat huy hang loat')
                && str_contains($text, 'Da huy ngay: 2')
                && str_contains($text, 'Da yeu cau huy (running): 1');
        });
    }

    /**
     * Confirm the Telegram webhook requires one explicit confirmation before cancelling a running task.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_confirm_one_running_task_cancellation(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 309],
            ]),
        ]);

        $runningTask = AutoCodingTask::query()->create([
            'summary' => 'Running task awaiting cancel confirmation',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 910,
                'text' => sprintf('/cancel %d', $runningTask->id),
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

        $response->assertOk();

        $runningTask->refresh();
        self::assertSame(AutoCodingExecutionStatus::Running, $runningTask->status);
        self::assertArrayNotHasKey('cancellation_requested_at', $runningTask->context_payload);

        $confirmResponse = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-confirm-cancel-task-1',
                'data' => 'ac:confirm:yes',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 911,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $confirmResponse->assertOk();

        $runningTask->refresh();
        self::assertSame(AutoCodingExecutionStatus::Running, $runningTask->status);
        self::assertArrayHasKey('cancellation_requested_at', $runningTask->context_payload);
    }

    /**
     * Confirm one plain-text confirmation reply can execute a dangerous action after the prompt.
     *
     * @return void
     */
    public function test_confirmation_text_reply_can_execute_one_pending_delete_action(): void
    {
        $this->configureTelegram('vi');
        Http::fake();

        $pendingTask = AutoCodingTask::query()->create([
            'summary' => 'Pending task awaiting text confirmation',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 912,
                'text' => sprintf('/delete %d', $pendingTask->id),
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

        $response->assertOk();
        self::assertTrue(AutoCodingTask::query()->whereKey($pendingTask->id)->exists());

        $confirmResponse = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 913,
                'text' => 'đồng ý',
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

        $confirmResponse->assertOk();
        self::assertFalse(AutoCodingTask::query()->whereKey($pendingTask->id)->exists());
    }

    /**
     * Confirm the Telegram webhook can permanently delete one pending task by id.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_delete_one_pending_task(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 310],
            ]),
        ]);

        $pendingTask = AutoCodingTask::query()->create([
            'summary' => 'Pending task to delete permanently',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Second pending task should remain',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 901,
                'text' => sprintf('/delete %d', $pendingTask->id),
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

        $response->assertOk();

        self::assertTrue(AutoCodingTask::query()->whereKey($pendingTask->id)->exists());
        self::assertSame(2, AutoCodingTask::query()->count());

        $confirmResponse = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-confirm-delete-task-1',
                'data' => 'ac:confirm:yes',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 903,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $confirmResponse->assertOk();

        self::assertFalse(AutoCodingTask::query()->whereKey($pendingTask->id)->exists());
        self::assertSame(1, AutoCodingTask::query()->count());

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Da xoa han task pending')
                && str_contains($text, 'Pending task to delete permanently');
        });
    }

    /**
     * Confirm the Telegram webhook can permanently delete all pending tasks.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_delete_all_pending_tasks(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 311],
            ]),
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Pending delete target one',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Pending delete target two',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Running task should remain after deleteall',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 902,
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

        $response->assertOk();

        self::assertSame(3, AutoCodingTask::query()->count());

        $confirmResponse = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-confirm-delete-all-1',
                'data' => 'ac:confirm:yes',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 904,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $confirmResponse->assertOk();

        self::assertSame(1, AutoCodingTask::query()->count());
        self::assertTrue(
            AutoCodingTask::query()->where('summary', 'Running task should remain after deleteall')->exists()
        );

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Hoan tat xoa han hang loat task pending')
                && str_contains($text, 'Task da xoa: 2');
        });
    }

    /**
     * Confirm the Telegram webhook can clear all persisted tasks when deleteall uses the explicit force scope.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_delete_all_tasks_with_force_scope(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 321],
            ]),
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Pending delete-all scope all target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Running delete-all scope all target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Running,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 905,
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

        $response->assertOk();

        self::assertSame(2, AutoCodingTask::query()->count());

        $confirmResponse = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-confirm-delete-all-scope-all-1',
                'data' => 'ac:confirm:yes',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 906,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $confirmResponse->assertOk();

        self::assertSame(0, AutoCodingTask::query()->count());

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Xoa tat ca task')
                && str_contains($text, 'Pham vi: all')
                && str_contains($text, 'Task da xoa: 2');
        });
    }

    /**
     * Confirm stale create-code callbacks return the simplified dashboard.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_return_the_simplified_dashboard_from_stale_create_code_menu(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 304],
            ]),
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-menu-create-code-1',
                'data' => 'ac:menu:create-code',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 14,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'OPAS dieu khien coding tu xa')
                && str_contains($text, '/start')
                && str_contains($text, '/changes')
                && str_contains($text, '/clear');
        });
    }

    /**
     * Confirm stale task-template callbacks return the simplified dashboard.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_return_the_simplified_dashboard_from_stale_task_templates_menu(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 3041],
            ]),
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-menu-create-templates-1',
                'data' => 'ac:menu:create-templates',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 141,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'OPAS dieu khien coding tu xa')
                && str_contains($text, '/queue')
                && str_contains($text, '/delete_all')
                && str_contains($text, '/clear_all');
        });
    }

    /**
     * Confirm the Telegram reports menu can show the latest failed-task summary by status shortcut.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_show_the_latest_failed_task_summary(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 305],
            ]),
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Failed Telegram task summary target',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Failed,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $failedTask = AutoCodingTask::query()->latest('id')->firstOrFail();
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'telegram-failed-summary-machine',
            'hostname' => 'localhost',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
            'repository_path' => base_path('..'),
        ]);

        AutoCodingTaskRun::query()->create([
            'task_id' => $failedTask->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Failed,
            'started_at' => now(),
            'completed_at' => now(),
            'repository_snapshot' => [
                'repository_path' => base_path('..'),
                'branch_name' => 'main',
                'is_dirty' => false,
                'changed_files' => [],
                'raw_status' => [],
            ],
            'changed_files' => [],
            'provider_result' => [],
            'validation_results' => [],
            'final_report' => [
                'validation' => [
                    'overall_status' => 'failed',
                ],
                'summary' => [
                    'changed_file_count' => 0,
                ],
                'failure' => [
                    'message' => 'Telegram failed summary example.',
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-latest-failed-summary-1',
                'data' => 'ac:latest:summary:failed',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 15,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'THAT BAI')
                && str_contains($text, 'Telegram failed summary example');
        });
    }

    /**
     * Confirm the Telegram reports menu can show the next action for the latest blocked task.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_show_the_next_action_for_the_latest_blocked_task(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 306],
            ]),
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Blocked Telegram task for next action',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [],
            'latest_report' => [
                'recommended_action' => [
                    'action' => 'resume_with_confirmation',
                    'reason' => 'dirty_workspace',
                ],
                'follow_up' => [
                    'required' => true,
                    'message' => 'Confirm the workspace before continuing.',
                ],
            ],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-latest-next-blocked-1',
                'data' => 'ac:latest:next:blocked',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 16,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $text = Str::ascii((string) ($data['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Buoc tiep theo')
                && str_contains($text, 'resume_with_confirmation')
                && str_contains($text, 'dirty_workspace');
        });
    }

    /**
     * Confirm the Telegram task keyboard can show validation summary for one task.
     *
     * @return void
     */
    public function test_authorized_telegram_callback_can_show_validation_for_one_task(): void
    {
        $this->configureTelegram('vi');
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 307],
            ]),
        ]);

        $task = AutoCodingTask::query()->create([
            'summary' => 'Validation target task',
            'issue_key' => null,
            'repository_path' => base_path('..'),
            'branch_name' => 'main',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [],
        ]);

        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'telegram-validation-machine',
            'hostname' => 'localhost',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
            'repository_path' => base_path('..'),
        ]);

        AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'repository_snapshot' => [
                'repository_path' => base_path('..'),
                'branch_name' => 'main',
                'is_dirty' => false,
                'changed_files' => [],
                'raw_status' => [],
            ],
            'changed_files' => [],
            'provider_result' => [],
            'validation_results' => [
                'overall_status' => 'passed',
                'total_commands' => 3,
                'failed_commands' => 0,
                'summary' => 'All validation commands passed.',
            ],
            'final_report' => [],
        ]);

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-validation-task-1',
                'data' => sprintf('ac:validation:%d', $task->id),
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 17,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            $text = Str::ascii((string) ($request->data()['text'] ?? ''));

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'Validation cua task')
                && str_contains($text, 'All validation commands passed');
        });
    }

    /**
     * Confirm unauthorized Telegram chats receive an allow-list hint without creating any task.
     *
     * @return void
     */
    public function test_unauthorized_telegram_chat_is_ignored(): void
    {
        $this->configureTelegram();
        Http::fake();

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 1,
                'text' => '/status latest',
                'chat' => [
                    'id' => 999999,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 888888,
                    'username' => 'intruder',
                ],
            ],
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
        ]);

        self::assertSame(0, AutoCodingTask::query()->count());
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && ($payload['chat_id'] ?? null) === 999999
                && str_contains((string) ($payload['text'] ?? ''), 'Chat ID: 999999')
                && str_contains((string) ($payload['text'] ?? ''), 'User ID: 888888');
        });
    }

    /**
     * Confirm the Telegram webhook can return the latest task status to an authorized requester.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_return_latest_task_status(): void
    {
        $this->configureTelegram();
        Http::fake();

        $dispatchService = $this->app->make(AutoCodingTaskDispatchService::class);
        $task = $dispatchService->createPendingTaskFromPayload([
            'summary' => 'Inspect Telegram status command',
            'validate' => true,
            'context_metadata' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 2,
                'text' => '/status latest',
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

        $response->assertOk();

        Http::assertSent(function ($request) use ($task): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains((string) ($data['text'] ?? ''), sprintf('Task #%d', $task->getKey()))
                && str_contains((string) ($data['text'] ?? ''), 'Status: pending');
        });
    }

    /**
     * Confirm worker execution sends Telegram running and completion updates for Telegram-origin tasks.
     *
     * @return void
     */
    public function test_worker_execution_sends_telegram_progress_updates(): void
    {
        $this->configureTelegram();
        Http::fake();

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        $chatStateService->startChatSession(123456);

        $dispatchService = $this->app->make(AutoCodingTaskDispatchService::class);
        $dispatchService->createPendingTaskFromPayload([
            'summary' => 'Run Telegram worker notification flow',
            'validate' => false,
            'context_metadata' => [
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
        ]);

        $workerService = $this->app->make(LocalAutoCodingWorkerService::class);
        $workerService->runCycle(base_path('..'), true);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains((string) ($data['text'] ?? ''), 'Running task #')
                && str_contains((string) ($data['text'] ?? ''), 'Phase: Execution started')
                && str_contains((string) ($data['text'] ?? ''), 'Focus: The connected machine has claimed the task');
        });

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains((string) ($data['text'] ?? ''), 'COMPLETED')
                && str_contains((string) ($data['text'] ?? ''), 'Summary: Run Telegram worker notification flow')
                && str_contains((string) ($data['text'] ?? ''), 'Validation: skipped');
        });

        $session = $chatStateService->getChatSession(123456);

        self::assertIsArray($session);
        self::assertIsArray($session['recent_events'] ?? null);
        self::assertCount(2, $session['recent_events']);
        self::assertSame('running', $session['recent_events'][0]['type'] ?? null);
        self::assertSame('completed', $session['recent_events'][1]['type'] ?? null);
    }

    /**
     * Confirm the Telegram reset command can clear tracked bot messages and leave one fresh root message.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_reset_one_chat(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 101]])
                ->push(['ok' => true, 'result' => ['message_id' => 102]])
                ->push(['ok' => true, 'result' => ['message_id' => 103]]),
            'https://api.telegram.org/bottest-token/deleteMessage' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 1,
                'text' => '/queue',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 2,
                'text' => '/status latest',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 3,
                'text' => '/reset',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 102;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 2;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 101;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 1;
        });

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains((string) ($data['text'] ?? ''), 'Chat cleaned.');
        });

        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);
        self::assertSame([103], $chatStateService->getTrackedMessageIds(123456));
    }

    /**
     * Confirm the clear command targets only tracked messages from the current chat session.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_clear_current_chat_session_only(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 903],
            ]),
            'https://api.telegram.org/bottest-token/deleteMessage' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        /** @var AutoCodingTelegramChatStateService $chatStateService */
        $chatStateService = $this->app->make(AutoCodingTelegramChatStateService::class);

        $this->travelTo(now()->subDay());
        $chatStateService->rememberBotMessage(123456, 901);

        $this->travelTo(now()->addDay());
        $chatStateService->startChatSession(123456);
        $chatStateService->rememberBotMessage(123456, 902);

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 3,
                'text' => '/clear',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 902;
        });

        Http::assertNotSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 3;
        });

        Http::assertNotSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 901;
        });

        self::assertSame([901, 903], $chatStateService->getTrackedMessageIds(123456));
    }

    /**
     * Confirm force-reset also tries to delete the incoming trigger message.
     *
     * @return void
     */
    public function test_authorized_telegram_webhook_can_force_reset_one_chat(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 301]])
                ->push(['ok' => true, 'result' => ['message_id' => 302]]),
            'https://api.telegram.org/bottest-token/deleteMessage' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 1,
                'text' => '/queue',
                'chat' => [
                    'id' => 123456,
                    'type' => 'supergroup',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 3,
                'text' => '/reset --force',
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

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 301;
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 3;
        });

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains((string) ($data['text'] ?? ''), 'Force cleanup was requested');
        });
    }

    /**
     * Confirm callback-driven reset can also remove the source bot message that triggered the cleanup.
     *
     * @return void
     */
    public function test_callback_reset_can_delete_the_source_message(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['message_id' => 201]])
                ->push(['ok' => true, 'result' => ['message_id' => 202]]),
            'https://api.telegram.org/bottest-token/deleteMessage' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->postJsonWithTelegramSecret([
            'message' => [
                'message_id' => 1,
                'text' => '/queue',
                'chat' => [
                    'id' => 123456,
                    'type' => 'private',
                ],
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
            ],
        ])->assertOk();

        $response = $this->postJsonWithTelegramSecret([
            'callback_query' => [
                'id' => 'callback-reset-1',
                'data' => 'ac:reset',
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 201,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteMessage'
                && ($request->data()['message_id'] ?? null) === 201;
        });
    }

    /**
     * Configure the Telegram bot settings used by webhook tests.
     *
     * @return void
     */
    protected function configureTelegram(string $locale = 'en'): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.provider', 'null');
        TelegramBotConfig::query()->updateOrCreate([
            'key' => 'default',
        ], [
            'display_name' => 'Default Telegram Bot',
            'enabled' => true,
            'is_default' => true,
            'locale' => $locale,
            'api_base_url' => 'https://api.telegram.org',
            'allowed_chat_ids' => ['123456'],
            'allowed_user_ids' => ['654321'],
            'allowed_actions' => [
                'help',
                'menu',
                'chat_start',
                'chat_status',
                'chat_stop',
                'chat_reset',
                'create_task',
                'status',
                'validation',
                'github_status',
                'next_action',
                'follow_up',
                'queue',
                'changes',
                'summary',
                'clarify_intent',
                'clarify_issue_context',
                'cancel_task',
                'cancel_tasks',
                'confirm_pending',
                'delete_task',
                'delete_tasks',
                'reset',
                'resume',
            ],
            'public_config' => [
                'allowed_updates' => ['message', 'callback_query'],
                'chat_history_limit' => 30,
                'chat_session_timeline_limit' => 6,
            ],
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'telegram-secret',
            ],
        ]);

        $this->app->make(AutoCodingTelegramRuntimeConfigService::class)->forgetCachedRuntimeConfig();
    }

    /**
     * Post one Telegram webhook payload with the configured secret header.
     *
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Testing\TestResponse
     */
    protected function postJsonWithTelegramSecret(array $payload)
    {
        return $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', $payload);
    }
}
