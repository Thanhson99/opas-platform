<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use Illuminate\Support\Str;

class AutoCodingAgentAuthService
{
    /**
     * Issue one new plain-text access token for a local auto-coding machine.
     *
     * @param  AutoCodingMachine  $machine
     * @return string
     */
    public function issueToken(AutoCodingMachine $machine): string
    {
        $plainToken = 'opas_agent_'.Str::random(64);

        $machine->forceFill([
            'access_token_hash' => hash('sha256', $plainToken),
            'access_token_last_used_at' => null,
        ])->save();

        return $plainToken;
    }

    /**
     * Resolve one local auto-coding machine from an incoming bearer token.
     *
     * @param  string|null  $bearerToken
     * @return AutoCodingMachine|null
     */
    public function authenticate(?string $bearerToken): ?AutoCodingMachine
    {
        if (! is_string($bearerToken) || trim($bearerToken) === '') {
            return null;
        }

        /** @var AutoCodingMachine|null $machine */
        $machine = AutoCodingMachine::query()
            ->where('access_token_hash', hash('sha256', trim($bearerToken)))
            ->first();

        if (! $machine instanceof AutoCodingMachine) {
            return null;
        }

        $machine->forceFill([
            'access_token_last_used_at' => now(),
        ])->save();

        return $machine;
    }
}
