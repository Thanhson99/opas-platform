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
}
