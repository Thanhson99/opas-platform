<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Build the shared password rule for email/password accounts.
     *
     * @return Password
     */
    private function passwordRule(): Password
    {
        return Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    /**
     * Allow guests to submit the registration form.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for the registration form payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ];
    }

    /**
     * Return custom validation messages for registration failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email has already been registered. Please go to the login page.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min' => 'Password must contain at least 8 characters.',
            'password.mixed' => 'Password must include uppercase and lowercase letters.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character.',
        ];
    }
}
