<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdminUsersRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Services\User\AdminUserService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserApiController extends Controller
{
    /**
     * Inject the admin user management service.
     *
     * @return void
     */
    public function __construct(
        private readonly AdminUserService $adminUserService,
    ) {}

    /**
     * Return the user accounts shown in the admin user management screen.
     *
     * @param  ListAdminUsersRequest  $request
     * @return AnonymousResourceCollection
     */
    public function index(ListAdminUsersRequest $request): AnonymousResourceCollection
    {
        /** @var array{search?:string,per_page?:int} $validated */
        $validated = $request->validated();

        return AdminUserResource::collection(
            $this->adminUserService->getManagedUsers(
                isset($validated['search']) ? trim($validated['search']) : null,
                (int) ($validated['per_page'] ?? 10),
            ),
        );
    }

    /**
     * Update a single user account role from the admin console.
     *
     * @param  UpdateAdminUserRequest  $request
     * @param  int  $id
     * @return AdminUserResource
     */
    public function update(UpdateAdminUserRequest $request, int $id): AdminUserResource
    {
        /** @var array{name:string,role:string} $validated */
        $validated = $request->validated();

        $user = $this->adminUserService->updateUser(
            $id,
            $validated['name'],
            UserRole::from($validated['role']),
        );

        return new AdminUserResource($user);
    }

    /**
     * Delete a single user account from the admin console.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        $this->adminUserService->deleteUser($id);

        return response()->json([
            'message' => 'User account deleted successfully.',
        ]);
    }

    /**
     * Reset a user password to a generated temporary value and email it to the account owner.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(int $id): \Illuminate\Http\JsonResponse
    {
        $this->adminUserService->resetPassword($id);

        return response()->json([
            'message' => 'A temporary password has been generated and emailed to the user.',
        ]);
    }
}
