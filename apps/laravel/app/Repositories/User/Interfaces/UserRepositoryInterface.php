<?php

declare(strict_types=1);

namespace App\Repositories\User\Interfaces;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * Return users in a deterministic order for the admin management screen.
     *
     * @param  string|null  $search
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, User>
     */
    public function getOrderedForAdmin(?string $search, int $perPage): LengthAwarePaginator;

    /**
     * Find a single user by primary key.
     *
     * @param  int  $id
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Persist account field changes and return a fresh user model.
     *
     * @param  User  $user
     * @param  string  $name
     * @param  UserRole  $role
     * @return User
     */
    public function updateAccount(User $user, string $name, UserRole $role): User;

    /**
     * Count users holding the given role.
     *
     * @param  UserRole  $role
     * @return int
     */
    public function countByRole(UserRole $role): int;

    /**
     * Delete a user account from persistent storage.
     *
     * @param  User  $user
     * @return void
     */
    public function delete(User $user): void;
}
