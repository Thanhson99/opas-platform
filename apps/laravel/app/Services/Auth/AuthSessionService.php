<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Http\Request;

class AuthSessionService
{
    public const LOGIN_PROVIDER_SESSION_KEY = 'auth.login_provider';

    /**
     * Store the provider key used to establish the current authenticated session.
     *
     * @param  Request  $request
     * @param  string  $providerKey
     * @return void
     */
    public function storeLoginProvider(Request $request, string $providerKey): void
    {
        $request->session()->put(self::LOGIN_PROVIDER_SESSION_KEY, $providerKey);
    }

    /**
     * Return the provider key associated with the current authenticated session when known.
     *
     * @param  Request  $request
     * @return string|null
     */
    public function currentLoginProvider(Request $request): ?string
    {
        $value = $request->session()->get(self::LOGIN_PROVIDER_SESSION_KEY);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Clear any remembered login provider from the current session.
     *
     * @param  Request  $request
     * @return void
     */
    public function clearLoginProvider(Request $request): void
    {
        $request->session()->forget(self::LOGIN_PROVIDER_SESSION_KEY);
    }
}
