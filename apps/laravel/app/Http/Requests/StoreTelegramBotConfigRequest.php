<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTelegramBotConfigRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:telegram_bot_configs,key'],
            'display_name' => ['required', 'string', 'max:255'],
            'purpose' => ['sometimes', 'string', Rule::in(['remote_control', 'support', 'alerts', 'operations'])],
            'environment' => ['sometimes', 'string', Rule::in(['local', 'staging', 'production', 'shared'])],
            'machine_group' => ['sometimes', 'nullable', 'string', 'max:100'],
            'enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', Rule::in(['en', 'vi'])],
            'api_base_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'allowed_chat_ids' => ['sometimes', 'array'],
            'allowed_chat_ids.*' => ['string', 'max:255'],
            'allowed_user_ids' => ['sometimes', 'array'],
            'allowed_user_ids.*' => ['string', 'max:255'],
            'allowed_actions' => ['sometimes', 'array'],
            'allowed_actions.*' => ['string', 'max:100'],
            'public_config' => ['sometimes', 'array:allowed_updates,bot_username,description,chat_history_limit,chat_session_timeline_limit'],
            'public_config.allowed_updates' => ['sometimes', 'array'],
            'public_config.allowed_updates.*' => ['string', 'max:100'],
            'public_config.bot_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'public_config.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'public_config.chat_history_limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'public_config.chat_session_timeline_limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'secret_config' => ['sometimes', 'array:bot_token,webhook_secret'],
            'secret_config.bot_token' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'secret_config.webhook_secret' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ];
    }
}
