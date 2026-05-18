<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

class GithubAuthProviderDriver extends AbstractOAuthAuthProviderDriver
{
    /**
     * Return the stable key used for GitHub OAuth configuration.
     *
     * @return string
     */
    public function key(): string
    {
        return 'github';
    }

    /**
     * Return the default admin/login label for the GitHub provider.
     *
     * @return string
     */
    public function defaultDisplayName(): string
    {
        return 'GitHub';
    }

    /**
     * Return the SPA icon name used when rendering GitHub login controls.
     *
     * @return string|null
     */
    public function defaultIcon(): ?string
    {
        return 'github';
    }

    /**
     * Return the GitHub authorization endpoint from configuration.
     *
     * @return string
     */
    protected function authorizationEndpoint(): string
    {
        return $this->oauthProviderConfigString('github', 'authorization_endpoint');
    }

    /**
     * Return the GitHub token exchange endpoint from configuration.
     *
     * @return string
     */
    protected function tokenEndpoint(): string
    {
        return $this->oauthProviderConfigString('github', 'token_endpoint');
    }

    /**
     * Return the GitHub user info endpoint from configuration.
     *
     * @return string
     */
    protected function userInfoEndpoint(): string
    {
        return $this->oauthProviderConfigString('github', 'user_info_endpoint');
    }

    /**
     * Return the default GitHub OAuth scopes for sign-in.
     *
     * @return list<string>
     */
    protected function defaultScopes(): array
    {
        return ['read:user', 'user:email'];
    }

    /**
     * Resolve the stable GitHub user identifier from the OAuth profile payload.
     *
     * @param  array<string, mixed>  $profile
     * @return string
     */
    protected function resolveProviderUserId(array $profile): string
    {
        $id = $profile['id'] ?? null;

        if (is_int($id) || is_float($id)) {
            return (string) $id;
        }

        if (! is_string($id) || trim($id) === '') {
            throw new \RuntimeException('GitHub user profile is missing the provider identifier.');
        }

        return $id;
    }

    /**
     * Resolve a display name from GitHub profile fields.
     *
     * @param  array<string, mixed>  $profile
     * @return string|null
     */
    protected function resolveProviderName(array $profile): ?string
    {
        if (is_string($profile['name'] ?? null) && trim($profile['name']) !== '') {
            return $profile['name'];
        }

        return is_string($profile['login'] ?? null) ? $profile['login'] : null;
    }

    /**
     * Treat a GitHub profile as verified when an email value is present.
     *
     * @param  array<string, mixed>  $profile
     * @return bool
     */
    protected function resolveEmailVerified(array $profile): bool
    {
        return is_string($profile['email'] ?? null) && trim($profile['email']) !== '';
    }

    /**
     * Fetch the GitHub profile and fall back to the dedicated emails endpoint when needed.
     *
     * @param  \App\Models\AuthProvider  $provider
     * @param  string  $accessToken
     * @return array<string, mixed>
     */
    protected function fetchUserProfile(\App\Models\AuthProvider $provider, string $accessToken): array
    {
        $profile = parent::fetchUserProfile($provider, $accessToken);

        if (! is_string($profile['email'] ?? null) || trim($profile['email']) === '') {
            $emailsResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->timeout($this->oauthHttpTimeoutSeconds())
                ->acceptJson()
                ->get($this->oauthProviderConfigString('github', 'user_emails_endpoint'))
                ->throw()
                ->json();

            if (is_array($emailsResponse)) {
                foreach ($emailsResponse as $email) {
                    if (
                        is_array($email)
                        && ($email['primary'] ?? false) === true
                        && is_string($email['email'] ?? null)
                    ) {
                        $profile['email'] = $email['email'];
                        $profile['email_verified'] = (bool) ($email['verified'] ?? false);
                        break;
                    }
                }
            }
        }

        return $profile;
    }
}
