<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkDouyinVideoPostedRequest extends FormRequest
{
    /**
     * Allow posted transitions through route middleware.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get validation rules for posted transition.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delete_after_posted' => 'nullable|boolean',
        ];
    }
}
