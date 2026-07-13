<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDouyinVideoSelectionRequest extends FormRequest
{
    /**
     * Allow selection changes through route middleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for selected state.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'selected' => 'required|boolean',
        ];
    }
}
