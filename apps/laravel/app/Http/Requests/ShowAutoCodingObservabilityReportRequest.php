<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowAutoCodingObservabilityReportRequest extends FormRequest
{
    /**
     * Allow authenticated admin middleware to own authorization.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return validation rules for the observability report request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'repository_path' => ['nullable', 'string', 'max:1000'],
            'machine_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
