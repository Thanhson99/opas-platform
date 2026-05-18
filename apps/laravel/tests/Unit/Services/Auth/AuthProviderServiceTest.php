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

    /**
     * Ready and enabled providers should resolve as usable for the requested capability.
     */
    public function test_can_use_returns_true_for_ready_enabled_provider(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $provider->update([
            'enabled' => true,
            'visibility' => 'public',
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => 'https://example.com/auth/google/callback',
            ],
            'secret_config' => [
                'client_secret' => 'test-secret',
            ],
        ]);

        $this->assertTrue($this->app->make(AuthProviderService::class)->canUse('google', 'login'));
    }

    /**
     * Unknown provider keys should not resolve into a runtime contract.
     */
    public function test_resolve_returns_null_for_unknown_provider(): void
    {
        $resolved = $this->app->make(AuthProviderService::class)->resolve('unknown-provider');

        $this->assertNull($resolved);
    }

    /**
     * Email provider verification policy should stay locked to required regardless of stored overrides.
     */
    public function test_email_verification_mode_always_returns_required_for_email_provider(): void
    {
        AuthProvider::query()->where('key', 'email')->update([
            'email_verification_mode' => 'optional',
        ]);

        $mode = $this->app->make(AuthProviderService::class)->emailVerificationMode('email');

        $this->assertSame('required', $mode);
    }

    /**
     * Email provider verification policy should ignore application defaults that weaken the requirement.
     */
    public function test_email_verification_mode_ignores_config_override_for_email_provider(): void
    {
        AuthProvider::query()->where('key', 'email')->update([
            'email_verification_mode' => null,
        ]);

        config()->set('opas.auth.email_verification.default_mode', 'disabled');

        $mode = $this->app->make(AuthProviderService::class)->emailVerificationMode('email');

        $this->assertSame('required', $mode);
    }

    /**
     * Non-email providers may still expose their own verification policy values.
     */
    public function test_email_verification_mode_keeps_provider_specific_value_for_non_email_providers(): void
    {
        AuthProvider::query()->where('key', 'google')->update([
            'email_verification_mode' => 'optional',
        ]);

        $mode = $this->app->make(AuthProviderService::class)->emailVerificationMode('google');

        $this->assertSame('optional', $mode);
    }
}
