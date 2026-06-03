<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminTelegramBotSecretRevealRequest extends FormRequest
{
    /**
     * Restrict secret reveal to authenticated admins.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Validate one admin secret-reveal confirmation payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'secret_key' => ['required', 'string', Rule::in(['bot_token', 'webhook_secret'])],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
