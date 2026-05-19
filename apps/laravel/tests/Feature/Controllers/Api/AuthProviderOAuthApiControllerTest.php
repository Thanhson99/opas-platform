<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Models\AuthProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verify OAuth redirect and callback handling for public auth providers.
 */
class AuthProviderOAuthApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ready Google providers should redirect users to the Google authorization endpoint.
     *
     * @return void
     */
    public function test_google_redirect_sends_user_to_provider_authorization_url(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => route('api.auth.providers.callback', ['key' => 'google']),
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        $response = $this->get(route('api.auth.providers.redirect', ['key' => 'google']));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response->headers->get('Location', ''));
    }

    /**
     * A successful Google callback should sign the user in and persist the linked identity.
     *
     * @return void
     */
    public function test_google_callback_logs_user_in_and_persists_identity(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => route('api.auth.providers.callback', ['key' => 'google']),
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-123',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'email' => 'google@example.com',
                'email_verified' => true,
                'name' => 'Google User',
            ]),
        ]);

        $response = $this
            ->withSession(['auth.oauth_state.google' => 'state-123'])
            ->get(route('api.auth.providers.callback', [
                'key' => 'google',
                'state' => 'state-123',
                'code' => 'oauth-code-123',
            ]));

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'name' => 'Google User',
        ]);
        $this->assertDatabaseHas('user_auth_identities', [
            'provider_key' => 'google',
            'provider_user_id' => 'google-user-1',
            'provider_email' => 'google@example.com',
        ]);

        $meResponse = $this->getJson(route('api.auth.me'));

        $meResponse->assertOk()
            ->assertJsonPath('data.email', 'google@example.com')
            ->assertJsonPath('data.current_sign_in_provider.key', 'google')
            ->assertJsonPath('data.current_sign_in_provider.display_name', 'Google')
            ->assertJsonPath('data.current_sign_in_provider.icon', 'google');
    }

    /**
     * Disabled providers must not expose a working OAuth redirect entrypoint.
     *
     * @return void
     */
    public function test_google_redirect_returns_not_found_when_provider_is_disabled(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => false,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => route('api.auth.providers.callback', ['key' => 'google']),
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        $response = $this->get(route('api.auth.providers.redirect', ['key' => 'google']));

        $response->assertNotFound();
    }

    /**
     * Upstream token exchange failures should return safely to the login screen.
     *
     * @return void
     */
    public function test_google_callback_redirects_back_to_login_when_token_exchange_fails(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => route('api.auth.providers.callback', ['key' => 'google']),
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $response = $this
            ->withSession(['auth.oauth_state.google' => 'state-123'])
            ->get(route('api.auth.providers.callback', [
                'key' => 'google',
                'state' => 'state-123',
                'code' => 'oauth-code-123',
            ]));

        $response->assertRedirectContains('/login?auth_error=');
        $this->assertGuest();
    }

    /**
     * Unverified provider emails must not be linked onto an existing local account.
     *
     * @return void
     */
    public function test_google_callback_does_not_link_existing_user_when_provider_email_is_not_verified(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => route('api.auth.providers.callback', ['key' => 'google']),
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        $user = User::factory()->create([
            'email' => 'google@example.com',
            'email_verified_at' => null,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-123',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'email' => 'google@example.com',
                'email_verified' => false,
                'name' => 'Google User',
            ]),
        ]);

        $response = $this
            ->withSession(['auth.oauth_state.google' => 'state-123'])
            ->get(route('api.auth.providers.callback', [
                'key' => 'google',
                'state' => 'state-123',
                'code' => 'oauth-code-123',
            ]));

        $response->assertRedirectContains('/login?auth_error=');
        $this->assertGuest();
        $this->assertDatabaseMissing('user_auth_identities', [
            'provider_key' => 'google',
            'provider_user_id' => 'google-user-1',
        ]);
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    /**
     * An authenticated email account should link Google onto the current account instead of switching users.
     *
     * @return void
     */
    public function test_authenticated_user_can_link_google_to_the_current_account(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => route('api.auth.providers.callback', ['key' => 'google']),
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        $user = User::factory()->create([
            'email' => 'google@example.com',
            'email_verified_at' => now(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'token-123',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-user-1',
                'email' => 'google@example.com',
                'email_verified' => true,
                'name' => 'Google User',
            ]),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'auth.oauth_state.google' => 'state-123',
                'auth.oauth_link.google' => $user->id,
            ])
            ->get(route('api.auth.providers.callback', [
                'key' => 'google',
                'state' => 'state-123',
                'code' => 'oauth-code-123',
            ]));

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('user_auth_identities', [
            'user_id' => $user->id,
            'provider_key' => 'google',
            'provider_user_id' => 'google-user-1',
            'provider_email' => 'google@example.com',
        ]);
    }
}
