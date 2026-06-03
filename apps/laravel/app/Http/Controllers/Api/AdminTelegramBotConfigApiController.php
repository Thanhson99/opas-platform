<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminTelegramBotSecretRevealRequest;
use App\Http\Requests\AdminTelegramWebhookOperationRequest;
use App\Http\Requests\StoreTelegramBotConfigRequest;
use App\Http\Requests\UpdateTelegramBotConfigRequest;
use App\Http\Resources\AdminTelegramBotConfigAuditResource;
use App\Http\Resources\AdminTelegramBotConfigResource;
use App\Models\TelegramBotConfig;
use App\Models\User;
use App\Repositories\AutoCoding\Interfaces\TelegramBotConfigRepositoryInterface;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramBotAuditService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramBotConfigService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramBotService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramNotificationService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramRuntimeConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminTelegramBotConfigApiController extends Controller
{
    /**
     * @return void
     */
    public function __construct(
        private readonly TelegramBotConfigRepositoryInterface $telegramBotConfigRepository,
        private readonly AutoCodingTelegramBotConfigService $telegramBotConfigService,
        private readonly AutoCodingTelegramBotAuditService $telegramBotAuditService,
        private readonly AutoCodingTelegramRuntimeConfigService $runtimeConfigService,
        private readonly AutoCodingTelegramBotService $telegramBotService,
        private readonly AutoCodingTelegramNotificationService $telegramNotificationService,
    ) {}

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return AdminTelegramBotConfigResource::collection($this->telegramBotConfigRepository->getOrdered());
    }

    /**
     * @param  StoreTelegramBotConfigRequest  $request
     * @return AdminTelegramBotConfigResource
     */
    public function store(StoreTelegramBotConfigRequest $request): AdminTelegramBotConfigResource
    {
        $actor = $request->user();
        $config = $this->telegramBotConfigService->create(
            $request->validated(),
            $actor instanceof User ? $actor : null,
        );

        return new AdminTelegramBotConfigResource($config);
    }

    /**
     * @param  UpdateTelegramBotConfigRequest  $request
     * @param  string  $key
     * @return AdminTelegramBotConfigResource
     */
    public function update(UpdateTelegramBotConfigRequest $request, string $key): AdminTelegramBotConfigResource
    {
        $config = $this->telegramBotConfigRepository->findByKey($key);

        abort_if(! $config instanceof TelegramBotConfig, 404);
        $actor = $request->user();
        $wasEnabled = (bool) $config->enabled;

        $updatedConfig = $this->telegramBotConfigService->update(
            $config,
            $request->validated(),
            $actor instanceof User ? $actor : null,
        );

        if (! $wasEnabled && $updatedConfig->enabled) {
            $this->runtimeConfigService->forgetCachedRuntimeConfig();
            $this->sendStartupMenuToAllowedChats($updatedConfig);
        }

        return new AdminTelegramBotConfigResource($updatedConfig);
    }

    /**
     * Remove one Telegram bot config from admin management.
     *
     * @param  string  $key
     * @return JsonResponse
     */
    public function destroy(string $key): JsonResponse
    {
        $config = $this->telegramBotConfigRepository->findByKey($key);

        abort_if(! $config instanceof TelegramBotConfig, 404);

        $this->telegramBotConfigService->delete($config);

        return response()->json([
            'message' => 'Telegram bot deleted successfully.',
        ]);
    }

    /**
     * Return the current Telegram runtime config without exposing secrets.
     *
     * @return JsonResponse
     */
    public function runtime(): JsonResponse
    {
        $runtimeConfig = $this->runtimeConfigService->getRuntimeConfig();
        $defaults = $this->runtimeConfigService->getDefaultRuntimeConfig();

        return response()->json([
            'data' => [
                'source' => $runtimeConfig['source'] ?? $defaults['source'],
                'key' => $runtimeConfig['key'] ?? $defaults['key'],
                'display_name' => $runtimeConfig['display_name'] ?? $defaults['display_name'],
                'purpose' => $runtimeConfig['purpose'] ?? $defaults['purpose'],
                'environment' => $runtimeConfig['environment'] ?? $defaults['environment'],
                'machine_group' => $runtimeConfig['machine_group'] ?? null,
                'enabled' => ($runtimeConfig['enabled'] ?? false) === true,
                'locale' => $runtimeConfig['locale'] ?? $defaults['locale'],
                'api_base_url' => $runtimeConfig['api_base_url'] ?? $defaults['api_base_url'],
                'allowed_updates' => $runtimeConfig['allowed_updates'] ?? $defaults['allowed_updates'],
                'allowed_chat_ids' => $runtimeConfig['allowed_chat_ids'] ?? [],
                'allowed_user_ids' => $runtimeConfig['allowed_user_ids'] ?? [],
                'allowed_actions' => $runtimeConfig['allowed_actions'] ?? [],
                'chat_history_limit' => $runtimeConfig['chat_history_limit'] ?? $defaults['chat_history_limit'],
                'chat_session_timeline_limit' => $runtimeConfig['chat_session_timeline_limit'] ?? $defaults['chat_session_timeline_limit'],
                'secret_status' => [
                    'bot_token' => is_string($runtimeConfig['bot_token'] ?? null)
                        && trim((string) $runtimeConfig['bot_token']) !== '',
                    'webhook_secret' => is_string($runtimeConfig['webhook_secret'] ?? null)
                        && trim((string) $runtimeConfig['webhook_secret']) !== '',
                ],
            ],
        ]);
    }

    /**
     * Return the recent audit activity for one Telegram bot config.
     *
     * @param  string  $key
     * @return JsonResponse
     */
    public function audits(string $key): JsonResponse
    {
        $config = $this->telegramBotConfigRepository->findByKey($key);

        abort_if(! $config instanceof TelegramBotConfig, 404);

        return response()->json([
            'data' => AdminTelegramBotConfigAuditResource::collection(
                $this->telegramBotAuditService->listRecent($config)
            ),
        ]);
    }

    /**
     * Reveal one stored Telegram bot secret after current-password confirmation.
     *
     * @param  AdminTelegramBotSecretRevealRequest  $request
     * @param  string  $key
     * @return JsonResponse
     */
    public function revealSecret(AdminTelegramBotSecretRevealRequest $request, string $key): JsonResponse
    {
        $config = $this->telegramBotConfigRepository->findByKey($key);

        abort_if(! $config instanceof TelegramBotConfig, 404);
        $actor = $request->user();

        if (! $actor instanceof User || $actor->password === '') {
            throw ValidationException::withMessages([
                'password' => ['Your current account cannot confirm secret access with a password.'],
            ]);
        }

        $validated = $request->validated();
        $password = $validated['password'] ?? null;

        if (! is_string($password) || ! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages([
                'password' => ['The current password is incorrect.'],
            ]);
        }

        $secretKey = is_string($validated['secret_key'] ?? null) ? $validated['secret_key'] : 'bot_token';
        $secretConfig = $config->secret_config;
        $secretValue = $secretConfig[$secretKey] ?? null;

        if (! is_string($secretValue) || trim($secretValue) === '') {
            throw ValidationException::withMessages([
                'secret_key' => ['The requested Telegram bot secret is not stored.'],
            ]);
        }

        $this->telegramBotAuditService->recordRuntimeOperation(
            $config,
            'secret_revealed',
            ['ok' => true, 'description' => null],
            $actor,
            ['secret_key' => $secretKey],
        );

        return response()->json([
            'data' => [
                'secret_key' => $secretKey,
                'value' => $secretValue,
            ],
        ]);
    }

    /**
     * Return the current Telegram webhook state for the active runtime bot.
     *
     * @return JsonResponse
     */
    public function webhook(): JsonResponse
    {
        return response()->json([
            'data' => $this->telegramBotService->getWebhookInfo(),
        ]);
    }

    /**
     * Register one Telegram webhook for the active runtime bot.
     *
     * @param  AdminTelegramWebhookOperationRequest  $request
     * @return JsonResponse
     */
    public function registerWebhook(AdminTelegramWebhookOperationRequest $request): JsonResponse
    {
        $result = $this->telegramBotService->setWebhook(
            $this->resolveWebhookUrl($request),
            null,
            $request->boolean('drop_pending_updates'),
        );

        $this->recordRuntimeAudit(
            'webhook_registered',
            $result,
            $request->user(),
            ['url' => $this->resolveWebhookUrl($request)]
        );

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Remove the Telegram webhook for the active runtime bot.
     *
     * @param  AdminTelegramWebhookOperationRequest  $request
     * @return JsonResponse
     */
    public function deleteWebhook(AdminTelegramWebhookOperationRequest $request): JsonResponse
    {
        $result = $this->telegramBotService->deleteWebhook(
            $request->boolean('drop_pending_updates'),
        );

        $this->recordRuntimeAudit('webhook_deleted', $result, $request->user());

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Sync the default Telegram command set for the active runtime bot.
     *
     * @return JsonResponse
     */
    public function syncCommands(): JsonResponse
    {
        $result = $this->telegramBotService->setDefaultCommands();
        $this->recordRuntimeAudit('commands_synced', $result, request()->user());

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Inspect a raw Telegram bot token and return recent chat/user IDs from getUpdates.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function inspectChatIds(Request $request): JsonResponse
    {
        $request->validate([
            'bot_token' => ['required', 'string', 'max:4096'],
            'delete_webhook' => ['sometimes', 'boolean'],
        ]);
        $botToken = $request->string('bot_token')->trim()->toString();

        return response()->json([
            'data' => $this->telegramBotService->inspectChatIdsFromToken(
                $botToken,
                $request->boolean('delete_webhook')
            ),
        ]);
    }

    /**
     * Resolve one validated webhook URL from the admin operation payload.
     *
     * @param  AdminTelegramWebhookOperationRequest  $request
     * @return string
     */
    protected function resolveWebhookUrl(AdminTelegramWebhookOperationRequest $request): string
    {
        $url = $request->validated('url');

        return is_string($url) ? $url : '';
    }

    /**
     * Persist one audit entry for the active runtime bot when it exists.
     *
     * @param  string  $action
     * @param  array<string, mixed>  $result
     * @param  mixed  $actor
     * @param  array<string, mixed>  $context
     * @return void
     */
    protected function recordRuntimeAudit(string $action, array $result, mixed $actor, array $context = []): void
    {
        $config = $this->telegramBotConfigRepository->findDefault();

        if (! $config instanceof TelegramBotConfig) {
            return;
        }

        $this->telegramBotAuditService->recordRuntimeOperation(
            $config,
            $action,
            $result,
            $actor instanceof User ? $actor : null,
            $context,
        );
    }

    /**
     * Send a root command menu to configured chats after an admin enables one bot.
     *
     * @param  TelegramBotConfig  $config
     * @return void
     */
    protected function sendStartupMenuToAllowedChats(TelegramBotConfig $config): void
    {
        foreach ($config->allowed_chat_ids as $chatId) {
            $normalizedChatId = trim((string) $chatId);

            if ($normalizedChatId === '') {
                continue;
            }

            $this->telegramNotificationService->sendStartupMenu($normalizedChatId);
        }
    }
}
