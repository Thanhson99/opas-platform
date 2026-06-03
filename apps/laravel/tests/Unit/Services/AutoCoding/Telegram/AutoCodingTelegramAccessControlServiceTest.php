<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding\Telegram;

use App\Models\TelegramBotConfig;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramAccessControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingTelegramAccessControlServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm internal dangerous-action keys can still be allowed through command-style aliases.
     *
     * @return void
     */
    public function test_it_accepts_command_aliases_for_allowed_actions(): void
    {
        TelegramBotConfig::query()->create([
            'key' => 'default',
            'display_name' => 'Default Telegram Bot',
            'enabled' => true,
            'is_default' => true,
            'locale' => 'en',
            'api_base_url' => 'https://api.telegram.org',
            'allowed_chat_ids' => [],
            'allowed_user_ids' => [],
            'allowed_actions' => [
                'delete_all',
                'cancel_all',
                'validation_report',
                'chat_mode',
            ],
            'public_config' => [
                'allowed_updates' => ['message', 'callback_query'],
                'chat_history_limit' => 30,
                'chat_session_timeline_limit' => 6,
            ],
            'secret_config' => [],
        ]);

        $service = $this->app->make(AutoCodingTelegramAccessControlService::class);

        self::assertTrue($service->isActionAllowed('delete_tasks'));
        self::assertTrue($service->isActionAllowed('cancel_tasks'));
        self::assertTrue($service->isActionAllowed('validation'));
        self::assertFalse($service->isActionAllowed('commands_sync'));
        self::assertTrue($service->isActionAllowed('chat_start'));
        self::assertTrue($service->isActionAllowed('chat_status'));
        self::assertTrue($service->isActionAllowed('chat_stop'));
        self::assertTrue($service->isActionAllowed('chat_reset'));
    }
}
