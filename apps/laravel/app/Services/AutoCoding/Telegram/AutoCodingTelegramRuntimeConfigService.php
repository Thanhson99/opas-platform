<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\TelegramBotConfig;
use App\Repositories\AutoCoding\Interfaces\TelegramBotConfigRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AutoCodingTelegramRuntimeConfigService
{
    private const CACHE_KEY = 'auto-coding:telegram:runtime-config:default';

    /**
     * @return void
     */
    public function __construct(
        private readonly TelegramBotConfigRepositoryInterface $telegramBotConfigRepository,
    ) {}

    /**
     * Return the active Telegram bot runtime settings.
     *
     * @return array<string, mixed>
     */
    public function getRuntimeConfig(): array
    {
        /** @var array<string, mixed> $runtimeConfig */
        $runtimeConfig = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $databaseConfig = $this->resolveDatabaseRuntimeConfig();
            $envConfig = $this->buildEnvRuntimeConfig();

            if ($databaseConfig !== null && $this->isUsableRuntimeConfig($databaseConfig)) {
                return $databaseConfig;
            }

            if ($this->isUsableRuntimeConfig($envConfig)) {
                return $envConfig;
            }

            return $databaseConfig ?? $envConfig;
        });

        return $runtimeConfig;
    }

    /**
     * Clear the cached Telegram bot runtime settings after admin updates.
     *
     * @return void
     */
    public function forgetCachedRuntimeConfig(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Return the configured fallback values used before a database bot is ready.
     *
     * @return array<string, mixed>
     */
    public function getDefaultRuntimeConfig(): array
    {
        $allowedUpdates = $this->normalizeStringList(config('opas.auto_coding.telegram.default_allowed_updates'));

        return [
            'source' => 'database_missing',
            'key' => $this->normalizeString(config('opas.auto_coding.telegram.default_key')),
            'display_name' => $this->normalizeString(config('opas.auto_coding.telegram.default_display_name')),
            'purpose' => $this->normalizeString(config('opas.auto_coding.telegram.default_purpose')),
            'environment' => $this->normalizeString(config('opas.auto_coding.telegram.default_environment')),
            'machine_group' => $this->normalizeString(config('opas.auto_coding.telegram.default_machine_group')),
            'enabled' => false,
            'bot_token' => null,
            'api_base_url' => $this->normalizeString(config('opas.auto_coding.telegram.default_api_base_url')),
            'webhook_secret' => null,
            'locale' => $this->normalizeLocale(config('opas.auto_coding.telegram.default_locale')),
            'chat_history_limit' => $this->normalizePositiveInteger(config('opas.auto_coding.telegram.default_chat_history_limit'), 1),
            'chat_session_timeline_limit' => $this->normalizePositiveInteger(config('opas.auto_coding.telegram.default_chat_session_timeline_limit'), 1),
            'allowed_updates' => $allowedUpdates,
            'allowed_chat_ids' => [],
            'allowed_user_ids' => [],
            'allowed_actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveDatabaseRuntimeConfig(): ?array
    {
        if (! $this->telegramBotConfigTableExists()) {
            return null;
        }

        $config = $this->telegramBotConfigRepository->findDefault();

        if (! $config instanceof TelegramBotConfig) {
            return null;
        }

        return $this->buildDatabaseRuntimeConfig($config);
    }

    /**
     * @param  TelegramBotConfig  $config
     * @return array<string, mixed>
     */
    private function buildDatabaseRuntimeConfig(TelegramBotConfig $config): array
    {
        $defaults = $this->getDefaultRuntimeConfig();
        $publicConfig = $this->normalizeStringKeyArray($config->public_config);
        $secretConfig = $this->normalizeStringKeyArray($config->secret_config);
        $defaultChatHistoryLimit = is_numeric($defaults['chat_history_limit'] ?? null)
            ? (int) $defaults['chat_history_limit']
            : 30;
        $defaultTimelineLimit = is_numeric($defaults['chat_session_timeline_limit'] ?? null)
            ? (int) $defaults['chat_session_timeline_limit']
            : 6;

        return [
            'source' => 'database',
            'key' => $config->key,
            'display_name' => $config->display_name,
            'purpose' => $config->purpose,
            'environment' => $config->environment,
            'machine_group' => $config->machine_group,
            'enabled' => $config->enabled,
            'bot_token' => $this->normalizeString($secretConfig['bot_token'] ?? null),
            'api_base_url' => $this->normalizeString($config->api_base_url) ?? $defaults['api_base_url'],
            'webhook_secret' => $this->normalizeString($secretConfig['webhook_secret'] ?? null),
            'locale' => $this->normalizeLocale($config->locale),
            'chat_history_limit' => $this->normalizePositiveInteger($publicConfig['chat_history_limit'] ?? null, $defaultChatHistoryLimit),
            'chat_session_timeline_limit' => $this->normalizePositiveInteger($publicConfig['chat_session_timeline_limit'] ?? null, $defaultTimelineLimit),
            'allowed_updates' => $this->normalizeStringList($publicConfig['allowed_updates'] ?? $defaults['allowed_updates']),
            'allowed_chat_ids' => $this->normalizeStringList($config->allowed_chat_ids),
            'allowed_user_ids' => $this->normalizeStringList($config->allowed_user_ids),
            'allowed_actions' => $this->normalizeStringList($config->allowed_actions),
        ];
    }

    /**
     * Build one env-backed runtime config used when the database bot is not ready.
     *
     * @return array<string, mixed>
     */
    private function buildEnvRuntimeConfig(): array
    {
        $defaults = $this->getDefaultRuntimeConfig();
        $botToken = $this->normalizeString(config('opas.auto_coding.telegram.bootstrap_bot_token'));
        $webhookSecret = $this->normalizeString(config('opas.auto_coding.telegram.bootstrap_webhook_secret'));
        $allowedChatIds = $this->normalizeStringList(config('opas.auto_coding.telegram.bootstrap_allowed_chat_ids'));
        $allowedUserIds = $this->normalizeStringList(config('opas.auto_coding.telegram.bootstrap_allowed_user_ids'));
        $allowedActions = $this->normalizeStringList(config('opas.auto_coding.telegram.bootstrap_allowed_actions'));
        $enabled = $botToken !== null
            && $webhookSecret !== null
            && ($allowedChatIds !== [] || $allowedUserIds !== []);

        return array_merge($defaults, [
            'source' => 'env',
            'enabled' => $enabled,
            'bot_token' => $botToken,
            'webhook_secret' => $webhookSecret,
            'allowed_chat_ids' => $allowedChatIds,
            'allowed_user_ids' => $allowedUserIds,
            'allowed_actions' => $allowedActions,
        ]);
    }

    /**
     * Determine whether one runtime config can operate the Telegram webhook.
     *
     * @param  array<string, mixed>  $runtimeConfig
     * @return bool
     */
    private function isUsableRuntimeConfig(array $runtimeConfig): bool
    {
        $allowedChatIds = is_array($runtimeConfig['allowed_chat_ids'] ?? null) ? $runtimeConfig['allowed_chat_ids'] : [];
        $allowedUserIds = is_array($runtimeConfig['allowed_user_ids'] ?? null) ? $runtimeConfig['allowed_user_ids'] : [];

        return ($runtimeConfig['enabled'] ?? false) === true
            && $this->normalizeString($runtimeConfig['bot_token'] ?? null) !== null
            && $this->normalizeString($runtimeConfig['webhook_secret'] ?? null) !== null
            && ($allowedChatIds !== [] || $allowedUserIds !== []);
    }

    /**
     * @return bool
     */
    private function telegramBotConfigTableExists(): bool
    {
        try {
            return Schema::hasTable('telegram_bot_configs');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function normalizeStringKeyArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            $stringValue = $this->normalizeString($item);

            if ($stringValue === null) {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  mixed  $value
     * @return string|null
     */
    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    private function normalizeLocale(mixed $value): string
    {
        $locale = $this->normalizeString($value);

        return $locale === 'vi' ? 'vi' : 'en';
    }

    /**
     * @param  mixed  $value
     * @param  int  $fallback
     * @return int
     */
    private function normalizePositiveInteger(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : $fallback;
    }
}
