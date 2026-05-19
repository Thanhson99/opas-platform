<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthProviderOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AuthProviderOAuthApiController extends Controller
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AuthProviderOAuthService $authProviderOAuthService,
    ) {}

    /**
     * Start the OAuth redirect flow for the selected provider.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return RedirectResponse
     */
    public function redirect(Request $request, string $key): RedirectResponse
    {
        try {
            return $this->authProviderOAuthService->redirect($request, $key);
        } catch (RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }
    }

    /**
     * Complete the OAuth callback flow and redirect back into the SPA.
     *
     * @param  Request  $request
     * @param  string  $key
     * @return RedirectResponse
     */
    public function callback(Request $request, string $key): RedirectResponse
    {
        try {
            return $this->authProviderOAuthService->callback($request, $key);
        } catch (Throwable $exception) {
            return redirect()->to('/login?auth_error='.urlencode($exception->getMessage()));
        }
    }
}
