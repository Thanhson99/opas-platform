<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\AuthProvider;
use App\Services\Auth\AuthProviderConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verify validation and secret merge rules in the provider configuration service.
 */
class AuthProviderConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Existing secrets should stay intact when an update changes only public configuration.
     */
    public function test_update_preserves_existing_secret_when_secret_payload_is_omitted(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'secret_config' => [
                'client_secret' => 'existing-secret',
            ],
        ]);

        $updated = $this->app->make(AuthProviderConfigService::class)->update($provider, [
            'enabled' => false,
            'visibility' => 'hidden',
            'public_config' => [
                'button_text' => 'Continue with Google',
            ],
        ]);

        $this->assertSame('existing-secret', $updated->secret_config['client_secret']);
    }

    /**
     * Enabled OAuth providers must reject malformed redirect URIs before being persisted.
     */
    public function test_update_rejects_invalid_redirect_uri_when_provider_is_enabled(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $this->expectException(ValidationException::class);

        $this->app->make(AuthProviderConfigService::class)->update($provider, [
            'enabled' => true,
            'visibility' => 'public',
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => 'not-a-url',
            ],
            'secret_config' => [
                'client_secret' => 'google-secret',
            ],
        ]);
    }

    /**
     * Enabled OAuth providers must require the full minimum config set before activation.
     */
    public function test_update_rejects_missing_required_config_when_provider_is_enabled(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        try {
            $this->app->make(AuthProviderConfigService::class)->update($provider, [
                'enabled' => true,
                'visibility' => 'public',
                'public_config' => [
                    'client_id' => 'google-client-id',
                ],
                'secret_config' => [],
            ]);

            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('public_config.redirect_uri', $errors);
            $this->assertArrayHasKey('secret_config.client_secret', $errors);
        }
    }

    /**
     * Removing the last active login provider must be rejected to protect sign-in access.
     */
    public function test_update_rejects_disabling_last_active_login_provider(): void
    {
        $provider = AuthProvider::query()->where('key', 'email')->firstOrFail();

        AuthProvider::query()->whereIn('key', ['google', 'facebook', 'github'])->update([
            'enabled' => false,
        ]);

        try {
            $this->app->make(AuthProviderConfigService::class)->update($provider, [
                'enabled' => false,
            ]);

            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('enabled', $errors);
        }
    }

    /**
     * Removing login capability from the final ready provider must be rejected like a hard disable.
     */
    public function test_update_rejects_disabling_last_active_login_capability(): void
    {
        $provider = AuthProvider::query()->where('key', 'email')->firstOrFail();

        AuthProvider::query()->whereIn('key', ['google', 'facebook', 'github'])->update([
            'enabled' => false,
        ]);

        try {
            $this->app->make(AuthProviderConfigService::class)->update($provider, [
                'capabilities' => [
                    'login' => false,
                    'register' => true,
                    'link_account' => false,
                    'requires_redirect' => false,
                    'supports_email_verification' => true,
                    'supports_password' => true,
                ],
            ]);

            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('capabilities.login', $errors);
        }
    }
}
