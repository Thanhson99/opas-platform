<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Auth\Contracts\AuthProviderDriverInterface;
use App\Models\AuthProvider;
use App\Models\User;
use App\Repositories\Auth\Interfaces\AuthProviderRepositoryInterface;
use Illuminate\Validation\ValidationException;

class AuthProviderConfigService
{
    /**
     * Inject repository and registry dependencies used to validate and persist provider settings.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthProviderRepositoryInterface $authProviderRepository,
        private readonly AuthProviderRegistry $authProviderRegistry,
        private readonly AuthSecurityAuditService $authSecurityAuditService,
    ) {}

    /**
     * Persist provider settings while preserving existing encrypted secrets
     * unless the admin explicitly rotates them.
     *
     * @param  AuthProvider  $provider
     * @param  array<string, mixed>  $validated
     * @param  User|null  $actor
     * @return AuthProvider
     */
    public function update(AuthProvider $provider, array $validated, ?User $actor = null): AuthProvider
    {
        $before = $actor === null ? null : clone $provider;
        $attributes = $validated;

        if ($provider->key === 'email') {
            $attributes['email_verification_mode'] = 'required';
        }

        if (array_key_exists('public_config', $attributes)) {
            $attributes['public_config'] = $this->normalizeConfigArray($attributes['public_config']);
        }

        if (array_key_exists('capabilities', $attributes)) {
            $attributes['capabilities'] = $this->normalizeConfigArray($attributes['capabilities']);
        }

        if (array_key_exists('secret_config', $attributes)) {
            $attributes['secret_config'] = $this->mergeSecretConfig(
                $this->normalizeConfigArray($provider->secret_config),
                $attributes['secret_config'],
            );
        }

        $this->validateUpdate($provider, $attributes);

        $updatedProvider = $this->authProviderRepository->update($provider, $attributes);

        if ($actor === null) {
            return $updatedProvider;
        }

        if (! $before instanceof AuthProvider) {
            return $updatedProvider;
        }

        $this->authSecurityAuditService->logProviderSettingsUpdated($actor, $before, $updatedProvider);

        return $updatedProvider;
    }

