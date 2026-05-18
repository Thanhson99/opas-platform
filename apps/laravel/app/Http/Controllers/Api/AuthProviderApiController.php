<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthProviderResource;
use App\Services\Auth\AuthProviderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuthProviderApiController extends Controller
{
    /**
     * Inject the service that resolves login providers for the public auth screens.
     *
     * @return void
     */
    public function __construct(
        private readonly AuthProviderService $authProviderService,
    ) {}

    /**
     * Return the provider list that the public login screen is allowed to render.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return AuthProviderResource::collection($this->authProviderService->getPublicProviders());
    }
}
