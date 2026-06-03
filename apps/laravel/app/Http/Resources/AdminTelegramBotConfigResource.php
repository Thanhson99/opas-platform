<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TelegramBotConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTelegramBotConfigResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TelegramBotConfig $config */
        $config = $this->resource;
        /** @var array<string, mixed> $secretConfig */
        $secretConfig = $config->secret_config ?? [];

        return [
            'key' => $config->key,
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
            'public_config' => $config->public_config ?? [],
            'secret_status' => [
                'bot_token' => array_key_exists('bot_token', $secretConfig),
                'webhook_secret' => array_key_exists('webhook_secret', $secretConfig),
            ],
        ];
    }
}
