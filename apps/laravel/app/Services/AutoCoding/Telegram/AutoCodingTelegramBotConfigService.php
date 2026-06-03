<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\TelegramBotConfig;
use App\Models\User;
use App\Repositories\AutoCoding\Interfaces\TelegramBotConfigRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutoCodingTelegramBotConfigService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly TelegramBotConfigRepositoryInterface $telegramBotConfigRepository,
        private readonly AutoCodingTelegramRuntimeConfigService $runtimeConfigService,
        private readonly AutoCodingTelegramBotAuditService $auditService,
    ) {}

    /**
     * Persist the initial default Telegram bot config from environment values once.
     *
     * @return TelegramBotConfig
     */
    public function ensureDefaultBotExists(): TelegramBotConfig
    {
        $defaultConfig = $this->telegramBotConfigRepository->findDefault();

        if ($defaultConfig instanceof TelegramBotConfig) {
            return $defaultConfig;
        }

        $defaults = $this->normalizeRuntimeDefaults($this->runtimeConfigService->getDefaultRuntimeConfig());
        $config = $this->telegramBotConfigRepository->firstOrCreateByKey($defaults['key'], [
            'display_name' => $defaults['display_name'],
            'purpose' => $defaults['purpose'],
            'environment' => $defaults['environment'],
            'machine_group' => $defaults['machine_group'],
            'enabled' => false,
            'is_default' => true,
            'locale' => $defaults['locale'],
            'api_base_url' => $defaults['api_base_url'],
            'allowed_chat_ids' => [],
            'allowed_user_ids' => [],
            'allowed_actions' => [],
            'public_config' => [
                'allowed_updates' => $defaults['allowed_updates'],
                'chat_history_limit' => $defaults['chat_history_limit'],
                'chat_session_timeline_limit' => $defaults['chat_session_timeline_limit'],
            ],
            'secret_config' => [],
        ]);

        if (! $config->is_default) {
            $config = $this->telegramBotConfigRepository->update($config, [
                'is_default' => true,
            ]);
        }

        $this->telegramBotConfigRepository->clearDefaultFlagExcept($config->id);
        $this->runtimeConfigService->forgetCachedRuntimeConfig();

        return $config;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  User|null  $actor
     * @return TelegramBotConfig
     */
    public function create(array $validated, ?User $actor = null): TelegramBotConfig
    {
        $attributes = $this->normalizeAttributes($validated);
        $this->validateCreate($attributes);

        $createdConfig = DB::transaction(function () use ($attributes): TelegramBotConfig {
            $isDefault = $this->shouldPersistAsDefault($attributes, null);

            if ($isDefault) {
                $this->telegramBotConfigRepository->clearDefaultFlagExcept(null);
            }

            $createdConfig = $this->telegramBotConfigRepository->create(array_merge($attributes, [
                'is_default' => $isDefault,
            ]));

            return $createdConfig;
        });

        $this->runtimeConfigService->forgetCachedRuntimeConfig();
        $this->auditService->recordConfigCreated($createdConfig, $actor);

        return $createdConfig;
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  array<string, mixed>  $validated
     * @param  User|null  $actor
     * @return TelegramBotConfig
     */
    public function update(TelegramBotConfig $config, array $validated, ?User $actor = null): TelegramBotConfig
    {
        $beforeSnapshot = $this->auditService->snapshot($config);
        $attributes = $this->normalizeAttributes($validated, $config);
        $this->validateUpdate($config, $attributes);

        $updatedConfig = DB::transaction(function () use ($config, $attributes): TelegramBotConfig {
            $isDefault = $this->shouldPersistAsDefault($attributes, $config);

            if ($isDefault) {
                $this->telegramBotConfigRepository->clearDefaultFlagExcept($config->id);
            }

            return $this->telegramBotConfigRepository->update($config, array_merge($attributes, [
                'is_default' => $isDefault,
            ]));
        });

        $this->runtimeConfigService->forgetCachedRuntimeConfig();
        $this->auditService->recordConfigUpdated(
            $updatedConfig,
            $beforeSnapshot,
            $this->auditService->snapshot($updatedConfig),
            $actor,
        );

        return $updatedConfig;
    }

    /**
     * Remove one Telegram bot config when runtime defaults remain valid.
     *
     * @param  TelegramBotConfig  $config
     * @return void
     */
    public function delete(TelegramBotConfig $config): void
    {
        $this->telegramBotConfigRepository->delete($config);
        $this->runtimeConfigService->forgetCachedRuntimeConfig();
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array{
     *     key: string,
     *     display_name: string,
     *     purpose: string,
     *     environment: string,
     *     machine_group: string|null,
     *     locale: string,
     *     api_base_url: string|null,
     *     allowed_updates: mixed,
     *     chat_history_limit: mixed,
     *     chat_session_timeline_limit: mixed
     * }
     */
    private function normalizeRuntimeDefaults(array $defaults): array
    {
        return [
            'key' => $this->stringDefault($defaults, 'key'),
            'display_name' => $this->stringDefault($defaults, 'display_name'),
            'purpose' => $this->stringDefault($defaults, 'purpose'),
            'environment' => $this->stringDefault($defaults, 'environment'),
            'machine_group' => $this->nullableStringDefault($defaults, 'machine_group'),
            'locale' => $this->stringDefault($defaults, 'locale'),
            'api_base_url' => $this->nullableStringDefault($defaults, 'api_base_url'),
            'allowed_updates' => $defaults['allowed_updates'] ?? [],
            'chat_history_limit' => $defaults['chat_history_limit'] ?? 1,
            'chat_session_timeline_limit' => $defaults['chat_session_timeline_limit'] ?? 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  string  $key
     * @return string
     */
    private function stringDefault(array $defaults, string $key): string
    {
        $value = $defaults[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  string  $key
     * @return string|null
     */
    private function nullableStringDefault(array $defaults, string $key): ?string
    {
        $value = $this->stringDefault($defaults, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  TelegramBotConfig|null  $currentConfig
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $validated, ?TelegramBotConfig $currentConfig = null): array
    {
        $attributes = $validated;

        if (array_key_exists('allowed_chat_ids', $attributes)) {
            $attributes['allowed_chat_ids'] = $this->normalizeStringList($attributes['allowed_chat_ids']);
        }

        if (array_key_exists('allowed_user_ids', $attributes)) {
            $attributes['allowed_user_ids'] = $this->normalizeStringList($attributes['allowed_user_ids']);
        }

        if (array_key_exists('allowed_actions', $attributes)) {
            $attributes['allowed_actions'] = $this->normalizeStringList($attributes['allowed_actions']);
        }

        if (array_key_exists('public_config', $attributes)) {
            $attributes['public_config'] = $this->normalizePublicConfig($attributes['public_config']);
        }

        if (array_key_exists('secret_config', $attributes)) {
            $currentSecrets = $currentConfig instanceof TelegramBotConfig
                ? $this->normalizeStringKeyArray($currentConfig->secret_config)
                : [];

            $attributes['secret_config'] = $this->mergeSecretConfig($currentSecrets, $attributes['secret_config']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    private function validateCreate(array $attributes): void
    {
        $errors = $this->buildValidationErrors($attributes);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    private function validateUpdate(TelegramBotConfig $config, array $attributes): void
    {
        $errors = $this->buildValidationErrors($attributes, $config);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  TelegramBotConfig|null  $currentConfig
     * @return array<string, list<string>>
     */
    private function buildValidationErrors(array $attributes, ?TelegramBotConfig $currentConfig = null): array
    {
        $errors = [];
        $enabled = (bool) ($attributes['enabled'] ?? ($currentConfig instanceof TelegramBotConfig ? $currentConfig->enabled : false));
        $secretConfig = array_key_exists('secret_config', $attributes)
            ? $this->normalizeStringKeyArray($attributes['secret_config'])
            : $this->normalizeStringKeyArray($currentConfig instanceof TelegramBotConfig ? $currentConfig->secret_config : []);

        if ($enabled && ! $this->hasConfiguredSecret($secretConfig, 'bot_token')) {
            $errors['secret_config.bot_token'] = ['The bot token field is required when the Telegram bot is enabled.'];
        }

        if ($enabled && ! $this->hasConfiguredOperator($attributes, $currentConfig)) {
            $errors['allowed_chat_ids'] = ['At least one allowed chat ID or user ID is required when the Telegram bot is enabled.'];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  TelegramBotConfig|null  $currentConfig
     * @return bool
     */
    private function hasConfiguredOperator(array $attributes, ?TelegramBotConfig $currentConfig = null): bool
    {
        $chatIds = array_key_exists('allowed_chat_ids', $attributes)
            ? $this->normalizeStringList($attributes['allowed_chat_ids'])
            : $this->normalizeStringList($currentConfig instanceof TelegramBotConfig ? $currentConfig->allowed_chat_ids : []);
        $userIds = array_key_exists('allowed_user_ids', $attributes)
            ? $this->normalizeStringList($attributes['allowed_user_ids'])
            : $this->normalizeStringList($currentConfig instanceof TelegramBotConfig ? $currentConfig->allowed_user_ids : []);

        return $chatIds !== [] || $userIds !== [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  TelegramBotConfig|null  $currentConfig
     * @return bool
     */
    private function shouldPersistAsDefault(array $attributes, ?TelegramBotConfig $currentConfig): bool
    {
        if (array_key_exists('is_default', $attributes)) {
            return (bool) $attributes['is_default'];
        }

        if ($currentConfig instanceof TelegramBotConfig) {
            return $currentConfig->is_default;
        }

        return $this->telegramBotConfigRepository->findDefault() === null;
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
            if (! is_string($item) && ! is_int($item)) {
                continue;
            }

            $stringValue = trim((string) $item);

            if ($stringValue === '') {
                continue;
            }

            $normalized[] = $stringValue;
        }

        return array_values(array_unique($normalized));
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
     * @return array<string, mixed>
     */
    private function normalizePublicConfig(mixed $value): array
    {
        $publicConfig = $this->normalizeStringKeyArray($value);

        if (array_key_exists('allowed_updates', $publicConfig)) {
            $publicConfig['allowed_updates'] = $this->normalizeStringList($publicConfig['allowed_updates']);
        }

        if (array_key_exists('bot_username', $publicConfig) && is_string($publicConfig['bot_username'])) {
            $publicConfig['bot_username'] = trim($publicConfig['bot_username']);
        }

        if (array_key_exists('description', $publicConfig) && is_string($publicConfig['description'])) {
            $publicConfig['description'] = trim($publicConfig['description']);
        }

        if (array_key_exists('chat_history_limit', $publicConfig) && is_numeric($publicConfig['chat_history_limit'])) {
            $publicConfig['chat_history_limit'] = max(1, (int) $publicConfig['chat_history_limit']);
        }

        if (array_key_exists('chat_session_timeline_limit', $publicConfig) && is_numeric($publicConfig['chat_session_timeline_limit'])) {
            $publicConfig['chat_session_timeline_limit'] = max(1, (int) $publicConfig['chat_session_timeline_limit']);
        }

        return $publicConfig;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  mixed  $incoming
     * @return array<string, mixed>
     */
    private function mergeSecretConfig(array $current, mixed $incoming): array
    {
        if (! is_array($incoming)) {
            return $current;
        }

        $next = $current;

        foreach ($incoming as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($next[$key]);

                continue;
            }

            $next[$key] = $value;
        }

        return $next;
    }

    /**
     * @param  array<string, mixed>  $secretConfig
     * @param  string  $key
     * @return bool
     */
    private function hasConfiguredSecret(array $secretConfig, string $key): bool
    {
        $value = $secretConfig[$key] ?? null;

        return is_string($value) && trim($value) !== '';
    }
}
