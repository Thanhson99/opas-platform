<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountProfileRequest extends FormRequest
{
    /**
     * Normalize the profile name before validation so HTML tags and noisy whitespace are not persisted.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (! is_string($name)) {
            return;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags($name)) ?? '');

        $this->merge([
            'name' => $normalized,
        ]);
    }

    /**
     * Restrict account settings changes to authenticated users.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Return validation rules for editable account profile fields.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
