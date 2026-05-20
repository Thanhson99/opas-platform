<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\AutoCoding\AutoCodingAgentAuthService;
use Illuminate\Foundation\Http\FormRequest;

class AgentClaimAutoCodingTaskRequest extends FormRequest
{
    /**
     * Restrict agent task claiming to authenticated machine tokens.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return app(AutoCodingAgentAuthService::class)->authenticate($this->bearerToken()) !== null;
    }

    /**
     * Return validation rules for one agent-facing task claim request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'execute' => ['nullable', 'boolean'],
        ];
    }
}
