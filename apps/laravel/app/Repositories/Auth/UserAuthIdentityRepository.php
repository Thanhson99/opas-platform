<?php

declare(strict_types=1);

namespace App\Repositories\Auth;

use App\Models\User;
use App\Models\UserAuthIdentity;
use App\Repositories\Auth\Interfaces\UserAuthIdentityRepositoryInterface;

class UserAuthIdentityRepository implements UserAuthIdentityRepositoryInterface
{
    /**
     * Inject the auth identity model used for linked-provider persistence.
     *
     * @return void
     */
    public function __construct(
        private readonly UserAuthIdentity $model,
    ) {}

    /**
     * Find one linked provider identity for the given user and provider key.
     *
     * @param  User  $user
     * @param  string  $providerKey
     * @return UserAuthIdentity|null
     */
    public function findByUserAndProviderKey(User $user, string $providerKey): ?UserAuthIdentity
    {
        $identity = $this->model
            ->newQuery()
            ->where('user_id', $user->id)
            ->where('provider_key', $providerKey)
            ->first();

        return $identity instanceof UserAuthIdentity ? $identity : null;
    }

    /**
     * Delete one linked provider identity from persistent storage.
     *
     * @param  UserAuthIdentity  $identity
     * @return void
     */
    public function delete(UserAuthIdentity $identity): void
    {
        $identity->delete();
    }
}
