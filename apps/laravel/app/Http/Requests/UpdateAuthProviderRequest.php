<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAuthProviderRequest extends FormRequest
{
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
        return [
            'enabled' => ['sometimes', 'boolean'],
            'display_name' => ['sometimes', 'string', 'max:255'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100', Rule::in(self::ALLOWED_ICONS)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'visibility' => ['sometimes', 'string', Rule::in(['public', 'hidden', 'admin_only'])],
            'capabilities' => ['sometimes', 'array'],
            'public_config' => ['sometimes', 'array'],
            'secret_config' => ['sometimes', 'array'],
            'email_verification_mode' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(['required', 'optional', 'disabled']),
            ],
        ];
    }
}
