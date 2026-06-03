<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\TelegramBotConfig;

class AutoCodingTelegramLegacyEnvImportService
{
    public function __construct(
        private readonly AutoCodingTelegramBotConfigService $botConfigService,
    ) {}

    /**
     * Import deployment/bootstrap Telegram bot values from env into the DB config.
     *
     * @return TelegramBotConfig|null
     */
    public function importDefaultBotFromEnv(): ?TelegramBotConfig
    {
        $payload = $this->buildImportPayload();

        if ($payload === []) {
            return null;
        }

        $config = $this->botConfigService->ensureDefaultBotExists();

        return $this->botConfigService->update($config, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildImportPayload(): array
    {
        $payload = [];
        $secretConfig = array_filter([
            'bot_token' => $this->stringConfig('bootstrap_bot_token'),
            'webhook_secret' => $this->stringConfig('bootstrap_webhook_secret'),
        ], static fn (?string $value): bool => is_string($value) && trim($value) !== '');

        if ($secretConfig !== []) {
            $payload['secret_config'] = $secretConfig;
        }

        foreach ([
            'allowed_chat_ids' => 'bootstrap_allowed_chat_ids',
            'allowed_user_ids' => 'bootstrap_allowed_user_ids',
            'allowed_actions' => 'bootstrap_allowed_actions',
        ] as $payloadKey => $configKey) {
            $values = $this->arrayConfig($configKey);

            if ($values !== []) {
                $payload[$payloadKey] = $values;
            }
        }

        $hasToken = array_key_exists('bot_token', $secretConfig);
        $hasSecret = array_key_exists('webhook_secret', $secretConfig);
        $hasAllowedOperator = ($payload['allowed_chat_ids'] ?? []) !== []
            || ($payload['allowed_user_ids'] ?? []) !== [];

        if ($hasToken && $hasSecret && $hasAllowedOperator) {
            $payload['enabled'] = true;
        }

        return $payload;
    }

    protected function stringConfig(string $key): ?string
    {
        $value = config(sprintf('opas.auto_coding.telegram.%s', $key));

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array<int, string>
     */
    protected function arrayConfig(string $key): array
    {
        $values = config(sprintf('opas.auto_coding.telegram.%s', $key));

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values
        ), static fn (string $value): bool => $value !== ''));
    }
}
