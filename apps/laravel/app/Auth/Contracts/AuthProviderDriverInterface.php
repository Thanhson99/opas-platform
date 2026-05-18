<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Enums\AuthProviderType;
use App\Models\AuthProvider;

interface AuthProviderDriverInterface
{
    /**
     * Return the stable provider key used across routes, DB rows, and frontend contracts.
     */
    public function key(): string;

    /**
     * Return the provider family so the system can branch by password vs OAuth-style flows.
     */
    public function type(): AuthProviderType;

    /**
     * Return the default label shown when the provider row is first bootstrapped.
     */
    public function defaultDisplayName(): string;

    /**
     * Return the default icon key understood by the SPA icon system.
     */
    public function defaultIcon(): ?string;

    /**
     * Return capability flags consumed by the admin UI and auth flow guards.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array;

    /**
     * Return the public config keys that must exist before the provider can be treated as ready.
     *
     * @return list<string>
     */
    public function requiredPublicConfigKeys(): array;

    /**
     * Return the encrypted config keys that must exist before the provider can be treated as ready.
     *
     * @return list<string>
     */
    public function requiredSecretConfigKeys(): array;

    /**
     * Build the metadata contract that can safely be returned to the frontend.
     *
     * @return array<string, mixed>
     */
    public function publicMetadata(AuthProvider $provider): array;

    /**
     * Determine whether the persisted provider record is complete enough to be used at runtime.
     */
    public function isReady(AuthProvider $provider): bool;
}
