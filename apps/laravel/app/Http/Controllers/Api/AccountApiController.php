<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountProfileRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\User\AccountSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountApiController extends Controller
{
    /**
     * Inject the services used by the authenticated account settings endpoints.
     *
     * @return void
     */
    public function __construct(
        private readonly AccountSettingsService $accountSettingsService,
        private readonly AuthSessionService $authSessionService,
    ) {}

    /**
     * Update editable profile fields for the current authenticated account.
     *
     * @param  UpdateAccountProfileRequest  $request
     * @return JsonResponse
     */
    public function update(UpdateAccountProfileRequest $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        /** @var array{name:string} $validated */
        $validated = $request->validated();

        $updated = $this->accountSettingsService->updateProfile($user, $validated['name']);

        return (new AuthUserResource($updated->load('authIdentities')))
            ->additional(['message' => 'Account profile updated successfully.'])
            ->response();
    }

    /**
     * Remove one linked OAuth provider from the current authenticated account.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return JsonResponse
     */
    public function unlinkProvider(Request $request, string $key): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);
        $updated = $this->accountSettingsService->unlinkProvider(
            $user,
            $key,
            $this->authSessionService->currentLoginProvider($request),
        );

        return (new AuthUserResource($updated))
            ->additional(['message' => 'Linked login provider removed successfully.'])
            ->response();
    }

    /**
     * Resolve the current authenticated user or raise a stable validation failure.
     *
     * @param  Request  $request
     * @return User
     */
    private function resolveAuthenticatedUser(Request $request): User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        throw ValidationException::withMessages([
            'user' => ['The current authenticated account could not be resolved.'],
        ]);
    }
}
