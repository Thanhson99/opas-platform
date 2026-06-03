<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

class AutoCodingTelegramAccessControlService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AutoCodingTelegramRuntimeConfigService $runtimeConfigService,
    ) {}

    /**
     * Determine whether Telegram remote control is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return ($this->runtimeConfigService->getRuntimeConfig()['enabled'] ?? false) === true;
    }

    /**
     * Confirm the incoming webhook secret matches the configured Telegram secret.
     *
     * @param  string|null  $providedSecret
     * @return bool
     */
    public function hasValidWebhookSecret(?string $providedSecret): bool
    {
        $expectedSecret = $this->runtimeConfigService->getRuntimeConfig()['webhook_secret'] ?? null;

        if (! is_string($expectedSecret) || trim($expectedSecret) === '') {
            return true;
        }

        return is_string($providedSecret) && hash_equals(trim($expectedSecret), trim($providedSecret));
    }

    /**
     * Confirm one Telegram chat or user is allowed to control the bot.
     *
     * @param  array{chat_id:int|string|null,user_id:int|string|null}  $messageContext
     * @return bool
     */
    public function isAuthorized(array $messageContext): bool
    {
        $runtimeConfig = $this->runtimeConfigService->getRuntimeConfig();
        $allowedChatIds = $this->normalizeIdentifierList($runtimeConfig['allowed_chat_ids'] ?? []);
        $allowedUserIds = $this->normalizeIdentifierList($runtimeConfig['allowed_user_ids'] ?? []);

        if ($allowedChatIds === [] && $allowedUserIds === []) {
            return false;
        }

        $chatId = $messageContext['chat_id'] ?? null;
        $userId = $messageContext['user_id'] ?? null;

        return $this->matchesAllowedIdentifier($chatId, $allowedChatIds)
            || $this->matchesAllowedIdentifier($userId, $allowedUserIds);
    }

    /**
     * Confirm one parsed Telegram action is enabled for remote control.
     *
     * @param  string  $action
     * @return bool
     */
    public function isActionAllowed(string $action): bool
    {
        $allowedActions = $this->runtimeConfigService->getRuntimeConfig()['allowed_actions'] ?? [];

        if (! is_array($allowedActions)) {
            return false;
        }

        $normalizedActions = array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $allowedActions
        ), static fn (string $value): bool => $value !== ''));

        $acceptedKeys = $this->resolveAllowedActionKeys($action);

        foreach ($acceptedKeys as $acceptedKey) {
            if (in_array($acceptedKey, $normalizedActions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the internal action key plus supported command-style aliases.
     *
     * This keeps allow-lists stable even when operator-facing Telegram command
     * names evolve from compact aliases to clearer snake_case variants.
     *
     * @param  string  $action
     * @return array<int, string>
     */
    protected function resolveAllowedActionKeys(string $action): array
    {
        $normalizedAction = trim($action);

        return match ($normalizedAction) {
            'chat_start' => ['chat_start', 'start', 'chat_mode'],
            'chat_ping' => ['chat_ping', 'chat_status', 'chat_mode'],
            'chat_status' => ['chat_status', 'chat_mode'],
            'chat_stop' => ['chat_stop', 'stop', 'chat_mode'],
            'chat_reset' => ['chat_reset', 'chat_mode'],
            'next_action' => ['next_action', 'next'],
            'follow_up' => ['follow_up', 'followup'],
            'validation' => ['validation', 'validation_report'],
            'cancel_tasks' => ['cancel_tasks', 'cancelall', 'cancel_all'],
            'delete_tasks' => ['delete_tasks', 'deleteall', 'delete_all'],
            'reset' => ['reset', 'clear', 'clear_all', 'clearall'],
            default => [$normalizedAction],
        };
    }

    /**
     * Normalize one configured allow-list into stable string identifiers.
     *
     * @param  mixed  $values
     * @return array<int, string>
     */
    protected function normalizeIdentifierList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $normalizedValues = [];

        foreach ($values as $value) {
            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            $normalizedValue = trim((string) $value);

            if ($normalizedValue === '') {
                continue;
            }

            $normalizedValues[] = $normalizedValue;
        }

        return array_values(array_unique($normalizedValues));
    }

    /**
     * Determine whether one runtime identifier matches the configured allow-list.
     *
     * @param  int|string|null  $identifier
     * @param  array<int, string>  $allowedIdentifiers
     * @return bool
     */
    protected function matchesAllowedIdentifier(int|string|null $identifier, array $allowedIdentifiers): bool
    {
        if (! is_string($identifier) && ! is_int($identifier)) {
            return false;
        }

        return in_array(trim((string) $identifier), $allowedIdentifiers, true);
    }
}
