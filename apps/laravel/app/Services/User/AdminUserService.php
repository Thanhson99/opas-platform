<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use App\Repositories\User\Interfaces\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    /**
     * Inject the user repository used for admin account management.
     *
     * @return void
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Return the account list displayed in the admin user management screen.
     *
     * @param  string|null  $search
     * @param  int  $perPage
     * @return LengthAwarePaginator<int, User>
     */
    public function getManagedUsers(?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->userRepository->getOrderedForAdmin($search, $perPage);
    }

    /**
     * Update user account fields while protecting the final remaining admin account.
     *
     * @param  int  $userId
     * @param  string  $name
     * @param  UserRole  $role
     * @return User
     */
    public function updateUser(int $userId, string $name, UserRole $role): User
    {
        $user = $this->userRepository->findById($userId);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user' => ['The selected user account could not be found.'],
            ]);
        }

        /** @var User|null $actor */
        $actor = Auth::user();

        if ($actor instanceof User && $actor->id === $user->id && $role !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'role' => ['You cannot remove admin access from the account you are currently using.'],
            ]);
        }

        if ($user->role === UserRole::Admin && $role !== UserRole::Admin) {
            $adminCount = $this->userRepository->countByRole(UserRole::Admin);

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['At least one admin account must remain assigned to the system.'],
                ]);
            }
        }

        return $this->userRepository->updateAccount($user, $name, $role);
    }

    /**
     * Delete a user account while preventing destructive removal of the final admin or current actor.
     *
     * @param  int  $userId
     * @return void
     */
    public function deleteUser(int $userId): void
    {
        $user = $this->userRepository->findById($userId);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user' => ['The selected user account could not be found.'],
            ]);
        }

        /** @var User|null $actor */
        $actor = Auth::user();

        if ($actor instanceof User && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete the account you are currently using.'],
            ]);
        }

        if ($user->role === UserRole::Admin) {
            $adminCount = $this->userRepository->countByRole(UserRole::Admin);

            if ($adminCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => ['At least one admin account must remain assigned to the system.'],
                ]);
            }
        }

        $this->userRepository->delete($user);
    }

    /**
     * Generate a new temporary password for a managed account and email it securely.
     *
     * @param  int  $userId
     * @return void
     */
    public function resetPassword(int $userId): void
    {
        $user = $this->userRepository->findById($userId);

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'user' => ['The selected user account could not be found.'],
            ]);
        }

        $temporaryPassword = Str::password(16, true, true, true, false);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
        ])->save();

        $user->notify(new AdminResetPasswordNotification($temporaryPassword));
    }
}
