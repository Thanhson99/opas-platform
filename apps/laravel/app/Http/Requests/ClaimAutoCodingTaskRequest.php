<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimAutoCodingTaskRequest extends FormRequest
{
    /**
     * Restrict local auto-coding task claiming to authenticated admins.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Return validation rules for one local auto-coding task claim request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'repository_path' => ['nullable', 'string', 'max:2048'],
            'execute' => ['nullable', 'boolean'],
        ];
    }
}
