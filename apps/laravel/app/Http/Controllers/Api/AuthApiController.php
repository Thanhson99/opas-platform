<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResendVerificationEmailRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyEmailCodeRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\Auth\AuthProviderService;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthApiController extends Controller
{
    /**
     * @return void
     */
    public function __construct(
        private readonly AuthProviderService $authProviderService,
        private readonly AuthSessionService $authSessionService,
        private readonly EmailVerificationService $emailVerificationService,
    ) {}

    /**
     * Return the currently authenticated user when a valid session exists.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return (new AuthUserResource($user))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Create a new email/password account and queue an email verification code.
     *
     * @param  RegisterRequest  $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        if (! $this->authProviderService->canUse('email', 'register')) {
            return response()->json([
                'message' => 'Email registration is not available.',
            ], 403);
        }

        /** @var array{name:string,email:string,password:string} $validated */
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Member,
            'email_verified_at' => null,
        ]);

        $this->emailVerificationService->sendCode($user);

        return (new AuthUserResource($user))
            ->additional([
                'message' => 'Account created successfully. Please verify your email address.',
                'meta' => [
                    'verification_required' => true,
                    'verification_expires_in_minutes' => $this->emailVerificationService->expireMinutes(),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authenticate an email/password account and block access until the email is verified.
     *
     * @param  LoginRequest  $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! $this->authProviderService->canUse('email', 'login')) {
            return response()->json([
                'message' => 'Email login is not available.',
            ], 403);
        }

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

        if (! $user->hasVerifiedEmail()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Please verify your email address before signing in.',
                'errors' => [
                    'email' => ['Please verify your email address before signing in.'],
                ],
                'meta' => [
                    'verification_required' => true,
                    'email' => $user->email,
                ],
            ], 403);
        }

        $this->authSessionService->storeLoginProvider($request, 'email');

        return (new AuthUserResource($user))
            ->additional(['message' => 'Đăng nhập thành công.'])
            ->response();
    }

    /**
     * Terminate the current authenticated session.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authSessionService->clearLoginProvider($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    /**
     * Re-send a fresh email verification code to an existing unverified account.
     *
     * @param  ResendVerificationEmailRequest  $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(ResendVerificationEmailRequest $request): JsonResponse
    {
        /** @var array{email:string} $validated */
        $validated = $request->validated();
        $this->emailVerificationService->resendCode($validated['email']);

        return response()->json([
            'message' => 'If the account exists and still needs verification, a verification email will be sent.',
        ]);
    }

    /**
     * Confirm an emailed verification code and activate the matching account.
     *
     * @param  VerifyEmailCodeRequest  $request
     * @return JsonResponse
     */
    public function verifyEmailCode(VerifyEmailCodeRequest $request): JsonResponse
    {
        /** @var array{email:string,code:string} $validated */
        $validated = $request->validated();
        $verification = $this->emailVerificationService->verifyCode($validated['email'], $validated['code']);

        if ($verification['status'] === 'verified') {
            return response()->json([
                'message' => 'Email verified successfully. You can sign in now.',
                'meta' => [
                    'status' => 'verified',
                    'email' => $validated['email'],
                ],
            ]);
        }

        if ($verification['status'] === 'already-verified') {
            return response()->json([
                'message' => 'This email address is already verified. You can sign in now.',
                'meta' => [
                    'status' => 'already-verified',
                    'email' => $validated['email'],
                ],
            ]);
        }

        if ($verification['status'] === 'expired') {
            return response()->json([
                'message' => 'This verification code has expired. Request a new code.',
                'errors' => [
                    'code' => ['This verification code has expired. Request a new code.'],
                ],
                'meta' => [
                    'status' => 'expired',
                    'email' => $validated['email'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'This verification code is invalid.',
            'errors' => [
                'code' => ['This verification code is invalid.'],
            ],
            'meta' => [
                'status' => 'invalid',
                'email' => $validated['email'],
            ],
        ], 422);
    }

    /**
     * Queue a password reset link email for the requested account email.
     *
     * @param  ForgotPasswordRequest  $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        /** @var array{email:string} $validated */
        $validated = $request->validated();
        Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return response()->json([
            'message' => 'If the account exists, a password reset link will be sent.',
        ]);
    }

    /**
     * Reset the account password using a valid reset token payload.
     *
     * @param  ResetPasswordRequest  $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        /** @var array{email:string,password:string,password_confirmation:string,token:string} $validated */
        $validated = $request->validated();

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Unable to reset the password with this link.',
                'errors' => [
                    'email' => ['This password reset link is invalid or has expired.'],
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }
}
