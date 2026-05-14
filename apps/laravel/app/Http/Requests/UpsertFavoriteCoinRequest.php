<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertFavoriteCoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'symbol' => strtoupper((string) $this->route('symbol', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'regex:/^[A-Z0-9]{3,10}$/'],
        ];
    }
}
