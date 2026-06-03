<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Telegram;

use Illuminate\Support\Facades\Http;

class AutoCodingTelegramBotService
{
    public function __construct(
        private readonly AutoCodingTelegramTextService $textService,
        private readonly AutoCodingTelegramRuntimeConfigService $runtimeConfigService,
    ) {}

    /**
     * Register one Telegram webhook for the remote-control bot.
     *
     * @param  string  $url
     * @param  string|null  $secretToken
     * @param  bool  $dropPendingUpdates
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, ?string $secretToken = null, bool $dropPendingUpdates = false): array
    {
        $token = $this->resolveBotToken();

        if ($token === null) {
            return [
                'ok' => false,
                'description' => $this->textService->line('errors.bot_token_missing'),
            ];
        }

        $payload = array_filter([
            'url' => trim($url),
            'secret_token' => $this->resolveSecretToken($secretToken),
            'drop_pending_updates' => $dropPendingUpdates,
            'allowed_updates' => $this->resolveAllowedUpdates(),
        ], static fn (mixed $value): bool => $value !== null);

        $response = Http::asJson()->post($this->buildEndpoint($token, 'setWebhook'), $payload);

        return $this->normalizeTelegramResponse($response->json(), $response->successful());
    }

    /**
     * Read the current Telegram webhook state for the remote-control bot.
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        $token = $this->resolveBotToken();

        if ($token === null) {
            return [
                'ok' => false,
                'description' => $this->textService->line('errors.bot_token_missing'),
            ];
        }

        $response = Http::asJson()->post($this->buildEndpoint($token, 'getWebhookInfo'));

        return $this->normalizeTelegramResponse($response->json(), $response->successful());
    }

    /**
     * Remove the Telegram webhook registration for the remote-control bot.
     *
     * @param  bool  $dropPendingUpdates
     * @return array<string, mixed>
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        $token = $this->resolveBotToken();

        if ($token === null) {
            return [
                'ok' => false,
                'description' => $this->textService->line('errors.bot_token_missing'),
            ];
        }

        $response = Http::asJson()->post($this->buildEndpoint($token, 'deleteWebhook'), [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);

        return $this->normalizeTelegramResponse($response->json(), $response->successful());
    }

    /**
     * Register the default Telegram bot command list for remote auto-coding control.
     *
     * @return array<string, mixed>
     */
    public function setDefaultCommands(): array
    {
        $token = $this->resolveBotToken();

        if ($token === null) {
            return [
                'ok' => false,
                'description' => $this->textService->line('errors.bot_token_missing'),
            ];
        }

        $response = Http::asJson()->post($this->buildEndpoint($token, 'setMyCommands'), [
            'commands' => $this->buildDefaultCommandDefinitions(),
        ]);

        return $this->normalizeTelegramResponse($response->json(), $response->successful());
    }

    /**
     * Inspect recent Telegram updates for one raw token and extract candidate chat/user IDs.
     *
     * @param  string  $token
     * @param  bool  $deleteWebhookFirst
     * @return array<string, mixed>
     */
    public function inspectChatIdsFromToken(string $token, bool $deleteWebhookFirst = false): array
    {
        $normalizedToken = trim($token);

        if ($normalizedToken === '') {
            return [
                'ok' => false,
                'description' => $this->textService->line('errors.bot_token_missing'),
                'chats' => [],
                'users' => [],
            ];
        }

        $deleteWebhookResult = null;

        if ($deleteWebhookFirst) {
            $deleteWebhookResponse = Http::asJson()->post($this->buildEndpoint($normalizedToken, 'deleteWebhook'), [
                'drop_pending_updates' => false,
            ]);
            $deleteWebhookResult = $this->normalizeTelegramResponse(
                $deleteWebhookResponse->json(),
                $deleteWebhookResponse->successful()
            );
        }

        $response = Http::asJson()->post($this->buildEndpoint($normalizedToken, 'getUpdates'), [
            'limit' => 20,
            'timeout' => 0,
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member', 'chat_member'],
        ]);
        $payload = $this->normalizeTelegramResponse($response->json(), $response->successful());

        return array_merge($payload, [
            'needs_webhook_delete' => $this->isWebhookConflict($payload),
            'webhook_deleted' => $deleteWebhookResult,
        ], $this->extractChatIdCandidates($payload['result'] ?? []));
    }

