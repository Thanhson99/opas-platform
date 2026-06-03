<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\TelegramBotConfig;
use App\Models\User;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramBotConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verify the admin-facing Telegram bot configuration API.
 */
class AdminTelegramBotConfigApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(AutoCodingTelegramBotConfigService::class)->ensureDefaultBotExists();
    }

    /**
     * Admins should see the Telegram bot list managed from the database.
     */
    public function test_admin_can_list_telegram_bot_configs(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.auto-coding.telegram-bots.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.key', 'default');
    }

    /**
     * Secrets should be persisted safely without being returned in plaintext to the SPA.
     */
    public function test_admin_can_create_telegram_bot_without_exposing_secrets(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.store'), [
                'key' => 'demo-bot',
                'display_name' => 'Demo Telegram Bot',
                'purpose' => 'support',
                'environment' => 'staging',
                'machine_group' => 'support-team',
                'enabled' => true,
                'locale' => 'vi',
                'allowed_chat_ids' => ['123456789'],
                'allowed_actions' => ['status', 'summary', 'chat_reset'],
                'public_config' => [
                    'allowed_updates' => ['message', 'callback_query'],
                    'chat_history_limit' => 40,
                    'chat_session_timeline_limit' => 8,
                ],
                'secret_config' => [
                    'bot_token' => 'demo-token',
                    'webhook_secret' => 'demo-secret',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'demo-bot')
            ->assertJsonPath('data.purpose', 'support')
            ->assertJsonPath('data.environment', 'staging')
            ->assertJsonPath('data.machine_group', 'support-team')
            ->assertJsonPath('data.secret_status.bot_token', true)
            ->assertJsonPath('data.secret_status.webhook_secret', true)
            ->assertJsonMissingPath('data.secret_config');

        $config = TelegramBotConfig::query()->where('key', 'demo-bot')->firstOrFail();

        $this->assertSame('demo-token', $config->secret_config['bot_token']);
        $this->assertSame('demo-secret', $config->secret_config['webhook_secret']);
    }

    /**
     * Runtime bot promotion should keep exactly one default bot after an admin update.
     */
    public function test_admin_can_promote_one_bot_to_default(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        TelegramBotConfig::query()->create([
            'key' => 'secondary-bot',
            'display_name' => 'Secondary Bot',
            'enabled' => false,
            'is_default' => false,
            'locale' => 'en',
            'allowed_chat_ids' => [],
            'allowed_user_ids' => [],
            'allowed_actions' => ['status'],
            'public_config' => [
                'allowed_updates' => ['message', 'callback_query'],
                'chat_history_limit' => 30,
                'chat_session_timeline_limit' => 6,
            ],
            'secret_config' => [],
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(route('api.admin.auto-coding.telegram-bots.update', ['key' => 'secondary-bot']), [
                'is_default' => true,
                'display_name' => 'Secondary Bot',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.key', 'secondary-bot')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('telegram_bot_configs', [
            'key' => 'secondary-bot',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('telegram_bot_configs', [
            'key' => 'default',
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('telegram_bot_config_audits', [
            'telegram_bot_config_id' => TelegramBotConfig::query()->where('key', 'secondary-bot')->value('id'),
            'action' => 'config_updated',
            'actor_user_id' => $admin->id,
        ]);
    }

    /**
     * Enabling one bot should immediately send the command menu to configured chat IDs.
     */
    public function test_admin_enable_sends_startup_menu_to_allowed_chats(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);
        Http::fake([
            'https://api.telegram.org/bottest-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 711],
            ]),
        ]);

        TelegramBotConfig::query()->create([
            'key' => 'group-bot',
            'display_name' => 'Group Bot',
            'enabled' => false,
            'is_default' => false,
            'locale' => 'vi',
            'allowed_chat_ids' => ['-5215826994'],
            'allowed_user_ids' => [],
            'allowed_actions' => ['help', 'menu', 'chat_start', 'queue', 'reset'],
            'public_config' => [
                'allowed_updates' => ['message', 'callback_query'],
                'chat_history_limit' => 30,
                'chat_session_timeline_limit' => 6,
            ],
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'secret',
            ],
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(route('api.admin.auto-coding.telegram-bots.update', ['key' => 'group-bot']), [
                'display_name' => 'Group Bot',
                'enabled' => true,
                'is_default' => true,
                'allowed_chat_ids' => ['-5215826994'],
                'allowed_actions' => ['help', 'menu', 'chat_start', 'queue', 'reset'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.is_default', true);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && ($data['chat_id'] ?? null) === '-5215826994'
                && str_contains((string) ($data['text'] ?? ''), 'OPAS')
                && is_array($data['reply_markup'] ?? null);
        });
    }

    /**
     * Admins should be able to remove a non-default Telegram bot config.
     */
    public function test_admin_can_delete_a_non_default_telegram_bot_config(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        TelegramBotConfig::query()->create([
            'key' => 'archived-bot',
            'display_name' => 'Archived Bot',
            'enabled' => false,
            'is_default' => false,
            'locale' => 'en',
            'allowed_chat_ids' => [],
            'allowed_user_ids' => [],
            'allowed_actions' => ['status'],
            'public_config' => [
                'allowed_updates' => ['message', 'callback_query'],
                'chat_history_limit' => 30,
                'chat_session_timeline_limit' => 6,
            ],
            'secret_config' => [],
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(route('api.admin.auto-coding.telegram-bots.destroy', ['key' => 'archived-bot']));

        $response->assertOk()
            ->assertJsonPath('message', 'Telegram bot deleted successfully.');

        $this->assertDatabaseMissing('telegram_bot_configs', [
            'key' => 'archived-bot',
        ]);
    }

    /**
     * Admins should be able to remove the last default Telegram bot config.
     */
    public function test_admin_can_delete_the_last_default_telegram_bot_config(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(route('api.admin.auto-coding.telegram-bots.destroy', ['key' => 'default']));

        $response->assertOk()
            ->assertJsonPath('message', 'Telegram bot deleted successfully.');

        $this->assertDatabaseMissing('telegram_bot_configs', [
            'key' => 'default',
        ]);
    }

    /**
     * Admins can inspect a raw bot token to discover recent Telegram chat and user IDs.
     *
     * @return void
     */
    public function test_admin_can_inspect_chat_ids_from_bot_token(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 10,
                        'message' => [
                            'message_id' => 1,
                            'from' => [
                                'id' => 123456789,
                                'first_name' => 'Hope',
                                'username' => 'hope_admin',
                            ],
                            'chat' => [
                                'id' => 123456789,
                                'type' => 'private',
                                'first_name' => 'Hope',
                                'username' => 'hope_admin',
                            ],
                        ],
                    ],
                    [
                        'update_id' => 11,
                        'message' => [
                            'message_id' => 2,
                            'from' => [
                                'id' => 987654321,
                                'first_name' => 'Operator',
                            ],
                            'chat' => [
                                'id' => -1001234567890,
                                'type' => 'supergroup',
                                'title' => 'OPAS Ops',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.inspect-chat-ids'), [
                'bot_token' => '123:abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.chats.0.id', '123456789')
            ->assertJsonPath('data.chats.1.id', '-1001234567890')
            ->assertJsonPath('data.chats.1.label', 'OPAS Ops')
            ->assertJsonPath('data.users.0.id', '123456789')
            ->assertJsonPath('data.users.1.id', '987654321');
    }

    /**
     * Telegram blocks getUpdates while a webhook is active, so the API should expose that state.
     *
     * @return void
     */
    public function test_admin_chat_id_inspection_reports_active_webhook_conflict(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Http::fake([
            '*' => Http::response([
                'ok' => false,
                'error_code' => 409,
                'description' => "Conflict: can't use getUpdates method while webhook is active; use deleteWebhook to delete the webhook first",
            ], 409),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.inspect-chat-ids'), [
                'bot_token' => '123:abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ok', false)
            ->assertJsonPath('data.error_code', 409)
            ->assertJsonPath('data.needs_webhook_delete', true)
            ->assertJsonPath('data.chats', [])
            ->assertJsonPath('data.users', []);
    }

    /**
     * Admins can explicitly delete a webhook for the raw token before reading getUpdates.
     *
     * @return void
     */
    public function test_admin_can_delete_webhook_before_inspecting_chat_ids(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        Http::fakeSequence()
            ->push([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was deleted',
            ])
            ->push([
                'ok' => true,
                'result' => [
                    [
                        'update_id' => 12,
                        'message' => [
                            'message_id' => 3,
                            'from' => [
                                'id' => 987654321,
                                'first_name' => 'Operator',
                            ],
                            'chat' => [
                                'id' => -1001234567890,
                                'type' => 'supergroup',
                                'title' => 'OPAS Ops',
                            ],
                        ],
                    ],
                ],
            ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.inspect-chat-ids'), [
                'bot_token' => '123:abc',
                'delete_webhook' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.needs_webhook_delete', false)
            ->assertJsonPath('data.webhook_deleted.ok', true)
            ->assertJsonPath('data.chats.0.id', '-1001234567890')
            ->assertJsonPath('data.users.0.id', '987654321');
    }

    /**
     * Listing bots after deletion must not recreate the environment default bot.
     *
     * @return void
     */
    public function test_admin_bot_list_does_not_recreate_deleted_default_bot(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this
            ->actingAs($admin)
            ->deleteJson(route('api.admin.auto-coding.telegram-bots.destroy', ['key' => 'default']))
            ->assertOk();

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.auto-coding.telegram-bots.index'));

        $response->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('telegram_bot_configs', [
            'key' => 'default',
        ]);
    }

    /**
     * Enabled bots must reject missing tokens even when the request shape is otherwise valid.
     */
    public function test_admin_cannot_enable_telegram_bot_without_a_token(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.store'), [
                'key' => 'invalid-bot',
                'display_name' => 'Invalid Bot',
                'enabled' => true,
                'locale' => 'en',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['secret_config.bot_token']);
    }

    /**
     * Enabled bots must reject missing allowed operators even when a token is present.
     *
     * @return void
     */
    public function test_admin_cannot_enable_telegram_bot_without_allowed_operator(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.store'), [
                'key' => 'missing-access-bot',
                'display_name' => 'Missing Access Bot',
                'enabled' => true,
                'locale' => 'en',
                'secret_config' => [
                    'bot_token' => 'demo-token',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allowed_chat_ids']);
    }

    /**
     * Non-admin operators must not manage Telegram bot configuration.
     */
    public function test_non_admin_cannot_manage_telegram_bot_configs(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $listResponse = $this
            ->actingAs($member)
            ->getJson(route('api.admin.auto-coding.telegram-bots.index'));

        $storeResponse = $this
            ->actingAs($member)
            ->postJson(route('api.admin.auto-coding.telegram-bots.store'), [
                'key' => 'blocked-bot',
                'display_name' => 'Blocked Bot',
            ]);

        $listResponse->assertForbidden();
        $storeResponse->assertForbidden();
    }

    /**
     * Admins should be able to inspect the active runtime Telegram bot without seeing plaintext secrets.
     */
    public function test_admin_can_inspect_the_current_telegram_runtime_state(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->update([
            'purpose' => 'operations',
            'environment' => 'production',
            'machine_group' => 'ops-primary',
            'enabled' => true,
            'locale' => 'vi',
            'allowed_chat_ids' => ['123456'],
            'allowed_user_ids' => ['654321'],
            'allowed_actions' => ['status', 'summary'],
            'public_config' => [
                'allowed_updates' => ['message'],
                'chat_history_limit' => 44,
                'chat_session_timeline_limit' => 7,
            ],
            'secret_config' => [
                'bot_token' => 'hidden-token',
                'webhook_secret' => 'hidden-secret',
            ],
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.auto-coding.telegram-bots.runtime'));

        $response->assertOk()
            ->assertJsonPath('data.key', 'default')
            ->assertJsonPath('data.purpose', 'operations')
            ->assertJsonPath('data.environment', 'production')
            ->assertJsonPath('data.machine_group', 'ops-primary')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.secret_status.bot_token', true)
            ->assertJsonMissingPath('data.bot_token')
            ->assertJsonMissingPath('data.webhook_secret');
    }

    /**
     * Admins should be able to inspect the current Telegram webhook state from the admin API.
     */
    public function test_admin_can_inspect_the_current_telegram_webhook_state(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->update([
            'enabled' => true,
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'test-secret',
            ],
        ]);

        Http::fake([
            'https://api.telegram.org/bottest-token/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://example.com/api/telegram/auto-coding/webhook',
                ],
            ]),
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.auto-coding.telegram-bots.webhook'));

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.result.url', 'https://example.com/api/telegram/auto-coding/webhook');
    }

    /**
     * Admins should be able to register the current Telegram webhook from the admin API.
     */
    public function test_admin_can_register_the_current_telegram_webhook(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->update([
            'enabled' => true,
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'test-secret',
            ],
        ]);

        Http::fake([
            'https://api.telegram.org/bottest-token/setWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.webhook.register'), [
                'url' => 'https://example.com/api/telegram/auto-coding/webhook',
                'drop_pending_updates' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.result', true);

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/setWebhook'
                && $request['url'] === 'https://example.com/api/telegram/auto-coding/webhook'
                && $request['secret_token'] === 'test-secret'
                && $request['drop_pending_updates'] === true
                && $request['allowed_updates'] === ['message', 'callback_query'];
        });

        $this->assertDatabaseHas('telegram_bot_config_audits', [
            'telegram_bot_config_id' => $config->id,
            'action' => 'webhook_registered',
            'actor_user_id' => $admin->id,
        ]);
    }

    /**
     * Admins should be able to remove the current Telegram webhook from the admin API.
     */
    public function test_admin_can_delete_the_current_telegram_webhook(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->update([
            'enabled' => true,
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'test-secret',
            ],
        ]);

        Http::fake([
            'https://api.telegram.org/bottest-token/deleteWebhook' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.webhook.delete'), [
                'drop_pending_updates' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.result', true);

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/deleteWebhook'
                && $request['drop_pending_updates'] === true;
        });

        $this->assertDatabaseHas('telegram_bot_config_audits', [
            'telegram_bot_config_id' => $config->id,
            'action' => 'webhook_deleted',
            'actor_user_id' => $admin->id,
        ]);
    }

    /**
     * Admins should be able to sync the active Telegram bot command set from the admin API.
     */
    public function test_admin_can_sync_the_default_telegram_bot_commands(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->update([
            'enabled' => true,
            'secret_config' => [
                'bot_token' => 'test-token',
                'webhook_secret' => 'test-secret',
            ],
        ]);

        Http::fake([
            'https://api.telegram.org/bottest-token/setMyCommands' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.commands-sync'));

        $response->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.result', true);

        $this->assertDatabaseHas('telegram_bot_config_audits', [
            'telegram_bot_config_id' => $config->id,
            'action' => 'commands_synced',
            'actor_user_id' => $admin->id,
        ]);
    }

    /**
     * Admins should be able to inspect recent Telegram bot audit history.
     */
    public function test_admin_can_list_telegram_bot_audits(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->audits()->create([
            'actor_user_id' => $admin->id,
            'action' => 'config_updated',
            'metadata' => [
                'changed_fields' => ['allowed_actions'],
            ],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.auto-coding.telegram-bots.audits', ['key' => 'default']));

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'config_updated')
            ->assertJsonPath('data.0.actor.id', $admin->id)
            ->assertJsonPath('data.0.metadata.changed_fields.0', 'allowed_actions');
    }

    /**
     * Admins should confirm their current password before revealing one bot secret.
     */
    public function test_admin_can_reveal_one_telegram_bot_secret_after_password_confirmation(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'password' => 'secret-pass',
        ]);

        $config = TelegramBotConfig::query()->where('key', 'default')->firstOrFail();
        $config->update([
            'secret_config' => [
                'bot_token' => 'revealed-bot-token',
                'webhook_secret' => 'revealed-webhook-secret',
            ],
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.reveal-secret', ['key' => 'default']), [
                'secret_key' => 'bot_token',
                'password' => 'secret-pass',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.secret_key', 'bot_token')
            ->assertJsonPath('data.value', 'revealed-bot-token');

        $this->assertDatabaseHas('telegram_bot_config_audits', [
            'telegram_bot_config_id' => $config->id,
            'action' => 'secret_revealed',
            'actor_user_id' => $admin->id,
        ]);
    }

    /**
     * Secret reveal should reject an incorrect current password.
     */
    public function test_admin_cannot_reveal_one_telegram_bot_secret_with_the_wrong_password(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'password' => 'secret-pass',
        ]);

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.auto-coding.telegram-bots.reveal-secret', ['key' => 'default']), [
                'secret_key' => 'bot_token',
                'password' => 'wrong-pass',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
