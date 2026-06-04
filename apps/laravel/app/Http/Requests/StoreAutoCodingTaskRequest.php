<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutoCodingTaskRequest extends FormRequest
{
    /**
     * Restrict local auto-coding task creation to authenticated admins.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Return validation rules for local auto-coding task creation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:255'],
            'issue_key' => ['nullable', 'string', 'max:32'],
            'repository_path' => ['nullable', 'string', 'max:2048'],
            'preferred_machine_key' => ['nullable', 'string', 'max:255'],
            'required_capabilities' => ['nullable', 'array'],
            'required_capabilities.*' => ['required', 'string', 'max:120'],
            'validate' => ['nullable', 'boolean'],
            'provider' => ['nullable', 'string', Rule::in(['null', 'ollama', 'codex'])],
            'provider_options' => ['nullable', 'array'],
            'provider_options.model' => ['nullable', 'string', 'max:120'],
            'dirty_workspace_policy' => ['nullable', 'string', Rule::in(['warn', 'block', 'allow'])],
            'scope_paths' => ['nullable', 'array'],
            'scope_paths.*' => ['required', 'string', 'max:2048'],
            'scope_policy' => ['nullable', 'string', Rule::in(['warn', 'block', 'allow'])],
        ];
    }
}
