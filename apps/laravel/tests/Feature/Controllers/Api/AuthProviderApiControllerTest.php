<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Models\AuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify the public provider listing that drives the login screen.
 */
class AuthProviderApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Only providers that are enabled, ready, and public should reach the public API.
     */
    public function test_it_returns_active_providers_only(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => 'https://example.com/auth/google/callback',
                'button_text' => 'Continue with Google',
                'scopes' => ['openid', 'email', 'profile'],
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        $response = $this->getJson(route('api.auth.providers.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.key', 'email')
            ->assertJsonPath('data.1.key', 'google')
            ->assertJsonPath(
                'data.1.metadata.callback_url',
                route('api.auth.providers.callback', ['key' => 'google']),
            )
            ->assertJsonMissingPath('data.2');
    }

    /**
     * Incomplete OAuth providers must stay hidden even when an admin has toggled them on.
     */
    public function test_it_hides_enabled_but_incomplete_providers(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
            ],
            'secret_config' => [],
        ]);

        $response = $this->getJson(route('api.auth.providers.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'email');
    }

    /**
     * Non-public providers should stay out of the login UI contract.
     */
    public function test_it_hides_non_public_providers_from_public_listing(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $provider->update([
            'enabled' => true,
            'visibility' => 'hidden',
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => 'https://example.com/auth/google/callback',
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);

        $response = $this->getJson(route('api.auth.providers.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.key', 'email');
    }
}
