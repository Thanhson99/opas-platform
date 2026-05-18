<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Models\AuthProvider;

interface OAuthAuthProviderDriverInterface extends AuthProviderDriverInterface
{
    /**
     * Build the provider authorization URL that the frontend should redirect the browser to.
     *
     * @param  AuthProvider  $provider
     * @param  string  $state
     * @return string
     */
    public function authorizationUrl(AuthProvider $provider, string $state): string;

    /**
     * Exchange an OAuth authorization code for a normalized identity payload.
     *
     * @param  AuthProvider  $provider
     * @param  string  $code
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
    public function exchangeCodeForIdentity(AuthProvider $provider, string $code): array;
}
