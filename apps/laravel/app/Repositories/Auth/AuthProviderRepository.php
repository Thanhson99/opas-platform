<?php

declare(strict_types=1);

namespace App\Repositories\Auth;

use App\Models\AuthProvider;
use App\Repositories\Auth\Interfaces\AuthProviderRepositoryInterface;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<AuthProvider>
 */
class AuthProviderRepository extends BaseRepository implements AuthProviderRepositoryInterface
{
    /**
     * @return void
     */
    public function __construct(AuthProvider $model)
    {
        parent::__construct($model);
    }

    /**
     * Return providers in a deterministic display order.
     *
     * @return Collection<int, AuthProvider>
     */
    public function getOrdered(): Collection
    {
        return $this->model
            ->newQuery()
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();
    }

    public function findByKey(string $key): ?AuthProvider
    {
        $provider = $this->model
            ->newQuery()
            ->where('key', $key)
            ->first();

        return $provider instanceof AuthProvider ? $provider : null;
    }

    /**
     * Persist a provider update and return a fresh copy of the record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(AuthProvider $provider, array $attributes): AuthProvider
    {
        $provider->fill($attributes);
        $provider->save();

        return $provider->refresh();
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function firstOrCreateByKey(string $key, array $defaults): AuthProvider
    {
        /** @var AuthProvider $provider */
        $provider = $this->model
            ->newQuery()
            ->firstOrCreate(
                ['key' => $key],
                $defaults,
            );

        return $provider;
    }
}
