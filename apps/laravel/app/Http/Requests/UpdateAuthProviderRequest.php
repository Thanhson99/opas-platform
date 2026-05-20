<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuthProviderRequest extends FormRequest
{
    private const ALLOWED_CAPABILITY_KEYS = [
        'login',
        'register',
        'link_account',
        'requires_redirect',
        'supports_email_verification',
        'supports_password',
    ];

    private const ALLOWED_ICONS = [
        'dashboard',
        'coins',
        'alerts',
        'keywords',
        'stocks',
        'videos',
        'menu',
        'shield',
        'mail',
        'google',
        'github',
        'facebook',
        'heart',
    ];

    private const ALLOWED_PUBLIC_CONFIG_KEYS = [
        'button_text',
        'client_id',
        'password_reset_enabled',
        'pkce',
        'redirect_uri',
        'scopes',
    ];

    private const ALLOWED_SECRET_CONFIG_KEYS = [
        'client_secret',
    ];

    /**
     * Restrict provider configuration changes to authenticated admins.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Return validation rules for provider configuration updates coming from admin settings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $emailVerificationModes = $this->route('key') === 'email'
            ? ['required']
            : ['required', 'optional', 'disabled'];

        return [
            'enabled' => ['sometimes', 'boolean'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100', Rule::in(self::ALLOWED_ICONS)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'visibility' => ['sometimes', 'string', Rule::in(['public', 'hidden', 'admin_only'])],
            'capabilities' => ['sometimes', 'array:'.implode(',', self::ALLOWED_CAPABILITY_KEYS)],
            'capabilities.login' => ['sometimes', 'boolean'],
            'capabilities.register' => ['sometimes', 'boolean'],
            'capabilities.link_account' => ['sometimes', 'boolean'],
            'capabilities.requires_redirect' => ['sometimes', 'boolean'],
            'capabilities.supports_email_verification' => ['sometimes', 'boolean'],
            'capabilities.supports_password' => ['sometimes', 'boolean'],
            'public_config' => ['sometimes', 'array:'.implode(',', self::ALLOWED_PUBLIC_CONFIG_KEYS)],
            'public_config.button_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'public_config.client_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'public_config.password_reset_enabled' => ['sometimes', 'boolean'],
            'public_config.pkce' => ['sometimes', 'boolean'],
            'public_config.redirect_uri' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'public_config.scopes' => ['sometimes', 'array'],
            'public_config.scopes.*' => ['string', 'max:100'],
            'secret_config' => ['sometimes', 'array:'.implode(',', self::ALLOWED_SECRET_CONFIG_KEYS)],
            'secret_config.client_secret' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'email_verification_mode' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in($emailVerificationModes),
            ],
        ];
    }
}
