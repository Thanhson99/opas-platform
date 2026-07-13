<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CrawlDouyinRequest extends FormRequest
{
    /**
     * Allow crawl requests through route middleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize crawl keyword input.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $keyword = $this->input('keyword');

        $this->merge([
            'keyword' => is_string($keyword) ? trim($keyword) : $keyword,
        ]);
    }

    /**
     * Get validation rules for crawl preview.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => 'bail|required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
        ];
    }
}
