<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding\Telegram;

use App\Models\TelegramBotConfig;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramRuntimeConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify fallback and database-preferred runtime configuration resolution for Telegram bots.
 */
class AutoCodingTelegramRuntimeConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Runtime config should stay disabled until database or env has a usable bot.
     */
    public function test_runtime_config_returns_empty_defaults_when_no_database_bot_exists(): void
    {
        TelegramBotConfig::query()->delete();

        $service = $this->app->make(AutoCodingTelegramRuntimeConfigService::class);
        $service->forgetCachedRuntimeConfig();
        $runtimeConfig = $service->getRuntimeConfig();

        $this->assertSame('env', $runtimeConfig['source']);
        $this->assertNull($runtimeConfig['bot_token']);
        $this->assertNull($runtimeConfig['webhook_secret']);
        $this->assertSame('en', $runtimeConfig['locale']);
        $this->assertFalse($runtimeConfig['enabled']);
    }

    /**
     * Environment bootstrap values are used when no usable database bot exists.
     */
    public function test_runtime_config_falls_back_to_env_when_database_bot_is_not_usable(): void
    {
        TelegramBotConfig::query()->delete();
        TelegramBotConfig::query()->create([
            'key' => 'disabled-db-bot',
            'display_name' => 'Disabled Database Bot',
            'enabled' => false,
            'is_default' => true,
            'locale' => 'vi',
            'allowed_chat_ids' => [],
            'allowed_user_ids' => [],
            'allowed_actions' => [],
            'public_config' => [],
            'secret_config' => [],
        ]);
        config()->set('opas.auto_coding.telegram.bootstrap_bot_token', 'env-token');
        config()->set('opas.auto_coding.telegram.bootstrap_webhook_secret', 'env-secret');
        config()->set('opas.auto_coding.telegram.bootstrap_allowed_chat_ids', ['777']);
        config()->set('opas.auto_coding.telegram.bootstrap_allowed_user_ids', []);
        config()->set('opas.auto_coding.telegram.bootstrap_allowed_actions', ['chat_start', 'chat_stop']);

        $service = $this->app->make(AutoCodingTelegramRuntimeConfigService::class);
        $service->forgetCachedRuntimeConfig();
        $runtimeConfig = $service->getRuntimeConfig();

        $this->assertSame('env', $runtimeConfig['source']);
        $this->assertTrue($runtimeConfig['enabled']);
        $this->assertSame('env-token', $runtimeConfig['bot_token']);
        $this->assertSame('env-secret', $runtimeConfig['webhook_secret']);
        $this->assertSame(['777'], $runtimeConfig['allowed_chat_ids']);
        $this->assertSame(['chat_start', 'chat_stop'], $runtimeConfig['allowed_actions']);
    }

    /**
     * Once a default DB bot exists, runtime reads should prefer the database over environment values.
     */
    public function test_runtime_config_prefers_default_database_bot_when_available(): void
    {
        TelegramBotConfig::query()->delete();
        config()->set('opas.auto_coding.telegram.bootstrap_bot_token', 'env-token');
        config()->set('opas.auto_coding.telegram.bootstrap_webhook_secret', 'env-secret');
        config()->set('opas.auto_coding.telegram.bootstrap_allowed_chat_ids', ['777']);
        config()->set('opas.auto_coding.telegram.bootstrap_allowed_actions', ['chat_start']);
        TelegramBotConfig::query()->create([
            'key' => 'db-bot',
            'display_name' => 'Database Bot',
            'enabled' => true,
            'is_default' => true,
            'locale' => 'vi',
            'api_base_url' => 'https://telegram.internal',
            'allowed_chat_ids' => ['123'],
            'allowed_user_ids' => ['456'],
            'allowed_actions' => ['github_status', 'chat_reset'],
            'public_config' => [
                'allowed_updates' => ['message'],
                'chat_history_limit' => 55,
                'chat_session_timeline_limit' => 9,
            ],
            'secret_config' => [
                'bot_token' => 'db-token',
                'webhook_secret' => 'db-secret',
            ],
        ]);

        $service = $this->app->make(AutoCodingTelegramRuntimeConfigService::class);
        $service->forgetCachedRuntimeConfig();
        $runtimeConfig = $service->getRuntimeConfig();

        $this->assertSame('database', $runtimeConfig['source']);
        $this->assertSame('db-bot', $runtimeConfig['key']);
        $this->assertSame('db-token', $runtimeConfig['bot_token']);
        $this->assertSame('db-secret', $runtimeConfig['webhook_secret']);
        $this->assertSame('https://telegram.internal', $runtimeConfig['api_base_url']);
        $this->assertSame(['github_status', 'chat_reset'], $runtimeConfig['allowed_actions']);
        $this->assertSame(55, $runtimeConfig['chat_history_limit']);
        $this->assertSame(9, $runtimeConfig['chat_session_timeline_limit']);
    }
}
