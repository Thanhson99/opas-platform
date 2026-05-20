<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\OAuthAuthProviderDriverInterface;
use App\Enums\AuthProviderType;
use App\Models\AuthProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class AbstractOAuthAuthProviderDriver extends AbstractAuthProviderDriver implements OAuthAuthProviderDriverInterface
{
    /**
     * Mark third-party social providers as OAuth2-style flows.
     *
     * @return AuthProviderType
     */
    public function type(): AuthProviderType
    {
        return AuthProviderType::OAuth2;
    }

    /**
     * Return the shared capability flags used by OAuth-based auth providers.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return [
            'login' => true,
            'register' => true,
            'link_account' => true,
            'requires_redirect' => true,
            'supports_email_verification' => true,
            'supports_password' => false,
        ];
    }

    /**
     * OAuth providers require a public client identifier and redirect URI before they are ready.
     *
     * @return list<string>
     */
    public function requiredPublicConfigKeys(): array
    {
        return [
            'client_id',
            'redirect_uri',
        ];
    }

    /**
     * OAuth providers require a client secret stored on the backend before they are ready.
     *
     * @return list<string>
     */
    public function requiredSecretConfigKeys(): array
    {
        return [
            'client_secret',
        ];
    }

    /**
     * Build frontend-safe metadata shared by OAuth providers, including the backend-generated callback URL.
     *
     * @param  AuthProvider  $provider
     * @return array<string, mixed>
     */
    public function publicMetadata(AuthProvider $provider): array
    {
        $config = $provider->public_config;
        $metadata = [
            'scopes' => $config['scopes'] ?? [],
            'pkce' => (bool) ($config['pkce'] ?? false),
            'redirect_url' => route('api.auth.providers.redirect', ['key' => $provider->key]),
            'callback_url' => route('api.auth.providers.callback', ['key' => $provider->key]),
        ];

        if (is_string($config['button_text'] ?? null) && trim($config['button_text']) !== '') {
            $metadata['button_text'] = $config['button_text'];
        }

        return $metadata;
    }

    /**
     * Build the third-party authorization URL from persisted provider config and runtime state.
     *
     * @param  AuthProvider  $provider
     * @param  string  $state
     * @return string
     */
    public function authorizationUrl(AuthProvider $provider, string $state): string
    {
        $config = $provider->public_config;
        $query = http_build_query([
            'client_id' => $config['client_id'] ?? '',
            'redirect_uri' => $config['redirect_uri'] ?? '',
            'response_type' => 'code',
            'scope' => implode(' ', $this->resolveScopes($provider)),
            'state' => $state,
            ...$this->authorizationParameters($provider),
        ]);

        return sprintf('%s?%s', $this->authorizationEndpoint(), $query);
    }

    /**
     * Exchange the authorization code and normalize the returned identity payload.
     *
     * @return array{
     *     provider_user_id:string,
     *     provider_email:?string,
     *     email_verified:bool,
     *     name:?string,
     *     access_token:string,
     *     refresh_token:?string,
     *     token_expires_at:?\DateTimeInterface,
     *     metadata:array<string,mixed>
     * }
     */
    public function exchangeCodeForIdentity(AuthProvider $provider, string $code): array
    {
        $tokenResponseRaw = Http::asForm()
            ->timeout($this->oauthHttpTimeoutSeconds())
            ->acceptJson()
            ->post($this->tokenEndpoint(), [
                'grant_type' => 'authorization_code',
                'client_id' => $provider->public_config['client_id'] ?? '',
                'client_secret' => $provider->secret_config['client_secret'] ?? '',
                'redirect_uri' => $provider->public_config['redirect_uri'] ?? '',
                'code' => $code,
                ...$this->tokenParameters($provider),
            ])
            ->throw()
            ->json();

        $accessTokenRaw = is_array($tokenResponseRaw) ? ($tokenResponseRaw['access_token'] ?? null) : null;

        if (! is_array($tokenResponseRaw) || ! is_string($accessTokenRaw)) {
            throw new RuntimeException('OAuth token response is invalid.');
        }

        /** @var array<string, mixed> $tokenResponse */
        $tokenResponse = $tokenResponseRaw;
        $accessToken = $accessTokenRaw;
        $profile = $this->fetchUserProfile($provider, $accessToken);

        return [
            'provider_user_id' => $this->resolveProviderUserId($profile),
            'provider_email' => $this->resolveProviderEmail($profile),
            'email_verified' => $this->resolveEmailVerified($profile),
            'name' => $this->resolveProviderName($profile),
            'access_token' => $accessToken,
            'refresh_token' => is_string($tokenResponse['refresh_token'] ?? null) ? $tokenResponse['refresh_token'] : null,
            'token_expires_at' => $this->resolveTokenExpiry($tokenResponse),
            'metadata' => $profile,
        ];
    }

    /**
     * Resolve the OAuth scopes from persisted provider config or driver defaults.
     *
     * @param  AuthProvider  $provider
     * @return list<string>
     */
    protected function resolveScopes(AuthProvider $provider): array
    {
        $configured = $provider->public_config['scopes'] ?? null;

        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter($configured, static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
        }

        return $this->defaultScopes();
    }

    /**
     * Return extra provider-specific authorization parameters.
     *
     * @param  AuthProvider  $provider
     * @return array<string, scalar>
     */
    protected function authorizationParameters(AuthProvider $provider): array
    {
        return [];
    }

    /**
     * Return extra provider-specific token exchange parameters.
     *
     * @param  AuthProvider  $provider
     * @return array<string, scalar>
     */
    protected function tokenParameters(AuthProvider $provider): array
    {
        return [];
    }

    /**
     * Fetch the normalized OAuth user profile from the provider API.
     *
     * @param  AuthProvider  $provider
     * @param  string  $accessToken
     * @return array<string, mixed>
     */
    protected function fetchUserProfile(AuthProvider $provider, string $accessToken): array
    {
        $responseRaw = Http::withToken($accessToken)
            ->timeout($this->oauthHttpTimeoutSeconds())
            ->acceptJson()
            ->get($this->userInfoEndpoint())
            ->throw()
            ->json();

        if (! is_array($responseRaw)) {
            throw new RuntimeException('OAuth user profile response is invalid.');
        }

        /** @var array<string, mixed> $response */
        $response = $responseRaw;

        return $response;
    }

    /**
     * Resolve the provider-specific user identifier from the profile payload.
     *
     * @param  array<string, mixed>  $profile
     * @return string
     */
    abstract protected function resolveProviderUserId(array $profile): string;

    /**
     * Resolve the primary email returned by the OAuth provider, if any.
     *
     * @param  array<string, mixed>  $profile
     * @return string|null
     */
    protected function resolveProviderEmail(array $profile): ?string
    {
        return is_string($profile['email'] ?? null) ? $profile['email'] : null;
    }

    /**
     * Determine whether the provider considers the email address verified.
     *
     * @param  array<string, mixed>  $profile
     * @return bool
     */
    protected function resolveEmailVerified(array $profile): bool
    {
        return (bool) ($profile['email_verified'] ?? false);
    }

    /**
     * Resolve the display name from the OAuth profile payload when available.
     *
     * @param  array<string, mixed>  $profile
     * @return string|null
     */
    protected function resolveProviderName(array $profile): ?string
    {
        return is_string($profile['name'] ?? null) ? $profile['name'] : null;
    }

    /**
     * Convert token lifetime metadata into an absolute expiration timestamp.
     *
     * @param  array<string, mixed>  $tokenResponse
     * @return \DateTimeInterface|null
     */
    protected function resolveTokenExpiry(array $tokenResponse): ?\DateTimeInterface
    {
        $expiresIn = $tokenResponse['expires_in'] ?? null;

        if (is_int($expiresIn)) {
            return CarbonImmutable::now()->addSeconds($expiresIn);
        }

        if (is_float($expiresIn)) {
            return CarbonImmutable::now()->addSeconds((int) $expiresIn);
        }

        if (is_string($expiresIn) && is_numeric($expiresIn)) {
            return CarbonImmutable::now()->addSeconds((int) $expiresIn);
        }

        return null;
    }

    /**
     * Return the provider authorization endpoint loaded from config.
     *
     * @return string
     */
    abstract protected function authorizationEndpoint(): string;

    /**
     * Return the provider token endpoint loaded from config.
     *
     * @return string
     */
    abstract protected function tokenEndpoint(): string;

    /**
     * Return the provider user info endpoint loaded from config.
     *
     * @return string
     */
    abstract protected function userInfoEndpoint(): string;

    /**
     * Return the default scopes applied when admin settings do not override them.
     *
     * @return list<string>
     */
    abstract protected function defaultScopes(): array;

    /**
     * Return a provider config string and fail fast when the configured value is missing or invalid.
     *
     * @param  string  $providerKey
     * @param  string  $configKey
     * @return string
     */
    protected function oauthProviderConfigString(string $providerKey, string $configKey): string
    {
        $value = config("opas.auth.oauth.providers.{$providerKey}.{$configKey}");

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                sprintf('OAuth config value [%s.%s] is missing or invalid.', $providerKey, $configKey),
            );
        }

        return $value;
    }

    /**
     * Return the configured OAuth HTTP timeout as an integer number of seconds.
     *
     * @return int
     */
    protected function oauthHttpTimeoutSeconds(): int
    {
        $timeout = config('opas.auth.oauth.http_timeout_seconds', 15);

        if (is_int($timeout)) {
            return $timeout;
        }

        if (is_float($timeout)) {
            return (int) $timeout;
        }

        if (is_string($timeout) && is_numeric($timeout)) {
            return (int) $timeout;
        }

        return 15;
    }
}
