<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return (new AuthUserResource($user))->response();
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        /** @var array{name:string,email:string,password:string} $validated */
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Member,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return (new AuthUserResource($user))
            ->additional(['message' => 'Account created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var array{email:string,password:string} $credentials */
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, true)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng.',
                'errors' => [
                    'email' => ['Email hoặc mật khẩu không đúng.'],
                ],
            ], 422);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        return (new AuthUserResource($user))
            ->additional(['message' => 'Đăng nhập thành công.'])
            ->response();
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }
}
