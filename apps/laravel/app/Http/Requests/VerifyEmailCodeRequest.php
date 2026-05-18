<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailCodeRequest extends FormRequest
{
    /**
     * Allow guests to submit email verification codes.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for the email verification code payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $configuredLength = config('opas.auth.email_verification.code_length', 6);
        $codeLength = is_int($configuredLength) ? max(4, min($configuredLength, 8)) : 6;

        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'string', sprintf('digits:%d', $codeLength)],
        ];
    }
}
