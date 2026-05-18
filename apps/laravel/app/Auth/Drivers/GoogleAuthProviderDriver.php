<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

class GoogleAuthProviderDriver extends AbstractOAuthAuthProviderDriver
{
    /**
     * Return the stable key used for Google OAuth configuration.
     *
     * @return string
     */
    public function key(): string
    {
        return 'google';
    }

    /**
     * Return the default admin/login label for the Google provider.
     *
     * @return string
     */
    public function defaultDisplayName(): string
    {
        return 'Google';
    }

    /**
     * Return the SPA icon name used when rendering Google login controls.
     *
     * @return string|null
     */
    public function defaultIcon(): ?string
    {
        return 'google';
    }

    /**
     * Return the Google authorization endpoint from configuration.
     *
     * @return string
     */
    protected function authorizationEndpoint(): string
    {
        return $this->oauthProviderConfigString('google', 'authorization_endpoint');
    }

    /**
     * Return the Google token exchange endpoint from configuration.
     *
     * @return string
     */
    protected function tokenEndpoint(): string
    {
        return $this->oauthProviderConfigString('google', 'token_endpoint');
    }

    /**
     * Return the Google user info endpoint from configuration.
     *
     * @return string
     */
    protected function userInfoEndpoint(): string
    {
        return $this->oauthProviderConfigString('google', 'user_info_endpoint');
    }

    /**
     * Return the default Google OAuth scopes for sign-in.
     *
     * @return list<string>
     */
    protected function defaultScopes(): array
    {
        return ['openid', 'email', 'profile'];
    }

    /**
     * Return extra authorization parameters required by Google.
     *
     * @param  \App\Models\AuthProvider  $provider
     * @return array<string, scalar>
     */
    protected function authorizationParameters(\App\Models\AuthProvider $provider): array
    {
        return [
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
    }

    /**
     * Resolve the stable Google user identifier from the OAuth profile payload.
     *
     * @param  array<string, mixed>  $profile
     * @return string
     */
    protected function resolveProviderUserId(array $profile): string
    {
        $id = $profile['sub'] ?? null;

        if (! is_string($id) || trim($id) === '') {
            throw new \RuntimeException('Google user profile is missing the subject identifier.');
        }

        return $id;
    }
}
