<?php

declare(strict_types=1);

namespace App\Repositories\Auth\Interfaces;

use App\Models\AuthProvider;
use Illuminate\Database\Eloquent\Collection;

interface AuthProviderRepositoryInterface
{
    /**
     * Return providers in the same order used by admin and public auth listings.
     *
     * @return Collection<int, AuthProvider>
     */
    public function getOrdered(): Collection;

    /**
     * Find a single provider by its stable key.
     */
    public function findByKey(string $key): ?AuthProvider;

    /**
     * Persist attribute changes for an existing provider record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(AuthProvider $provider, array $attributes): AuthProvider;

    /**
     * Ensure a provider row exists for the given key and default attributes.
     *
     * @param  array<string, mixed>  $defaults
     */
    public function firstOrCreateByKey(string $key, array $defaults): AuthProvider;
}
