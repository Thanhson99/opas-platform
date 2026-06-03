<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use App\Models\TelegramBotConfig;
use App\Models\TelegramBotConfigAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AutoCodingTelegramBotAuditService
{
    /**
     * @param  TelegramBotConfig  $config
     * @return array<string, mixed>
     */
    public function snapshot(TelegramBotConfig $config): array
    {
        $secretConfig = $this->normalizeArray($config->secret_config);

        return [
            'display_name' => $config->display_name,
            'purpose' => $config->purpose,
            'environment' => $config->environment,
            'machine_group' => $config->machine_group,
            'enabled' => $config->enabled,
            'is_default' => $config->is_default,
            'locale' => $config->locale,
            'api_base_url' => $config->api_base_url,
            'allowed_chat_ids' => $config->allowed_chat_ids ?? [],
            'allowed_user_ids' => $config->allowed_user_ids ?? [],
            'allowed_actions' => $config->allowed_actions ?? [],
            'public_config' => $this->normalizeArray($config->public_config),
            'secret_status' => [
                'bot_token' => $this->hasConfiguredSecret($secretConfig, 'bot_token'),
                'webhook_secret' => $this->hasConfiguredSecret($secretConfig, 'webhook_secret'),
            ],
        ];
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  User|null  $actor
     * @return void
     */
    public function recordConfigCreated(TelegramBotConfig $config, ?User $actor = null): void
    {
        $this->record($config, $actor, 'config_created', [
            'changed_fields' => array_keys($this->flattenSnapshot($this->snapshot($config))),
        ]);
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  User|null  $actor
     * @return void
     */
    public function recordConfigUpdated(
        TelegramBotConfig $config,
        array $before,
        array $after,
        ?User $actor = null,
    ): void {
        $changedFields = $this->detectChangedFields($before, $after);

        if ($changedFields === []) {
            return;
        }

        $this->record($config, $actor, 'config_updated', [
            'changed_fields' => $changedFields,
        ]);
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  string  $action
     * @param  array<string, mixed>  $result
     * @param  User|null  $actor
     * @param  array<string, mixed>  $context
     * @return void
     */
    public function recordRuntimeOperation(
        TelegramBotConfig $config,
        string $action,
        array $result,
        ?User $actor = null,
        array $context = [],
    ): void {
        $this->record($config, $actor, $action, [
            'ok' => ($result['ok'] ?? false) === true,
            'description' => is_string($result['description'] ?? null) ? $result['description'] : null,
            'context' => $context,
        ]);
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  int  $limit
     * @return Collection<int, TelegramBotConfigAudit>
     */
    public function listRecent(TelegramBotConfig $config, int $limit = 20): Collection
    {
        return $config->audits()
            ->with('actor:id,name,email')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @param  TelegramBotConfig  $config
     * @param  User|null  $actor
     * @param  string  $action
     * @param  array<string, mixed>  $metadata
     * @return void
     */
    private function record(TelegramBotConfig $config, ?User $actor, string $action, array $metadata): void
    {
        TelegramBotConfigAudit::query()->create([
            'telegram_bot_config_id' => $config->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private function detectChangedFields(array $before, array $after): array
    {
        $beforeFlat = $this->flattenSnapshot($before);
        $afterFlat = $this->flattenSnapshot($after);
        $keys = array_unique(array_merge(array_keys($beforeFlat), array_keys($afterFlat)));
        $changedFields = [];

        foreach ($keys as $key) {
            if (($beforeFlat[$key] ?? null) === ($afterFlat[$key] ?? null)) {
                continue;
            }

            $changedFields[] = $key;
        }

        sort($changedFields);

        return $changedFields;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  string  $prefix
     * @return array<string, string>
     */
    private function flattenSnapshot(array $snapshot, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($snapshot as $key => $value) {
            $composedKey = $prefix === '' ? $key : sprintf('%s.%s', $prefix, $key);

            if (is_array($value)) {
                $nested = $this->flattenSnapshot($this->normalizeArray($value), $composedKey);

                if ($nested !== []) {
                    $flattened = array_merge($flattened, $nested);

                    continue;
                }
            }

            $flattened[$composedKey] = $this->stringifyValue($value);
        }

        return $flattened;
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $item;
        }

        return $normalized;
    }

    /**
     * @param  mixed  $value
     * @return string
     */
    private function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value) ?: '';
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
