<?php

declare(strict_types=1);

namespace App\Repositories\Auth\Interfaces;

use App\Models\User;
use App\Models\UserAuthIdentity;

interface UserAuthIdentityRepositoryInterface
{
    /**
     * Find one linked provider identity for the given user and provider key.
     *
     * @param  User  $user
     * @param  string  $providerKey
     * @return UserAuthIdentity|null
     */
    public function findByUserAndProviderKey(User $user, string $providerKey): ?UserAuthIdentity;

    /**
     * Delete one linked provider identity from persistent storage.
     *
     * @param  UserAuthIdentity  $identity
     * @return void
     */
    public function delete(UserAuthIdentity $identity): void;
}
