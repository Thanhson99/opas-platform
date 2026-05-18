<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Auth\Contracts\AuthProviderDriverInterface;
use RuntimeException;

class AuthProviderRegistry
{
    /**
     * Store the registered auth provider drivers supplied by the service container.
     *
     * @param  iterable<AuthProviderDriverInterface>  $drivers
     * @return void
     */
    public function __construct(private readonly iterable $drivers) {}

    /**
     * Return all registered auth provider drivers keyed by provider identifier.
     *
     * @return array<string, AuthProviderDriverInterface>
     */
    public function all(): array
    {
        $drivers = [];

        foreach ($this->drivers as $driver) {
            $this->assertValidProviderKey($driver->key());
            $drivers[$driver->key()] = $driver;
        }

        return $drivers;
    }

    /**
     * Resolve a single registered auth provider driver by key.
     *
     * @param  string  $key
     * @return AuthProviderDriverInterface|null
     */
    public function get(string $key): ?AuthProviderDriverInterface
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Ensure provider keys stay route-safe and consistent across DB, API, and frontend.
     *
     * @param  string  $key
     * @return void
     */
    private function assertValidProviderKey(string $key): void
    {
        $configuredPattern = config('opas.auth.provider_key_pattern', '^[a-z][a-z0-9_-]{1,63}$');
        $pattern = is_string($configuredPattern) ? $configuredPattern : '^[a-z][a-z0-9_-]{1,63}$';
        $delimitedPattern = sprintf('/%s/', str_replace('/', '\/', $pattern));

        if (preg_match($delimitedPattern, $key) === 1) {
            return;
        }

        $configuredExample = config('opas.auth.provider_key_example', 'google');
        $example = is_string($configuredExample) ? $configuredExample : 'google';

        throw new RuntimeException(sprintf(
            'Auth provider key [%s] is invalid. Expected format similar to [%s].',
            $key,
            $example,
        ));
    }
}