    /**
     * Validate the provider payload before it is stored.
     *
     * @param  AuthProvider  $provider
     * @param  array<string, mixed>  $attributes
     * @return void
     */
    private function validateUpdate(AuthProvider $provider, array $attributes): void
    {
        $driver = $this->authProviderRegistry->get($provider->key);

        if (! $driver instanceof AuthProviderDriverInterface) {
            return;
        }

        $errors = [];
        $enabled = (bool) ($attributes['enabled'] ?? $provider->enabled);
        $capabilities = $this->normalizeConfigArray($attributes['capabilities'] ?? $provider->capabilities);
        $publicConfig = $this->normalizeConfigArray($attributes['public_config'] ?? $provider->public_config);
        $secretConfig = $this->normalizeConfigArray($attributes['secret_config'] ?? $provider->secret_config);

        if ($enabled) {
            $errors = array_merge(
                $errors,
                $this->validateRequiredConfig($driver, $publicConfig, $secretConfig),
                $this->validateRedirectUri($publicConfig),
            );
        }

        // Never allow admins to remove the final working sign-in method.
        if ($provider->enabled && ! $enabled && ! $this->hasAnotherActiveLoginProvider($provider)) {
            $errors['enabled'] = ['At least one active login provider must remain enabled.'];
        }

        if ($this->disablesLastWorkingLoginCapability($provider, $driver, $enabled, $capabilities)) {
            $errors['capabilities.login'] = ['At least one active login provider must continue to support login.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Prevent capability changes from removing the final working login path.
     *
     * @param  AuthProvider  $provider
     * @param  AuthProviderDriverInterface  $driver
     * @param  bool  $enabled
     * @param  array<string, mixed>  $capabilities
     * @return bool
     */
    private function disablesLastWorkingLoginCapability(
        AuthProvider $provider,
        AuthProviderDriverInterface $driver,
        bool $enabled,
        array $capabilities,
    ): bool {
        if (! $provider->enabled || ! $driver->isReady($provider)) {
            return false;
        }

        $currentSupportsLogin = (bool) ($provider->capabilities['login'] ?? false);
        $nextSupportsLogin = (bool) ($capabilities['login'] ?? false);

        return $enabled
            && $currentSupportsLogin
            && ! $nextSupportsLogin
            && ! $this->hasAnotherActiveLoginProvider($provider);
    }

    /**
     * Normalize mixed payload input into a string-keyed array.
     *
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function normalizeConfigArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * Merge incoming secret values into the encrypted config payload.
     *
     * @param  array<string, mixed>  $current
     * @param  mixed  $incoming
     * @return array<string, mixed>
     */
    private function mergeSecretConfig(array $current, mixed $incoming): array
    {
        if (! is_array($incoming)) {
            return $current;
        }

        $next = $current;

        foreach ($incoming as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($value === null || (is_string($value) && trim($value) === '')) {
                unset($next[$key]);

                continue;
            }

            $next[$key] = $value;
        }

        return $next;
    }

    /**
     * Validate required provider config keys before enabling a provider.
     *
     * @param  AuthProviderDriverInterface  $driver
     * @param  array<string, mixed>  $publicConfig
     * @param  array<string, mixed>  $secretConfig
     * @return array<string, list<string>>
     */
    private function validateRequiredConfig(
        AuthProviderDriverInterface $driver,
        array $publicConfig,
        array $secretConfig,
    ): array {
        $errors = [];

        foreach ($driver->requiredPublicConfigKeys() as $key) {
            if (! $this->hasConfiguredValue($publicConfig, $key)) {
                $errors["public_config.$key"] = ["The {$key} field is required."];
            }
        }

        foreach ($driver->requiredSecretConfigKeys() as $key) {
            if (! $this->hasConfiguredValue($secretConfig, $key)) {
                $errors["secret_config.$key"] = ["The {$key} field is required."];
            }
        }

        return $errors;
    }

    /**
     * Validate the configured redirect URI when the provider exposes one.
     *
     * @param  array<string, mixed>  $publicConfig
     * @return array<string, list<string>>
     */
    private function validateRedirectUri(array $publicConfig): array
    {
        if (! $this->hasConfiguredValue($publicConfig, 'redirect_uri')) {
            return [];
        }

        $redirectUri = $publicConfig['redirect_uri'];

        if (! is_string($redirectUri)) {
            return [
                'public_config.redirect_uri' => ['The redirect URI must be a valid URL.'],
            ];
        }

        if (filter_var($redirectUri, FILTER_VALIDATE_URL) !== false) {
            return [];
        }

        return [
            'public_config.redirect_uri' => ['The redirect URI must be a valid URL.'],
        ];
    }

    /**
     * Check whether a config value should be treated as present.
     *
     * @param  array<string, mixed>  $config
     * @param  string  $key
     * @return bool
     */
    private function hasConfiguredValue(array $config, string $key): bool
    {
        if (! array_key_exists($key, $config)) {
            return false;
        }

        $value = $config[$key];

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }

    /**
     * Determine whether another working login provider remains enabled before disabling one.
     *
     * @param  AuthProvider  $provider
     * @return bool
     */
    private function hasAnotherActiveLoginProvider(AuthProvider $provider): bool
    {
        foreach ($this->authProviderRepository->getOrdered() as $candidate) {
            if ($candidate->key === $provider->key) {
                continue;
            }

            $driver = $this->authProviderRegistry->get($candidate->key);

            if (! $driver instanceof AuthProviderDriverInterface) {
                continue;
            }

            $supportsLogin = (bool) ($candidate->capabilities['login'] ?? false);

            if ($supportsLogin && $candidate->enabled && $driver->isReady($candidate)) {
                return true;
            }
        }

        return false;
    }
}
