<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Models\TelegramBotConfig;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramChatStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramAutoCodingWebhookFollowUpApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm Telegram callback resume buttons can continue confirmation-style blocked tasks.
     *
     * @return void
     */
    public function test_confirmation_callback_can_resume_one_blocked_task(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'dirty_workspace',
            'message' => 'Confirm the dirty workspace before continuing.',
            'input_contract' => [
                'type' => 'confirmation',
                'accepted_values' => ['allow', 'continue'],
                'free_text_allowed' => false,
                'resume_token' => 'task:1:run:1:blocked',
            ],
            'question_contracts' => [],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'callback_query' => [
                'id' => 'callback-1',
                'data' => sprintf('ac:resume:%d:allow', $task->id),
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 1,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        self::assertSame('completed', $task->fresh()->status->value);
    }

    /**
     * Confirm Telegram can resume the latest blocked task without requiring the task id in the callback payload.
     *
     * @return void
     */
    public function test_latest_blocked_resume_callback_can_continue_one_confirmation_task(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'dirty_workspace',
            'message' => 'Confirm the dirty workspace before continuing.',
            'input_contract' => [
                'type' => 'confirmation',
                'accepted_values' => ['allow', 'continue'],
                'free_text_allowed' => false,
                'resume_token' => 'task:1:run:1:blocked',
            ],
            'question_contracts' => [],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'callback_query' => [
                'id' => 'callback-latest-blocked-1',
                'data' => 'ac:resume-latest:blocked:allow',
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
        self::assertSame('completed', $task->fresh()->status->value);
    }

    /**
     * Confirm Telegram option callbacks can submit structured follow-up answers for one blocked task.
     *
     * @return void
     */
    public function test_question_option_callback_can_resume_one_blocked_task(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'provider_follow_up',
            'message' => 'Choose the scope you want to continue with.',
            'input_contract' => [
                'type' => 'free_text',
                'resume_token' => 'task:1:run:1:blocked',
            ],
            'question_contracts' => [
                [
                    'id' => 'target_scope',
                    'prompt' => 'Which scope should continue?',
                    'input_type' => 'text',
                    'required' => true,
                    'accepted_values' => ['services', 'docs'],
                    'options' => [
                        ['label' => 'Services', 'value' => 'services'],
                        ['label' => 'Docs', 'value' => 'docs'],
                    ],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'callback_query' => [
                'id' => 'callback-2',
                'data' => sprintf('ac:ra:%d:0:services', $task->id),
                'from' => [
                    'id' => 654321,
                    'username' => 'opas_admin',
                ],
                'message' => [
                    'message_id' => 2,
                    'chat' => [
                        'id' => 123456,
                        'type' => 'private',
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $freshTask = $task->fresh();
        self::assertSame('completed', $freshTask->status->value);
        self::assertSame(
            'services',
            $freshTask->context_payload['follow_up_answers'][0]['response_payload']['answers'][0]['value'] ?? null
        );
    }

    /**
     * Confirm Telegram text resume commands can submit multiple structured follow-up answers.
     *
     * @return void
     */
    public function test_text_resume_can_submit_multiple_structured_follow_up_answers(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'provider_follow_up',
            'message' => 'Provide the target scope and target file.',
            'input_contract' => [
                'type' => 'free_text',
                'resume_token' => 'task:1:run:1:blocked',
            ],
            'question_contracts' => [
                [
                    'id' => 'target_scope',
                    'prompt' => 'Which scope should continue?',
                    'input_type' => 'text',
                    'required' => true,
                    'accepted_values' => ['services', 'docs'],
                    'options' => [],
                ],
                [
                    'id' => 'target_file',
                    'prompt' => 'Which file should be prioritized?',
                    'input_type' => 'text',
                    'required' => true,
                    'accepted_values' => [],
                    'options' => [],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 3,
                'text' => sprintf(
                    '/resume %d target_scope=services; target_file=apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                    $task->id
                ),
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

        $freshTask = $task->fresh();
        self::assertSame('completed', $freshTask->status->value);
        self::assertCount(2, $freshTask->context_payload['follow_up_answers'][0]['response_payload']['answers'] ?? []);
        self::assertSame(
            'target_scope',
            $freshTask->context_payload['follow_up_answers'][0]['response_payload']['answers'][0]['question_id'] ?? null
        );
        self::assertSame(
            'target_file',
            $freshTask->context_payload['follow_up_answers'][0]['response_payload']['answers'][1]['question_id'] ?? null
        );
    }

    /**
     * Confirm plain Telegram chat replies can resume the active blocked task remembered for one chat.
     *
     * @return void
     */
    public function test_plain_text_chat_can_resume_the_active_blocked_task(): void
    {
        $this->configureTelegram();
        Http::fake();

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'dirty_workspace',
            'message' => 'Confirm the dirty workspace before continuing.',
            'input_contract' => [
                'type' => 'confirmation',
                'accepted_values' => ['allow', 'continue'],
                'free_text_allowed' => false,
                'resume_token' => 'task:1:run:1:blocked',
            ],
            'question_contracts' => [],
        ]);

        $this->app->make(AutoCodingTelegramChatStateService::class)->rememberActiveTaskId(123456, (int) $task->getKey());

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 31,
                'text' => 'allow',
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
        self::assertSame('completed', $task->fresh()->status->value);
    }

    /**
     * Confirm Telegram can show the follow-up contract for the latest blocked task without task-id lookup.
     *
     * @return void
     */
    public function test_latest_blocked_follow_up_callback_can_show_the_current_contract(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/answerCallbackQuery' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 401],
            ]),
        ]);

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'provider_follow_up',
            'message' => 'Choose the target file before continuing.',
            'input_contract' => [
                'type' => 'free_text',
                'resume_token' => 'task:1:run:1:blocked',
            ],
            'question_contracts' => [
                [
                    'id' => 'target_file',
                    'prompt' => 'Which file should be edited first?',
                    'input_type' => 'text',
                    'required' => true,
                    'accepted_values' => [],
                    'options' => [],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'callback_query' => [
                'id' => 'callback-latest-followup-1',
                'data' => 'ac:latest:followup:blocked',
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
        self::assertSame('blocked', $task->fresh()->status->value);

        Http::assertSent(function ($request): bool {
            $text = (string) ($request->data()['text'] ?? '');

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && str_contains($text, 'target_file')
                && str_contains($text, 'Which file should be edited first?');
        });
    }

    /**
     * Confirm Telegram follow-up rendering includes accepted values for confirmation-style contracts.
     *
     * @return void
     */
    public function test_follow_up_rendering_can_show_confirmation_accepted_values(): void
    {
        $this->configureTelegram();
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 402],
            ]),
        ]);

        $task = $this->createBlockedTask([
            'required' => true,
            'reason' => 'dirty_workspace',
            'message' => 'Confirm the workspace before continuing.',
            'input_contract' => [
                'type' => 'confirmation',
                'resume_token' => 'task:1:run:1:blocked',
                'accepted_values' => ['allow', 'continue'],
            ],
            'question_contracts' => [
                [
                    'id' => 'workspace_confirmation',
                    'prompt' => 'Proceed on dirty workspace?',
                    'input_type' => 'confirmation',
                    'required' => true,
                    'accepted_values' => ['allow', 'continue'],
                    'options' => [],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ])->postJson('/api/telegram/auto-coding/webhook', [
            'message' => [
                'message_id' => 13,
                'text' => sprintf('/followup %d', $task->id),
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
                && str_contains($text, 'allow, continue')
                && str_contains($text, 'Proceed on dirty workspace?');
        });
    }

    /**
     * Create one minimal blocked task and run pair for Telegram follow-up callbacks.
     *
     * @param  array<string, mixed>  $followUp
     * @return AutoCodingTask
     */
    protected function createBlockedTask(array $followUp): AutoCodingTask
    {
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'telegram-test-machine',
            'hostname' => 'localhost',
            'operating_system' => 'macos',
            'status' => 'online',
            'last_seen_at' => now(),
            'repository_path' => base_path('..'),
        ]);

        $task = AutoCodingTask::query()->create([
            'summary' => 'Blocked Telegram follow-up task',
            'issue_key' => 'OPAS-0072',
            'repository_path' => base_path('..'),
            'status' => AutoCodingExecutionStatus::Blocked,
            'context_payload' => [
                'repository_path' => base_path('..'),
                'should_run_validation' => false,
                'provider_name' => null,
                'provider_options' => [],
                'dirty_workspace_policy' => 'warn',
                'scope_paths' => [],
                'scope_policy' => 'warn',
                'follow_up_answers' => [],
                'transport_context' => [
                    'source' => 'telegram',
                    'telegram' => [
                        'chat_id' => 123456,
                        'user_id' => 654321,
                    ],
                ],
            ],
            'latest_report' => [
                'follow_up' => $followUp,
            ],
        ]);

        $run = AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Blocked,
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
            'final_report' => [],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $followUp['input_contract']['resume_token'] = sprintf('task:%d:run:%d:blocked', $task->id, $run->id);
        $task->update([
            'latest_report' => [
                'follow_up' => $followUp,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Configure the Telegram bot settings used by blocked-task callback tests.
     *
     * @return void
     */
    protected function configureTelegram(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));
        config()->set('opas.auto_coding.provider', 'null');
        TelegramBotConfig::query()->updateOrCreate([
            'key' => 'default',
        ], [
            'display_name' => 'Default Telegram Bot',
            'enabled' => true,
            'is_default' => true,
            'locale' => 'en',
            'api_base_url' => 'https://api.telegram.org',
            'allowed_chat_ids' => ['123456'],
            'allowed_user_ids' => ['654321'],
            'allowed_actions' => [
                'help',
                'menu',
                'create_task',
                'status',
                'validation',
                'next_action',
                'follow_up',
                'queue',
                'changes',
                'summary',
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
    }
}
