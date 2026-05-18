<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\AuthProviderDriverInterface;
use App\Models\AuthProvider;

abstract class AbstractAuthProviderDriver implements AuthProviderDriverInterface
{
    /**
     * Default to no icon unless a concrete driver overrides it.
     */
    public function defaultIcon(): ?string
    {
        return null;
    }

    /**
     * Provide an empty metadata payload unless a concrete driver exposes public values.
     *
     * @return array<string, mixed>
     */
    public function publicMetadata(AuthProvider $provider): array
    {
        return [];
    }

    /**
     * Check the configured public and secret keys declared by the driver before exposing it as ready.
     */
    public function isReady(AuthProvider $provider): bool
    {
        $publicConfig = $provider->public_config;
        $secretConfig = $provider->secret_config;

        foreach ($this->requiredPublicConfigKeys() as $key) {
            if (! $this->hasConfiguredValue($publicConfig, $key)) {
                return false;
            }
        }

        foreach ($this->requiredSecretConfigKeys() as $key) {
            if (! $this->hasConfiguredValue($secretConfig, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Treat trimmed strings, non-empty arrays, and non-null scalars as configured values.
     *
     * @param  array<string, mixed>  $config
     */
    protected function hasConfiguredValue(array $config, string $key): bool
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
}
