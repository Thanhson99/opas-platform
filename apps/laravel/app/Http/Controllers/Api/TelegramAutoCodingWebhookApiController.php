<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramAccessControlService;
use App\Services\AutoCoding\Telegram\AutoCodingTelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramAutoCodingWebhookApiController extends Controller
{
    /**
     * Handle one Telegram webhook update for the auto-coding remote-control flow.
     *
     * @param  Request  $request
     * @param  AutoCodingTelegramWebhookService  $webhookService
     * @param  AutoCodingTelegramAccessControlService  $accessControlService
     * @return JsonResponse
     */
    public function store(
        Request $request,
        AutoCodingTelegramWebhookService $webhookService,
        AutoCodingTelegramAccessControlService $accessControlService,
    ): JsonResponse {
        abort_unless(
            $accessControlService->hasValidWebhookSecret($request->header('X-Telegram-Bot-Api-Secret-Token')),
            Response::HTTP_FORBIDDEN,
            'Telegram webhook secret is invalid.'
        );

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $webhookService->handle($payload);

        return response()->json([
            'ok' => true,
        ]);
    }
}
