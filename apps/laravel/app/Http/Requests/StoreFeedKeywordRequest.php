<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedKeywordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->input('keyword');
        $tags = $this->input('tags', []);

        $this->merge([
            'keyword' => is_string($keyword) ? trim($keyword) : $keyword,
            'tags' => is_array($tags)
                ? array_values(array_unique(array_filter(array_map(
                    static fn (mixed $tag): string => is_string($tag) ? trim($tag) : '',
                    $tags
                ))))
                : $tags,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => 'bail|required|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100|distinct',
        ];
    }
}