    /**
     * Send one Telegram message when bot delivery is configured.
     *
     * @param  int|string  $chatId
     * @param  string  $text
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>|null
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): ?array
    {
        $token = $this->resolveBotToken();

        if ($token === null) {
            return null;
        }

        $payload = array_merge($options, [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ]);

        $response = Http::asJson()->post($this->buildEndpoint($token, 'sendMessage'), $payload);

        return $this->normalizeTelegramResponse($response->json(), $response->successful());
    }

    /**
     * Delete one bot-owned Telegram message from the current chat.
     *
     * @param  int|string  $chatId
     * @param  int  $messageId
     * @return array<string, mixed>|null
     */
    public function deleteMessage(int|string $chatId, int $messageId): ?array
    {
        $token = $this->resolveBotToken();

        if ($token === null || $messageId <= 0) {
            return null;
        }

        $response = Http::asJson()->post($this->buildEndpoint($token, 'deleteMessage'), [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        return $this->normalizeTelegramResponse($response->json(), $response->successful());
    }

    /**
     * Answer one Telegram callback query to stop the client-side loading state.
     *
     * @param  string|null  $callbackQueryId
     * @param  string|null  $text
     * @return void
     */
    public function answerCallbackQuery(?string $callbackQueryId, ?string $text = null): void
    {
        $token = $this->resolveBotToken();

        if ($token === null || ! is_string($callbackQueryId) || trim($callbackQueryId) === '') {
            return;
        }

        Http::asJson()->post($this->buildEndpoint($token, 'answerCallbackQuery'), array_filter([
            'callback_query_id' => trim($callbackQueryId),
            'text' => is_string($text) && trim($text) !== '' ? trim($text) : null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * Resolve the configured Telegram bot token.
     *
     * @return string|null
     */
    protected function resolveBotToken(): ?string
    {
        $token = $this->runtimeConfigService->getRuntimeConfig()['bot_token'] ?? null;

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    /**
     * Build one Telegram Bot API endpoint URL.
     *
     * @param  string  $token
     * @param  string  $method
     * @return string
     */
    protected function buildEndpoint(string $token, string $method): string
    {
        $defaults = $this->runtimeConfigService->getDefaultRuntimeConfig();
        $baseUrl = $this->runtimeConfigService->getRuntimeConfig()['api_base_url'] ?? $defaults['api_base_url'];
        $normalizedBaseUrl = is_string($baseUrl) && trim($baseUrl) !== ''
            ? rtrim(trim($baseUrl), '/')
            : rtrim(is_string($defaults['api_base_url'] ?? null) ? $defaults['api_base_url'] : 'https://api.telegram.org', '/');

        return sprintf('%s/bot%s/%s', $normalizedBaseUrl, $token, $method);
    }

    /**
     * Extract unique chats and users from Telegram getUpdates payload.
     *
     * @param  mixed  $updates
     * @return array{chats: array<int, array<string, mixed>>, users: array<int, array<string, mixed>>}
     */
    protected function extractChatIdCandidates(mixed $updates): array
    {
        $chats = [];
        $users = [];

        if (! is_array($updates)) {
            return ['chats' => [], 'users' => []];
        }

        foreach ($updates as $update) {
            if (! is_array($update)) {
                continue;
            }
            $normalizedUpdate = $this->normalizeAssocArray($update);

            foreach ($this->extractUpdateMessages($normalizedUpdate) as $message) {
                $chat = is_array($message['chat'] ?? null) ? $message['chat'] : null;
                $from = is_array($message['from'] ?? null) ? $message['from'] : null;

                if ($chat !== null) {
                    $this->storeCandidate($chats, $this->normalizeAssocArray($chat), 'chat');
                }

                if ($from !== null) {
                    $this->storeCandidate($users, $this->normalizeAssocArray($from), 'user');
                }
            }

            foreach (['my_chat_member', 'chat_member'] as $memberKey) {
                $memberUpdate = is_array($normalizedUpdate[$memberKey] ?? null) ? $normalizedUpdate[$memberKey] : null;

                if ($memberUpdate === null) {
                    continue;
                }
                $normalizedMemberUpdate = $this->normalizeAssocArray($memberUpdate);

                $chat = is_array($normalizedMemberUpdate['chat'] ?? null) ? $normalizedMemberUpdate['chat'] : null;
                $from = is_array($normalizedMemberUpdate['from'] ?? null) ? $normalizedMemberUpdate['from'] : null;

                if ($chat !== null) {
                    $this->storeCandidate($chats, $this->normalizeAssocArray($chat), 'chat');
                }

                if ($from !== null) {
                    $this->storeCandidate($users, $this->normalizeAssocArray($from), 'user');
                }
            }
        }

        return [
            'chats' => array_values($chats),
            'users' => array_values($users),
        ];
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<int, array<string, mixed>>
     */
    protected function extractUpdateMessages(array $update): array
    {
        $messages = [];

        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post'] as $key) {
            if (is_array($update[$key] ?? null)) {
                $messages[] = $this->normalizeAssocArray($update[$key]);
            }
        }

        $callbackQuery = is_array($update['callback_query'] ?? null) ? $update['callback_query'] : null;

        if ($callbackQuery !== null) {
            if (is_array($callbackQuery['message'] ?? null)) {
                $messages[] = $this->normalizeAssocArray($callbackQuery['message']);
            }

            if (is_array($callbackQuery['from'] ?? null)) {
                $messages[] = ['from' => $this->normalizeAssocArray($callbackQuery['from'])];
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $source
     * @param  string  $kind
     * @return void
     */
    protected function storeCandidate(array &$candidates, array $source, string $kind): void
    {
        $id = $source['id'] ?? null;

        if (! is_int($id) && ! is_string($id)) {
            return;
        }

        $normalizedId = (string) $id;
        $nameParts = array_filter([
            is_string($source['title'] ?? null) ? $source['title'] : null,
            is_string($source['first_name'] ?? null) ? $source['first_name'] : null,
            is_string($source['last_name'] ?? null) ? $source['last_name'] : null,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== '');
        $username = is_string($source['username'] ?? null) ? trim($source['username']) : '';
        $label = trim(implode(' ', $nameParts));

        if ($label === '' && $username !== '') {
            $label = '@'.$username;
        }

        $candidates[$normalizedId] = [
            'id' => $normalizedId,
            'kind' => $kind,
            'type' => is_string($source['type'] ?? null) ? $source['type'] : null,
            'label' => $label !== '' ? $label : $normalizedId,
            'username' => $username !== '' ? $username : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return bool
     */
    protected function isWebhookConflict(array $payload): bool
    {
        $description = is_string($payload['description'] ?? null)
            ? strtolower($payload['description'])
            : '';

        return ($payload['error_code'] ?? null) === 409
            || (str_contains($description, 'webhook')
                && str_contains($description, 'getupdates'));
    }

    /**
     * Resolve the Telegram webhook secret token used for bot registration.
     *
     * @param  string|null  $secretToken
     * @return string|null
     */
    protected function resolveSecretToken(?string $secretToken): ?string
    {
        if (is_string($secretToken) && trim($secretToken) !== '') {
            return trim($secretToken);
        }

        $configuredSecret = $this->runtimeConfigService->getRuntimeConfig()['webhook_secret'] ?? null;

        return is_string($configuredSecret) && trim($configuredSecret) !== ''
            ? trim($configuredSecret)
            : null;
    }

    /**
     * Resolve the Telegram update types that must reach the remote-control webhook.
     *
     * @return array<int, string>|null
     */
    protected function resolveAllowedUpdates(): ?array
    {
        $configuredUpdates = $this->runtimeConfigService->getRuntimeConfig()['allowed_updates'] ?? null;

        if (is_array($configuredUpdates)) {
            $normalizedUpdates = array_values(array_filter(array_map(
                static fn (mixed $value): string => is_string($value) ? trim($value) : '',
                $configuredUpdates
            ), static fn (string $value): bool => $value !== ''));

            if ($normalizedUpdates !== []) {
                return $normalizedUpdates;
            }
        }

        $defaults = $this->runtimeConfigService->getDefaultRuntimeConfig();
        $defaultUpdates = $defaults['allowed_updates'] ?? [];

        return is_array($defaultUpdates) ? $this->normalizeStringList($defaultUpdates) : [];
    }

    /**
     * Normalize one array into a string-keyed payload for Telegram JSON objects.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeAssocArray(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<int, string>
     */
    protected function normalizeStringList(array $values): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): string => is_string($value) ? trim($value) : '',
            $values
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * Normalize one Telegram API response into a predictable payload.
     *
     * @param  mixed  $responsePayload
     * @param  bool  $requestSucceeded
     * @return array<string, mixed>
     */
    protected function normalizeTelegramResponse(mixed $responsePayload, bool $requestSucceeded): array
    {
        if (! is_array($responsePayload)) {
            return [
                'ok' => $requestSucceeded,
                'result' => null,
                'description' => $requestSucceeded
                    ? $this->textService->line('errors.empty_response_payload')
                    : $this->textService->line('errors.request_failed'),
            ];
        }

        return [
            'ok' => ($responsePayload['ok'] ?? false) === true,
            'result' => is_array($responsePayload['result'] ?? null)
                ? $responsePayload['result']
                : ($responsePayload['result'] ?? null),
            'error_code' => is_int($responsePayload['error_code'] ?? null)
                ? $responsePayload['error_code']
                : null,
            'description' => is_string($responsePayload['description'] ?? null)
                ? $responsePayload['description']
                : null,
        ];
    }

    /**
     * Build the default Telegram bot command contract from the shared text catalog.
     *
     * @return array<int, array{command:string,description:string}>
     */
    protected function buildDefaultCommandDefinitions(): array
    {
        $commands = [
            'start',
            'chat_status',
            'stop',
            'chat_reset',
            'changes',
            'queue',
            'cancel',
            'cancel_all',
            'delete',
            'delete_all',
            'clear',
            'clear_all',
            'help',
        ];

        return array_map(fn (string $command): array => [
            'command' => $command,
            'description' => $this->textService->line(sprintf('bot_commands.%s', $command)),
        ], $commands);
    }
}
