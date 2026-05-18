<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerified
{
    /**
     * Block protected features until the current account finishes email verification.
     *
     * @param  Request  $request
     * @param  Closure(Request): Response  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return $next($request);
        }

        return new JsonResponse([
            'message' => 'Please verify your email address before accessing this feature.',
            'errors' => [
                'email' => ['Please verify your email address before accessing this feature.'],
            ],
            'meta' => [
                'verification_required' => true,
                'email' => $user->email,
            ],
        ], 403);
    }
}
