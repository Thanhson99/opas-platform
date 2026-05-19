<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Auth\Interfaces\UserAuthIdentityRepositoryInterface;
use App\Repositories\User\Interfaces\UserRepositoryInterface;
use App\Services\Auth\AuthProviderService;
use Illuminate\Validation\ValidationException;

class AccountSettingsService
{
    /**
     * Inject the repositories and provider service used by the account settings workflow.
     *
     * @return void
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserAuthIdentityRepositoryInterface $userAuthIdentityRepository,
        private readonly AuthProviderService $authProviderService,
    ) {}

    /**
     * Update the current user's editable profile fields.
     *
     * @param  User  $user
     * @param  string  $name
     * @return User
     */
    public function updateProfile(User $user, string $name): User
    {
        return $this->userRepository->updateDisplayName($user, $name);
    }

    /**
     * Remove a linked OAuth provider from the current account when a safe fallback sign-in path remains.
     *
     * @param  User  $user
     * @param  string  $providerKey
     * @param  string|null  $currentLoginProvider
     * @return User
     */
    public function unlinkProvider(User $user, string $providerKey, ?string $currentLoginProvider): User
    {
        $resolved = $this->authProviderService->resolve($providerKey);

        if ($resolved === null || ! ($resolved['provider']->capabilities['link_account'] ?? false)) {
            throw ValidationException::withMessages([
                'provider' => ['This login provider cannot be managed from account linking settings.'],
            ]);
        }

        if ($currentLoginProvider === $providerKey) {
            throw ValidationException::withMessages([
                'provider' => ['You cannot unlink the login provider used by the current session.'],
            ]);
        }

        $identity = $this->userAuthIdentityRepository->findByUserAndProviderKey($user, $providerKey);

        if ($identity === null) {
            throw ValidationException::withMessages([
                'provider' => ['This login provider is not linked to the current account.'],
            ]);
        }

        $this->userAuthIdentityRepository->delete($identity);

        return $user->refresh()->load('authIdentities');
    }
}
