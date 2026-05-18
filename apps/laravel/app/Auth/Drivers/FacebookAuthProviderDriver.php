<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

class FacebookAuthProviderDriver extends AbstractOAuthAuthProviderDriver
{
    /**
     * Return the stable key used for Facebook Login configuration.
     *
     * @return string
     */
    public function key(): string
    {
        return 'facebook';
    }

    /**
     * Return the default admin/login label for the Facebook provider.
     *
     * @return string
     */
    public function defaultDisplayName(): string
    {
        return 'Facebook';
    }

    /**
     * Return the SPA icon name used when rendering Facebook login controls.
     *
     * @return string|null
     */
    public function defaultIcon(): ?string
    {
        return 'facebook';
    }

    /**
     * Return the Facebook authorization endpoint from configuration.
     *
     * @return string
     */
    protected function authorizationEndpoint(): string
    {
        return $this->oauthProviderConfigString('facebook', 'authorization_endpoint');
    }

    /**
     * Return the Facebook token exchange endpoint from configuration.
     *
     * @return string
     */
    protected function tokenEndpoint(): string
    {
        return $this->oauthProviderConfigString('facebook', 'token_endpoint');
    }

    /**
     * Return the Facebook user info endpoint from configuration.
     *
     * @return string
     */
    protected function userInfoEndpoint(): string
    {
        return $this->oauthProviderConfigString('facebook', 'user_info_endpoint');
    }

    /**
     * Return the default Facebook OAuth scopes for sign-in.
     *
     * @return list<string>
     */
    protected function defaultScopes(): array
    {
        return ['email', 'public_profile'];
    }

    /**
     * Resolve the stable Facebook user identifier from the OAuth profile payload.
     *
     * @param  array<string, mixed>  $profile
     * @return string
     */
    protected function resolveProviderUserId(array $profile): string
    {
        $id = $profile['id'] ?? null;

        if (! is_string($id) || trim($id) === '') {
            throw new \RuntimeException('Facebook user profile is missing the provider identifier.');
        }

        return $id;
    }

    /**
     * Treat a Facebook profile as verified when an email value is present.
     *
     * @param  array<string, mixed>  $profile
     * @return bool
     */
    protected function resolveEmailVerified(array $profile): bool
    {
        return is_string($profile['email'] ?? null) && trim($profile['email']) !== '';
    }
}
