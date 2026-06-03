<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\TelegramBotConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoCodingTelegramWebhookCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm the webhook command can inspect the configured Telegram webhook state.
     *
     * @return void
     */
    public function test_it_can_show_the_current_telegram_webhook_info(): void
    {
        $this->createDefaultTelegramBot();

        Http::fake([
            'https://api.telegram.org/bottest-token/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://example.com/api/telegram/auto-coding/webhook',
                    'pending_update_count' => 0,
                ],
            ]),
        ]);

        $this->artisan('opas:auto-coding:telegram:webhook')
            ->assertExitCode(0);

        Http::assertSentCount(1);
    }

    /**
     * Confirm the webhook command can register one Telegram webhook with the configured secret.
     *
     * @return void
     */
    public function test_it_can_register_the_telegram_webhook(): void
    {
        $this->createDefaultTelegramBot();

        Http::fake([
            'https://api.telegram.org/bottest-token/setWebhook' => Http::response([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was set',
            ]),
        ]);

        $this->artisan('opas:auto-coding:telegram:webhook', [
            'url' => 'https://example.com/api/telegram/auto-coding/webhook',
            '--drop-pending-updates' => true,
        ])->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $allowedUpdates = $data['allowed_updates'] ?? null;

            return $request->url() === 'https://api.telegram.org/bottest-token/setWebhook'
                && ($data['url'] ?? null) === 'https://example.com/api/telegram/auto-coding/webhook'
                && ($data['secret_token'] ?? null) === 'config-secret'
                && ($data['drop_pending_updates'] ?? null) === true
                && $allowedUpdates === ['message', 'callback_query'];
        });
    }

    /**
     * Confirm the webhook-delete command can remove the Telegram webhook registration.
     *
     * @return void
     */
    public function test_it_can_delete_the_telegram_webhook(): void
    {
        $this->createDefaultTelegramBot();

        Http::fake([
            'https://api.telegram.org/bottest-token/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->artisan('opas:auto-coding:telegram:webhook-delete', [
            '--drop-pending-updates' => true,
        ])->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/deleteWebhook'
                && ($data['drop_pending_updates'] ?? null) === true;
        });
    }

    /**
     * Confirm the command-sync command can register the default Telegram bot commands.
     *
     * @return void
     */
    public function test_it_can_sync_the_default_telegram_bot_commands(): void
    {
        $this->createDefaultTelegramBot();

        Http::fake([
            'https://api.telegram.org/bottest-token/setMyCommands' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $this->artisan('opas:auto-coding:telegram:commands-sync')
            ->assertExitCode(0);

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $commands = $data['commands'] ?? null;
            $commandNames = is_array($commands)
                ? array_values(array_filter(array_map(
                    static fn (mixed $command): ?string => is_array($command) && is_string($command['command'] ?? null)
                        ? $command['command']
                        : null,
                    $commands
                )))
                : [];

            return $request->url() === 'https://api.telegram.org/bottest-token/setMyCommands'
                && is_array($commands)
                && count($commands) >= 5
                && in_array('start', $commandNames, true)
                && in_array('stop', $commandNames, true)
                && ! in_array('chat_start', $commandNames, true)
                && in_array('queue', $commandNames, true)
                && in_array('changes', $commandNames, true)
                && in_array('clear', $commandNames, true)
                && in_array('clear_all', $commandNames, true)
                && in_array('delete_all', $commandNames, true)
                && ! in_array('sync_commands', $commandNames, true)
                && ! in_array('next_action', $commandNames, true);
        });
    }

    /**
     * @return void
     */
    private function createDefaultTelegramBot(): void
    {
        TelegramBotConfig::query()->create([
            'key' => 'default',
            'display_name' => 'Default Telegram Bot',
            'enabled' => true,
            'is_default' => true,
            'locale' => 'en',
            'api_base_url' => 'https://api.telegram.org',
            'allowed_chat_ids' => ['123456'],
            'allowed_user_ids' => ['654321'],
            'allowed_actions' => ['status', 'summary', 'changes'],
            'public_config' => [
                'allowed_updates' => ['message', 'callback_query'],
                'chat_history_limit' => 30,
                'chat_session_timeline_limit' => 6,
            ],
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'config-secret',
            ],
        ]);
    }
}
