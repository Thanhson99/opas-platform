<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * Allow anyone with an email address to request a password reset link.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for the forgot password payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
