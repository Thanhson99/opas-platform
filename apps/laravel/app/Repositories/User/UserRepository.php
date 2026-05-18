<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Repositories\User\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * Inject the user model used by the repository.
     *
     * @return void
     */
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Return users in a deterministic order for the admin management screen.
     *
     * @param  string|null  $search
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, User>
     */
    public function getOrderedForAdmin(?string $search, int $perPage): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->withCount('authIdentities')
            ->when(
                $search !== null && $search !== '',
                static function ($builder) use ($search): void {
                    $builder->where(function ($nestedBuilder) use ($search): void {
                        $nestedBuilder
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('role', 'like', '%'.$search.'%');
                    });
                },
            )
            ->orderByRaw(
                "case role when 'admin' then 1 when 'vip' then 2 when 'plus' then 3 when 'member' then 4 else 5 end",
            )
            ->orderByDesc('email_verified_at')
            ->orderBy('name')
            ->orderBy('email');

        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Find a single user by primary key.
     *
     * @param  int  $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        $user = $this->model
            ->newQuery()
            ->find($id);

        return $user instanceof User ? $user : null;
    }

    /**
     * Persist account field changes and return a fresh user model.
     *
     * @param  User  $user
     * @param  string  $name
     * @param  UserRole  $role
     * @return User
     */
    public function updateAccount(User $user, string $name, UserRole $role): User
    {
        $user->forceFill([
            'name' => $name,
            'role' => $role,
        ])->save();

        return $user->refresh();
    }

    /**
     * Count users holding the given role.
     *
     * @param  UserRole  $role
     * @return int
     */
    public function countByRole(UserRole $role): int
    {
        return $this->model
            ->newQuery()
            ->where('role', $role->value)
            ->count();
    }

    /**
     * Delete a user account from persistent storage.
     *
     * @param  User  $user
     * @return void
     */
    public function delete(User $user): void
    {
        $user->delete();
    }
}
