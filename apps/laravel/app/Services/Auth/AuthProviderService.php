<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Auth\Contracts\AuthProviderDriverInterface;
use App\Models\AuthProvider;
use App\Repositories\Auth\Interfaces\AuthProviderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthProviderService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AuthProviderRegistry $registry,
        private readonly AuthProviderRepositoryInterface $authProviderRepository,
    ) {}

    /**
     * Return the persisted provider list or an in-memory email fallback when storage is unavailable.
     *
     * @return Collection<int, AuthProvider>
     */
    public function getConfiguredProviders(): Collection
    {
        try {
            $providers = $this->authProviderRepository->getOrdered();

            if ($providers->isNotEmpty()) {
                return $providers;
            }
        } catch (Throwable $exception) {
            Log::warning('Falling back to default auth providers because configured providers are unavailable.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return new Collection([$this->makeFallbackProvider('email')]);
    }

    /**
     * Resolve only providers that are safe to expose on the public login screen.
     *
     * @return list<array{provider:AuthProvider,driver:AuthProviderDriverInterface,active:bool,ready:bool,issues:list<string>}>
     */
    public function getPublicProviders(): array
    {
        $providers = [];

        foreach ($this->getConfiguredProviders() as $provider) {
            $driver = $this->registry->get($provider->key);

            if (! $driver instanceof AuthProviderDriverInterface) {
                continue;
            }

            $resolved = $this->buildResolvedProvider($provider, $driver);

            if (! $resolved['active'] || $provider->visibility !== 'public') {
                continue;
            }

            $providers[] = $resolved;
        }

        return $providers;
    }

    /**
     * Resolve the complete provider list for admin management screens.
     *
     * @return list<array{provider:AuthProvider,driver:AuthProviderDriverInterface,active:bool,ready:bool,issues:list<string>}>
     */
    public function getAdminProviders(): array
    {
        $providers = [];

        foreach ($this->getConfiguredProviders() as $provider) {
            $driver = $this->registry->get($provider->key);

            if (! $driver instanceof AuthProviderDriverInterface) {
                continue;
            }

            $providers[] = $this->buildResolvedProvider($provider, $driver);
        }

        return $providers;
    }

    /**
     * Check whether a provider is active and, if requested, whether it exposes a capability flag.
     *
     * @param  string  $key
     * @param  string|null  $capability
     * @return bool
     */
    public function canUse(string $key, ?string $capability = null): bool
    {
        $resolved = $this->resolve($key);

        if ($resolved === null || ! $resolved['active']) {
            return false;
        }

        if ($capability === null) {
            return true;
        }

        return (bool) ($resolved['provider']->capabilities[$capability] ?? false);
    }

    /**
     * Resolve the configured email verification mode for a provider or fall back to config default.
     *
     * @param  string  $key
     * @return string
     */
    public function emailVerificationMode(string $key = 'email'): string
    {
        $resolved = $this->resolve($key);
        $mode = $resolved['provider']->email_verification_mode ?? null;

        if (is_string($mode) && in_array($mode, ['required', 'optional', 'disabled'], true)) {
            return $mode;
        }

        $configuredMode = config('opas.auth.email_verification.default_mode', 'required');

        return is_string($configuredMode) ? $configuredMode : 'required';
    }

    /**
     * Resolve a single provider key into a runtime-ready provider payload.
     *
     * @param  string  $key
     * @return array{provider:AuthProvider,driver:AuthProviderDriverInterface,active:bool,ready:bool,issues:list<string>}|null
     */
    public function resolve(string $key): ?array
    {
        $driver = $this->registry->get($key);

        if (! $driver instanceof AuthProviderDriverInterface) {
            return null;
        }

        $provider = $this->authProviderRepository->findByKey($key);

        if (! $provider instanceof AuthProvider) {
            if ($key !== 'email') {
                return null;
            }

            $provider = $this->makeFallbackProvider('email');
        }

        return $this->buildResolvedProvider($provider, $driver);
    }

    /**
     * Bootstrap default provider rows without leaking database writes outside the repository layer.
     *
     * Ensure a database record exists for every registered provider driver.
     *
     * @return void
     */
    public function ensureDefaultProviders(): void
    {
        foreach ($this->registry->all() as $driver) {
            $this->authProviderRepository->firstOrCreateByKey(
                $driver->key(),
                [
                    'display_name' => $driver->defaultDisplayName(),
                    'type' => $driver->type(),
                    'enabled' => $driver->key() === 'email',
                    'sort_order' => $this->defaultSortOrder($driver->key()),
                    'visibility' => 'public',
                    'icon' => $driver->defaultIcon(),
                    'capabilities' => $driver->capabilities(),
                    'public_config' => [],
                    'secret_config' => [],
                    'email_verification_mode' => null,
                ],
            );
        }
    }

    /**
     * Keep provider ordering predictable even before admins customize the records.
     *
     * @param  string  $key
     * @return int
     */
    private function defaultSortOrder(string $key): int
    {
        return match ($key) {
            'email' => 10,
            'google' => 20,
            'facebook' => 30,
            'github' => 40,
            default => 100,
        };
    }

    /**
     * Map runtime readiness state onto the persisted provider record.
     *
     * @param  AuthProvider  $provider
     * @param  AuthProviderDriverInterface  $driver
     * @return array{provider:AuthProvider,driver:AuthProviderDriverInterface,active:bool,ready:bool,issues:list<string>}
     */
    private function buildResolvedProvider(
        AuthProvider $provider,
        AuthProviderDriverInterface $driver,
    ): array {
        $issues = [];
        $ready = $driver->isReady($provider);

        if (! $provider->enabled) {
            $issues[] = 'Provider is disabled.';
        }

        if (! $ready) {
            $issues[] = 'Provider configuration is incomplete.';
        }

        if ($provider->enabled && ! $ready) {
            Log::warning('Auth provider is enabled but not ready.', [
                'provider' => $provider->key,
            ]);
        }

        return [
            'provider' => $provider,
            'driver' => $driver,
            'active' => $provider->enabled && $ready,
            'ready' => $ready,
            'issues' => $issues,
        ];
    }

    /**
     * Build an in-memory provider when the database record is missing.
     *
     * @param  string  $key
     * @return AuthProvider
     */
    private function makeFallbackProvider(string $key): AuthProvider
    {
        $driver = $this->registry->get($key);

        if (! $driver instanceof AuthProviderDriverInterface) {
            throw new \RuntimeException(sprintf('Auth provider driver [%s] is not registered.', $key));
        }

        return new AuthProvider([
            'key' => $driver->key(),
            'display_name' => $driver->defaultDisplayName(),
            'type' => $driver->type(),
            'enabled' => $key === 'email',
            'sort_order' => $this->defaultSortOrder($driver->key()),
            'visibility' => 'public',
            'icon' => $driver->defaultIcon(),
            'capabilities' => $driver->capabilities(),
            'public_config' => [],
            'secret_config' => [],
            'email_verification_mode' => null,
        ]);
    }
}
