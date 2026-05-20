<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'repository_path' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
