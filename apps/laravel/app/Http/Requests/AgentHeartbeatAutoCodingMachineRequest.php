<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AutoCoding\AutoCodingAgentAuthService;
use Illuminate\Foundation\Http\FormRequest;

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
            'repository_path' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
