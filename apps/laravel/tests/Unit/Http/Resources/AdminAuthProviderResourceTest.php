<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\AdminAuthProviderResource;
use App\Models\AuthProvider;
use App\Services\Auth\AuthProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Verify admin auth provider resource shaping independently from controller transport.
 */
class AdminAuthProviderResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Secret status should expose presence flags without returning plaintext secret values.
     */
    public function test_to_array_reports_secret_status_without_exposing_secret_config(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();
        $provider->update([
            'enabled' => true,
            'public_config' => [
                'client_id' => 'google-client-id',
                'redirect_uri' => 'https://example.com/auth/google/callback',
            ],
            'secret_config' => [
                'client_secret' => 'test-secret',
            ],
        ]);

        $resolved = $this->app->make(AuthProviderService::class)->resolve('google');

        $this->assertNotNull($resolved);

        $payload = (new AdminAuthProviderResource($resolved))->toArray(new Request);

        $this->assertSame('google', $payload['key']);
        $this->assertSame(['client_secret' => true], $payload['secret_status']);
        $this->assertSame(['client_id', 'redirect_uri'], $payload['required_public_keys']);
        $this->assertSame(['client_secret'], $payload['required_secret_keys']);
        $this->assertArrayNotHasKey('secret_config', $payload);
    }

    /**
     * Providers without stored secrets should report a false secret status flag.
     */
    public function test_to_array_reports_missing_secret_as_false(): void
    {
        $resolved = $this->app->make(AuthProviderService::class)->resolve('google');

        $this->assertNotNull($resolved);

        $payload = (new AdminAuthProviderResource($resolved))->toArray(new Request);

        $this->assertSame(['client_secret' => false], $payload['secret_status']);
    }
}
