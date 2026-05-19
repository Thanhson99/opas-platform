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
        private readonly AuthSessionService $authSessionService,
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
        $this->storeLinkTargetUser($request, $key);

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
        $user = $this->resolveCallbackUser($request, $resolved['provider']->key, $identityPayload);

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->authSessionService->storeLoginProvider($request, $resolved['provider']->key);

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
        $identity = $this->findIdentityByProviderUser($providerKey, $identityPayload['provider_user_id']);

        if ($identity instanceof UserAuthIdentity) {
            $this->syncIdentity($identity, $identityPayload);

            return $identity->user()->firstOrFail();
        }

        $email = $this->requireVerifiedProviderEmail($identityPayload);
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => $this->resolveDisplayName($identityPayload, $email),
                'email' => $email,
                'password' => Str::password(32),
                'role' => UserRole::Member,
            ]);

            $this->markUserVerifiedIfNeeded($user);
        }

        $this->markUserVerifiedIfNeeded($user);
        $this->createIdentity($user, $providerKey, $identityPayload, $email);

        return $user;
    }

    /**
     * Resolve the user for an OAuth callback, distinguishing between login and explicit account linking.
     *
     * @param  Request  $request
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
    private function resolveCallbackUser(Request $request, string $providerKey, array $identityPayload): User
    {
        $linkTargetUserId = $this->pullLinkTargetUserId($request, $providerKey);

        if ($linkTargetUserId !== null) {
            return $this->linkIdentityToCurrentUser($linkTargetUserId, $providerKey, $identityPayload);
        }

        return $this->resolveUser($providerKey, $identityPayload);
    }

    /**
     * Link an OAuth identity to the current authenticated account instead of switching accounts.
     *
     * @param  int  $userId
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
    private function linkIdentityToCurrentUser(int $userId, string $providerKey, array $identityPayload): User
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            throw new RuntimeException('The account selected for provider linking could not be found.');
        }

        $email = $this->requireVerifiedProviderEmail($identityPayload);
        $this->assertProviderEmailMatchesUser($user, $email);

        $identity = $this->findIdentityByProviderUser($providerKey, $identityPayload['provider_user_id']);

        if ($identity instanceof UserAuthIdentity) {
            if ($identity->user_id !== $user->id) {
                throw new RuntimeException('OAuth identity is already linked to another account.');
            }

            $this->syncIdentity($identity, $identityPayload);
            $this->markUserVerifiedIfNeeded($user);

            return $user->refresh();
        }

        $existingProviderIdentity = UserAuthIdentity::query()
            ->where('user_id', $user->id)
            ->where('provider_key', $providerKey)
            ->first();

        if ($existingProviderIdentity instanceof UserAuthIdentity) {
            throw new RuntimeException('This login provider is already linked to the current account.');
        }

        $this->markUserVerifiedIfNeeded($user);
        $this->createIdentity($user, $providerKey, $identityPayload, $email);

        return $user->refresh();
    }

    /**
     * Require a verified provider email before linking or creating a local user by email address.
     *
     * @param  array{
     *     provider_email:?string,
     *     email_verified:bool
     * }  $identityPayload
     * @return string
     */
    private function requireVerifiedProviderEmail(array $identityPayload): string
    {
        $email = $identityPayload['provider_email'];

        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException('OAuth provider did not return an email address.');
        }

        if (! $identityPayload['email_verified']) {
            throw new RuntimeException('OAuth provider email address is not verified.');
        }

        return $email;
    }

    /**
     * Require the provider email to match the current account before linking an OAuth identity.
     *
     * @param  User  $user
     * @param  string  $providerEmail
     * @return void
     */
    private function assertProviderEmailMatchesUser(User $user, string $providerEmail): void
    {
        if (mb_strtolower($user->email) === mb_strtolower($providerEmail)) {
            return;
        }

        throw new RuntimeException('OAuth provider email does not match the current account email.');
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
     * Persist a new OAuth identity for the resolved local account.
     *
     * @param  User  $user
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
     * @param  string  $email
     * @return void
     */
    private function createIdentity(User $user, string $providerKey, array $identityPayload, string $email): void
    {
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
    }

    /**
     * Refresh local verification state when a trusted OAuth provider confirms the account email.
     *
     * @param  User  $user
     * @return void
     */
    private function markUserVerifiedIfNeeded(User $user): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
    }

    /**
     * Find a persisted OAuth identity by provider key and provider user identifier.
     *
     * @param  string  $providerKey
     * @param  string  $providerUserId
     * @return UserAuthIdentity|null
     */
    private function findIdentityByProviderUser(string $providerKey, string $providerUserId): ?UserAuthIdentity
    {
        return UserAuthIdentity::query()
            ->where('provider_key', $providerKey)
            ->where('provider_user_id', $providerUserId)
            ->first();
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
     * Store the currently authenticated user as the explicit OAuth link target when applicable.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return void
     */
    private function storeLinkTargetUser(Request $request, string $key): void
    {
        $user = $request->user();

        if ($user instanceof User) {
            $request->session()->put($this->linkSessionKey($key), $user->id);

            return;
        }

        $request->session()->forget($this->linkSessionKey($key));
    }

    /**
     * Pull the pending OAuth link target user identifier from the session for one provider callback.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return int|null
     */
    private function pullLinkTargetUserId(Request $request, string $key): ?int
    {
        $value = $request->session()->pull($this->linkSessionKey($key));

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Build the session key used to store the pending authenticated link target per provider.
     *
     * @param  string  $key
     * @return string
     */
    private function linkSessionKey(string $key): string
    {
        return sprintf('auth.oauth_link.%s', $key);
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
