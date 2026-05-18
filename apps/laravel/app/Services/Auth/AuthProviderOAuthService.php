<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Auth\Contracts\OAuthAuthProviderDriverInterface;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserAuthIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class AuthProviderOAuthService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AuthProviderService $authProviderService,
    ) {}

    /**
     * Build a provider authorization redirect and persist the anti-CSRF state in session.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return RedirectResponse
     */
    public function redirect(Request $request, string $key): RedirectResponse
    {
        $resolved = $this->resolveOAuthProvider($key);
        $state = Str::random(40);

        $request->session()->put($this->stateSessionKey($key), $state);

        return redirect()->away($resolved['driver']->authorizationUrl($resolved['provider'], $state));
    }

    /**
     * Validate the OAuth callback state, exchange the authorization code, and sign the user in.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return RedirectResponse
     */
    public function callback(Request $request, string $key): RedirectResponse
    {
        $resolved = $this->resolveOAuthProvider($key);
        $expectedState = $request->session()->pull($this->stateSessionKey($key));
        $actualState = $request->query('state');
        $code = $request->query('code');

        if (! is_string($expectedState) || ! is_string($actualState) || ! hash_equals($expectedState, $actualState)) {
            return $this->redirectToLogin('OAuth state validation failed.');
        }

        if (! is_string($code) || trim($code) === '') {
            return $this->redirectToLogin('OAuth authorization code is missing.');
        }

        $identityPayload = $resolved['driver']->exchangeCodeForIdentity($resolved['provider'], $code);
        $user = $this->resolveUser($resolved['provider']->key, $identityPayload);

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->to('/');
    }

    /**
     * Resolve an active OAuth-capable provider from the configured auth provider set.
     *
     * @param  string  $key
     * @return array{provider:\App\Models\AuthProvider,driver:OAuthAuthProviderDriverInterface,active:bool,ready:bool,issues:list<string>}
     */
    private function resolveOAuthProvider(string $key): array
    {
        $resolved = $this->authProviderService->resolve($key);

        if ($resolved === null || ! $resolved['active']) {
            throw new RuntimeException('Auth provider is not available.');
        }

        if (! $resolved['driver'] instanceof OAuthAuthProviderDriverInterface) {
            throw new RuntimeException(sprintf('Auth provider [%s] does not support OAuth.', $key));
        }

        return $resolved;
    }

    /**
     * Link or create a local user account from the provider identity payload.
     *
     * @param  string  $providerKey
     * @param  array{
     *     provider_user_id:string,
     *     provider_email:?string,
     *     email_verified:bool,
     *     name:?string,
     *     access_token:string,
     *     refresh_token:?string,
     *     token_expires_at:?\DateTimeInterface,
     *     metadata:array<string,mixed>
     * }  $identityPayload
     * @return User
     */
    private function resolveUser(string $providerKey, array $identityPayload): User
    {
        $identity = UserAuthIdentity::query()
            ->where('provider_key', $providerKey)
            ->where('provider_user_id', $identityPayload['provider_user_id'])
            ->first();

        if ($identity instanceof UserAuthIdentity) {
            $this->syncIdentity($identity, $identityPayload);

            return $identity->user()->firstOrFail();
        }

        $email = $identityPayload['provider_email'];
        $user = null;

        if (is_string($email) && trim($email) !== '') {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user instanceof User) {
            if (! is_string($email) || trim($email) === '') {
                throw new RuntimeException('OAuth provider did not return an email address.');
            }

            $user = User::query()->create([
                'name' => $this->resolveDisplayName($identityPayload, $email),
                'email' => $email,
                'password' => Str::password(32),
                'role' => UserRole::Member,
            ]);

            if ($identityPayload['email_verified']) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
        } elseif ($identityPayload['email_verified'] && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        UserAuthIdentity::query()->create([
            'user_id' => $user->id,
            'provider_key' => $providerKey,
            'provider_user_id' => $identityPayload['provider_user_id'],
            'provider_email' => $email,
            'metadata' => $identityPayload['metadata'],
            'access_token' => $identityPayload['access_token'],
            'refresh_token' => $identityPayload['refresh_token'],
            'token_expires_at' => $identityPayload['token_expires_at'],
        ]);

        return $user;
    }

    /**
     * Refresh an existing linked provider identity with the latest token and profile data.
     *
     * @param  UserAuthIdentity  $identity
     * @param  array{
     *     provider_user_id:string,
     *     provider_email:?string,
     *     email_verified:bool,
     *     name:?string,
     *     access_token:string,
     *     refresh_token:?string,
     *     token_expires_at:?\DateTimeInterface,
     *     metadata:array<string,mixed>
     * }  $identityPayload
     * @return void
     */
    private function syncIdentity(UserAuthIdentity $identity, array $identityPayload): void
    {
        $identity->fill([
            'provider_email' => $identityPayload['provider_email'],
            'metadata' => $identityPayload['metadata'],
            'access_token' => $identityPayload['access_token'],
            'refresh_token' => $identityPayload['refresh_token'],
            'token_expires_at' => $identityPayload['token_expires_at'],
        ])->save();
    }

    /**
     * Choose a display name from provider data or derive one from the email local-part.
     *
     * @param  array{
     *     name:?string
     * }  $identityPayload
     * @param  string  $email
     * @return string
     */
    private function resolveDisplayName(array $identityPayload, string $email): string
    {
        $name = $identityPayload['name'] ?? null;

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        return Str::before($email, '@');
    }

    /**
     * Build the session key used to store OAuth state per provider.
     *
     * @param  string  $key
     * @return string
     */
    private function stateSessionKey(string $key): string
    {
        return sprintf('auth.oauth_state.%s', $key);
    }

    /**
     * Redirect back to the login screen with an encoded auth error message.
     *
     * @param  string  $message
     * @return RedirectResponse
     */
    private function redirectToLogin(string $message): RedirectResponse
    {
        return redirect()->to('/login?auth_error='.urlencode($message));
    }
}
