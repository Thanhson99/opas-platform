<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAuthProviderRequest;
use App\Http\Resources\AdminAuthProviderResource;
use App\Models\User;
use App\Services\Auth\AuthProviderConfigService;
use App\Services\Auth\AuthProviderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminAuthProviderApiController extends Controller
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AuthProviderService $authProviderService,
        private readonly AuthProviderConfigService $authProviderConfigService,
    ) {}

    /**
     * Return the provider list used by the admin configuration screens.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return AdminAuthProviderResource::collection($this->authProviderService->getAdminProviders());
    }

    /**
     * Update a single provider configuration entry by key.
     *
     * @param  UpdateAuthProviderRequest  $request
     * @param  string  $key
     * @return AdminAuthProviderResource
     */
    public function update(UpdateAuthProviderRequest $request, string $key): AdminAuthProviderResource
    {
        $resolved = $this->authProviderService->resolve($key);

        abort_if($resolved === null, 404);
        $actor = $request->user();

        $provider = $this->authProviderConfigService->update(
            $resolved['provider'],
            $request->validated(),
            $actor instanceof User ? $actor : null,
        );

        $next = $this->authProviderService->resolve($provider->key);

        abort_if($next === null, 404);

        return new AdminAuthProviderResource($next);
    }
}
