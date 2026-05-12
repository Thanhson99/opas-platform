<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class UpdateCoinAlertSettingRequest
 *
 * Handles validation for updating coin alert settings.
 */
class UpdateCoinAlertSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Adjust if you want to add permission checks
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'threshold_percent' => 'nullable|numeric|min:0',
            'type' => 'required|string|in:preset,custom',
            'direction' => 'nullable|string|in:increase,decrease',
            'is_active' => 'required|boolean',
        ];
    }
}
