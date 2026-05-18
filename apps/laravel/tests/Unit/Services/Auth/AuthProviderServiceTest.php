<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\AuthProvider;
use App\Services\Auth\AuthProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify auth provider service behavior independently from controller responses.
 */
class AuthProviderServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Missing provider rows should be recreated from the registered driver defaults.
     */
    public function test_ensure_default_providers_recreates_missing_records(): void
    {
        AuthProvider::query()->where('key', 'google')->delete();

        $this->app->make(AuthProviderService::class)->ensureDefaultProviders();

        $this->assertDatabaseHas('auth_providers', [
            'key' => 'google',
            'display_name' => 'Google',
        ]);
    }

    /**
     * Email login should still resolve through the in-memory fallback when the row is missing.
     */
    public function test_resolve_returns_fallback_email_provider_when_database_row_is_missing(): void
    {
        AuthProvider::query()->where('key', 'email')->delete();

        $resolved = $this->app->make(AuthProviderService::class)->resolve('email');

        $this->assertNotNull($resolved);
        $this->assertSame('email', $resolved['provider']->key);
        $this->assertTrue($resolved['active']);
        $this->assertTrue($resolved['ready']);
    }

    /**
     * Providers with missing required config should not be exposed as usable.
     */
    public function test_can_use_returns_false_for_enabled_but_incomplete_provider(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
            ],
            'secret_config' => [],
        ]);

        $this->assertFalse($this->app->make(AuthProviderService::class)->canUse('google'));
    }
}
