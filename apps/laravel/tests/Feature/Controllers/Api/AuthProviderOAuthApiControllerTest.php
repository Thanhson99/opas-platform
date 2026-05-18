<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Models\AuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthProviderOAuthApiControllerTest extends TestCase
{
    use RefreshDatabase;

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
    }
}
