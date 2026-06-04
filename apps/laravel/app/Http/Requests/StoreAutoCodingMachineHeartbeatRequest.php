<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutoCodingMachineHeartbeatRequest extends FormRequest
{
    /**
     * Restrict local auto-coding machine heartbeat updates to authenticated admins.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Return validation rules for one local auto-coding machine heartbeat payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'machine_key' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255'],
            'operating_system' => ['required', 'string', 'max:255'],
            'availability_status' => ['nullable', 'string', Rule::in(['idle', 'busy', 'draining', 'offline'])],
            'repository_path' => ['nullable', 'string', 'max:1000'],
            'capabilities' => ['nullable', 'array'],
            'workspace_bindings' => ['nullable', 'array'],
            'workspace_bindings.*.repository_path' => ['required', 'string', 'max:1000'],
            'workspace_bindings.*.workspace_path' => ['nullable', 'string', 'max:1000'],
            'workspace_bindings.*.active_branch' => ['nullable', 'string', 'max:255'],
            'max_parallel_tasks' => ['nullable', 'integer', 'min:1', 'max:10'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
