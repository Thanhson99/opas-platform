<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories\Auth;

use App\Models\AuthProvider;
use App\Repositories\Auth\AuthProviderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify repository-level data access rules for auth providers.
 */
class AuthProviderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Providers should be returned in admin-friendly display order.
     */
    public function test_get_ordered_returns_providers_sorted_by_sort_order_then_display_name(): void
    {
        AuthProvider::query()->where('key', 'email')->update([
            'sort_order' => 30,
            'display_name' => 'Email Z',
        ]);
        AuthProvider::query()->where('key', 'google')->update([
            'sort_order' => 10,
            'display_name' => 'Google',
        ]);
        AuthProvider::query()->where('key', 'facebook')->update([
            'sort_order' => 20,
            'display_name' => 'Facebook',
        ]);
        AuthProvider::query()->where('key', 'github')->update([
            'sort_order' => 30,
            'display_name' => 'GitHub A',
        ]);

        $providers = $this->app->make(AuthProviderRepository::class)->getOrdered();

        $this->assertSame(
            ['google', 'facebook', 'email', 'github'],
            $providers->pluck('key')->all(),
        );
    }

    /**
     * Provider lookup should resolve a persisted row by its stable key.
     */
    public function test_find_by_key_returns_matching_provider(): void
    {
        $provider = $this->app->make(AuthProviderRepository::class)->findByKey('google');

        $this->assertInstanceOf(AuthProvider::class, $provider);
        $this->assertSame('google', $provider->key);
    }

    /**
     * Repository updates should persist changes and return a fresh encrypted payload.
     */
    public function test_update_persists_changes_and_returns_fresh_model(): void
    {
        $provider = AuthProvider::query()->where('key', 'google')->firstOrFail();

        $updated = $this->app->make(AuthProviderRepository::class)->update($provider, [
            'display_name' => 'Google Workspace',
            'enabled' => true,
            'secret_config' => [
                'client_secret' => 'test-secret',
            ],
        ]);

        $this->assertSame('Google Workspace', $updated->display_name);
        $this->assertTrue($updated->enabled);
        $this->assertSame('test-secret', $updated->secret_config['client_secret']);
        $this->assertDatabaseHas('auth_providers', [
            'key' => 'google',
            'display_name' => 'Google Workspace',
            'enabled' => true,
        ]);
    }
}
