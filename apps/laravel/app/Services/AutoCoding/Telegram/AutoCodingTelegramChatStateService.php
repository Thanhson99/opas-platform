<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AutoCodingTelegramChatStateService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AutoCodingTelegramRuntimeConfigService $runtimeConfigService,
    ) {}

    /**
     * Remember one bot-owned Telegram message so chat cleanup can remove it later.
     *
     * @param  int|string  $chatId
     * @param  int  $messageId
     * @return void
     */
    public function rememberBotMessage(int|string $chatId, int $messageId): void
    {
        $this->rememberTrackedMessage($chatId, $messageId);
    }

    /**
     * Remember one operator-owned Telegram message so group cleanup can remove it when permissions allow.
     *
     * @param  int|string  $chatId
     * @param  int  $messageId
     * @return void
     */
    public function rememberOperatorMessage(int|string $chatId, int $messageId): void
    {
        $this->rememberTrackedMessage($chatId, $messageId);
    }

    /**
     * Remember one Telegram message so chat cleanup can remove it later.
     *
     * @param  int|string  $chatId
     * @param  int  $messageId
     * @return void
     */
    protected function rememberTrackedMessage(int|string $chatId, int $messageId): void
    {
        if ($messageId <= 0) {
            return;
        }

        $cacheKey = $this->buildCacheKey($chatId);
        $history = Cache::get($cacheKey, []);

        if (! is_array($history)) {
            $history = [];
        }

        $history = $this->normalizeTrackedMessageRecords($history);
        $history[] = [
            'message_id' => $messageId,
            'created_at' => now()->toAtomString(),
        ];

        $configuredLimit = $this->runtimeConfigService->getRuntimeConfig()['chat_history_limit'] ?? 30;
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 30;

        if ($limit > 0 && count($history) > $limit) {
            $history = array_slice($history, -1 * $limit);
        }

        Cache::forever($cacheKey, $history);
    }

    /**
     * Read the tracked bot message ids for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @return array<int, int>
     */
    public function getTrackedMessageIds(int|string $chatId): array
    {
        return array_map(
            static fn (array $record): int => (int) $record['message_id'],
            $this->getTrackedMessageRecords($chatId)
        );
    }

    /**
     * Read tracked bot message ids for the current chat session when available.
     *
     * @param  int|string  $chatId
     * @return array<int, int>
     */
    public function getCurrentSessionMessageIds(int|string $chatId): array
    {
        $session = $this->getChatSession($chatId);

        if (! is_array($session) || ! is_string($session['started_at'] ?? null)) {
            return $this->getTrackedMessageIds($chatId);
        }

        $sessionStartedAt = trim((string) $session['started_at']);

        return array_map(
            static fn (array $record): int => (int) $record['message_id'],
            array_values(array_filter(
                $this->getTrackedMessageRecords($chatId),
                static function (array $record) use ($sessionStartedAt): bool {
                    $createdAt = trim($record['created_at']);

                    return $createdAt === '' || $createdAt >= $sessionStartedAt;
                }
            ))
        );
    }

    /**
     * Read tracked bot message records in oldest-to-newest order.
     *
     * @param  int|string  $chatId
     * @return array<int, array{message_id:int,created_at:string}>
     */
    public function getTrackedMessageRecords(int|string $chatId): array
    {
        $history = Cache::get($this->buildCacheKey($chatId), []);

        if (! is_array($history)) {
            return [];
        }

        return $this->normalizeTrackedMessageRecords($history);
    }

    /**
     * Forget the tracked bot message ids for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @return void
     */
    public function forgetTrackedMessages(int|string $chatId): void
    {
        Cache::forget($this->buildCacheKey($chatId));
    }

    /**
     * Forget only selected tracked bot messages for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @param  array<int, int>  $messageIds
     * @return void
     */
    public function forgetTrackedMessageIds(int|string $chatId, array $messageIds): void
    {
        $normalizedMessageIds = $this->normalizePositiveIntegerList($messageIds);

        if ($normalizedMessageIds === []) {
            return;
        }

        $remainingRecords = array_values(array_filter(
            $this->getTrackedMessageRecords($chatId),
            static fn (array $record): bool => ! in_array((int) $record['message_id'], $normalizedMessageIds, true)
        ));

        Cache::forever($this->buildCacheKey($chatId), $remainingRecords);
    }

    /**
     * Remember the latest active task id for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @param  int  $taskId
     * @return void
     */
    public function rememberActiveTaskId(int|string $chatId, int $taskId): void
    {
        if ($taskId <= 0) {
            return;
        }

        Cache::forever($this->buildActiveTaskCacheKey($chatId), $taskId);
        $this->updateChatSessionState($chatId, [
            'active_task_id' => $taskId,
            'last_message_at' => now()->toAtomString(),
        ]);
    }

    /**
     * Read the latest active task id remembered for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @return int|null
     */
    public function getActiveTaskId(int|string $chatId): ?int
    {
        $value = Cache::get($this->buildActiveTaskCacheKey($chatId));

        if (! is_numeric($value)) {
            return null;
        }

        $taskId = (int) $value;

        return $taskId > 0 ? $taskId : null;
    }

    /**
     * Forget the latest active task id remembered for one Telegram chat.
     *
     * @param  int|string  $chatId
     * @return void
     */
    public function forgetActiveTaskId(int|string $chatId): void
    {
        Cache::forget($this->buildActiveTaskCacheKey($chatId));
        $this->updateChatSessionState($chatId, [
            'active_task_id' => null,
            'last_message_at' => now()->toAtomString(),
        ]);
    }

    /**
     * Start or refresh one Telegram chat session for direct remote coding.
     *
     * @param  int|string  $chatId
     * @return array<string, mixed>
     */
    public function startChatSession(int|string $chatId): array
    {
        $existingSession = $this->getChatSession($chatId);

        if (is_array($existingSession)) {
            $session = array_merge($existingSession, [
                'enabled' => true,
                'last_message_at' => now()->toAtomString(),
            ]);
        } else {
            $session = [
                'enabled' => true,
                'mode' => 'codex_chat',
                'session_id' => (string) Str::uuid(),
                'started_at' => now()->toAtomString(),
                'last_message_at' => now()->toAtomString(),
                'active_task_id' => $this->getActiveTaskId($chatId),
            ];
        }

        Cache::forever($this->buildChatSessionCacheKey($chatId), $session);

        return $session;
    }

    /**
     * Append one structured timeline event to the current Telegram chat session.
     *
     * @param  int|string  $chatId
     * @param  array<string, mixed>  $event
     * @return void
     */
    public function rememberChatSessionEvent(int|string $chatId, array $event): void
    {
        $session = $this->getChatSession($chatId);

        if (! is_array($session) || ($session['enabled'] ?? false) !== true) {
            return;
        }

        $normalizedEvent = $this->normalizeChatSessionEvent($event);

        if ($normalizedEvent === null) {
            return;
        }

        $events = is_array($session['recent_events'] ?? null)
            ? array_values(array_filter($session['recent_events'], 'is_array'))
            : [];
        $events[] = $normalizedEvent;

        $configuredLimit = $this->runtimeConfigService->getRuntimeConfig()['chat_session_timeline_limit'] ?? 6;
        $limit = is_numeric($configuredLimit) ? (int) $configuredLimit : 6;

        if ($limit > 0 && count($events) > $limit) {
            $events = array_slice($events, -1 * $limit);
        }

        $session['recent_events'] = $events;
        $session['last_message_at'] = now()->toAtomString();

        Cache::forever($this->buildChatSessionCacheKey($chatId), $session);
    }

    /**
     * Read the current Telegram chat session state for one chat.
     *
     * @param  int|string  $chatId
     * @return array<string, mixed>|null
     */
    public function getChatSession(int|string $chatId): ?array
    {
        $value = Cache::get($this->buildChatSessionCacheKey($chatId));

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Determine whether one Telegram chat currently has chat mode enabled.
     *
     * @param  int|string  $chatId
     * @return bool
     */
    public function hasActiveChatSession(int|string $chatId): bool
    {
        $session = $this->getChatSession($chatId);

        return is_array($session) && ($session['enabled'] ?? false) === true;
    }

    /**
     * Touch one active Telegram chat session after receiving a new message.
     *
     * @param  int|string  $chatId
     * @return array<string, mixed>|null
     */
    public function touchChatSession(int|string $chatId): ?array
    {
        $session = $this->getChatSession($chatId);

        if (! is_array($session)) {
            return null;
        }

        $session['last_message_at'] = now()->toAtomString();
        $session['active_task_id'] = $this->getActiveTaskId($chatId);

        Cache::forever($this->buildChatSessionCacheKey($chatId), $session);

        return $session;
    }

    /**
     * Reset one Telegram chat session while keeping chat mode enabled.
     *
     * @param  int|string  $chatId
     * @return array<string, mixed>|null
     */
    public function resetChatSession(int|string $chatId): ?array
    {
        if (! $this->hasActiveChatSession($chatId)) {
            return null;
        }

        $session = [
            'enabled' => true,
            'mode' => 'codex_chat',
            'session_id' => (string) Str::uuid(),
            'started_at' => now()->toAtomString(),
            'last_message_at' => now()->toAtomString(),
            'active_task_id' => null,
            'recent_events' => [],
        ];

        Cache::forever($this->buildChatSessionCacheKey($chatId), $session);

        return $session;
    }

    /**
     * Forget the current Telegram chat session for one chat.
     *
     * @param  int|string  $chatId
     * @return void
     */
    public function forgetChatSession(int|string $chatId): void
    {
        Cache::forget($this->buildChatSessionCacheKey($chatId));
    }

    /**
     * Remember one pending Telegram interaction for clarification or confirmation.
     *
     * @param  int|string  $chatId
     * @param  array<string, mixed>  $interaction
     * @return void
     */
    public function rememberPendingInteraction(int|string $chatId, array $interaction): void
    {
        if ($interaction === []) {
            return;
        }

        Cache::put(
            $this->buildPendingInteractionCacheKey($chatId),
            $interaction,
            now()->addMinutes($this->resolvePendingInteractionTtlMinutes())
        );
    }

    /**
     * Resolve how long Telegram clarification/confirmation state should live.
     *
     * @return int
     */
    protected function resolvePendingInteractionTtlMinutes(): int
    {
        $ttl = config('opas.auto_coding.telegram.pending_interaction_ttl_minutes');

        return is_numeric($ttl) && (int) $ttl > 0 ? (int) $ttl : 1;
    }

    /**
     * Read the pending Telegram interaction remembered for one chat.
     *
     * @param  int|string  $chatId
     * @return array<string, mixed>|null
     */
    public function getPendingInteraction(int|string $chatId): ?array
    {
        $value = Cache::get($this->buildPendingInteractionCacheKey($chatId));

        if (! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Forget the pending Telegram interaction remembered for one chat.
     *
     * @param  int|string  $chatId
     * @return void
     */
    public function forgetPendingInteraction(int|string $chatId): void
    {
        Cache::forget($this->buildPendingInteractionCacheKey($chatId));
    }

    /**
     * Build one stable cache key for Telegram chat message history.
     *
     * @param  int|string  $chatId
     * @return string
     */
    protected function buildCacheKey(int|string $chatId): string
    {
        return sprintf('auto-coding:telegram:chat:%s:messages', trim((string) $chatId));
    }

    /**
     * Build one stable cache key for the active task remembered per Telegram chat.
     *
     * @param  int|string  $chatId
     * @return string
     */
    protected function buildActiveTaskCacheKey(int|string $chatId): string
    {
        return sprintf('auto-coding:telegram:chat:%s:active-task', trim((string) $chatId));
    }

    /**
     * Build one stable cache key for the pending interaction remembered per Telegram chat.
     *
     * @param  int|string  $chatId
     * @return string
     */
    protected function buildPendingInteractionCacheKey(int|string $chatId): string
    {
        return sprintf('auto-coding:telegram:chat:%s:pending-interaction', trim((string) $chatId));
    }

    /**
     * Build one stable cache key for the direct remote-coding session remembered per chat.
     *
     * @param  int|string  $chatId
     * @return string
     */
    protected function buildChatSessionCacheKey(int|string $chatId): string
    {
        return sprintf('auto-coding:telegram:chat:%s:session', trim((string) $chatId));
    }

    /**
     * Update one active chat session with new values when the session exists.
     *
     * @param  int|string  $chatId
     * @param  array<string, mixed>  $values
     * @return void
     */
    protected function updateChatSessionState(int|string $chatId, array $values): void
    {
        $session = $this->getChatSession($chatId);

        if (! is_array($session)) {
            return;
        }

        Cache::forever($this->buildChatSessionCacheKey($chatId), array_merge($session, $values));
    }

    /**
     * Normalize one mixed list into unique positive integer identifiers.
     *
     * @param  array<mixed, mixed>  $values
     * @return array<int, int>
     */
    protected function normalizePositiveIntegerList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $integerValue = $this->normalizePositiveInteger($value);

            if ($integerValue === null) {
                continue;
            }

            $normalized[] = $integerValue;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Normalize tracked message cache data while accepting the previous integer-only format.
     *
     * @param  array<mixed, mixed>  $values
     * @return array<int, array{message_id:int,created_at:string}>
     */
    protected function normalizeTrackedMessageRecords(array $values): array
    {
        $records = [];
        $seenMessageIds = [];

        foreach ($values as $value) {
            $messageId = is_array($value)
                ? $this->normalizePositiveInteger($value['message_id'] ?? null)
                : $this->normalizePositiveInteger($value);

            if ($messageId === null || in_array($messageId, $seenMessageIds, true)) {
                continue;
            }

            $createdAt = is_array($value) && is_string($value['created_at'] ?? null)
                ? trim((string) $value['created_at'])
                : '';

            $records[] = [
                'message_id' => $messageId,
                'created_at' => $createdAt !== '' ? $createdAt : now()->toAtomString(),
            ];
            $seenMessageIds[] = $messageId;
        }

        return $records;
    }

    /**
     * Normalize one mixed value into a positive integer id when possible.
     *
     * @param  mixed  $value
     * @return int|null
     */
    protected function normalizePositiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && is_numeric($value)) {
            $integerValue = (int) $value;

            return $integerValue > 0 ? $integerValue : null;
        }

        return null;
    }

    /**
     * Normalize one mixed timeline event into a cache-safe session entry.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>|null
     */
    protected function normalizeChatSessionEvent(array $event): ?array
    {
        $type = is_string($event['type'] ?? null) ? trim((string) $event['type']) : '';
        $taskId = $this->normalizePositiveInteger($event['task_id'] ?? null);
        $summary = is_string($event['summary'] ?? null) ? trim((string) $event['summary']) : '';
        $status = is_string($event['status'] ?? null) ? trim((string) $event['status']) : '';
        $createdAt = is_string($event['created_at'] ?? null) ? trim((string) $event['created_at']) : now()->toAtomString();

        if ($type === '' || $taskId === null || $summary === '') {
            return null;
        }

        return array_filter([
            'type' => $type,
            'task_id' => $taskId,
            'summary' => Str::limit($summary, 140),
            'status' => $status !== '' ? $status : null,
            'created_at' => $createdAt,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
