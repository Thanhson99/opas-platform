<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDouyinKeywordRequest extends FormRequest
{
    /**
     * Allow authenticated browser users through route middleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize keyword text before validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $category = $this->input('category');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'category' => is_string($category) ? trim($category) : $category,
        ]);
    }

    /**
     * Get validation rules for keyword creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'bail|required|string|max:255',
            'category' => 'nullable|string|max:120',
        ];
    }
}
