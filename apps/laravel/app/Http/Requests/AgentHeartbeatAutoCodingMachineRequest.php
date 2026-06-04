<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AutoCoding\AutoCodingAgentAuthService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentHeartbeatAutoCodingMachineRequest extends FormRequest
{
    /**
     * Restrict agent heartbeat updates to authenticated machine tokens.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return app(AutoCodingAgentAuthService::class)->authenticate($this->bearerToken()) !== null;
    }

    /**
     * Return validation rules for one agent-facing machine heartbeat payload.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
