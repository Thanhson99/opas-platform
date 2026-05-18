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
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\Auth\AuthProviderService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        return (new AuthUserResource($user))->response();
    }

    /**
     * Create a new email/password account and queue an email verification notification.
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
        $verificationMode = $this->authProviderService->emailVerificationMode('email');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Member,
            'email_verified_at' => $verificationMode === 'disabled' ? now() : null,
        ]);

        if (in_array($verificationMode, ['required', 'optional'], true)) {
            $user->sendEmailVerificationNotification();
        }

        $meta = [];
        $message = 'Account created successfully.';

        if ($verificationMode === 'required') {
            $message = 'Account created successfully. Please verify your email address.';
            $meta['verification_required'] = true;
        }

        if ($verificationMode === 'optional') {
            $message = 'Account created successfully. You can verify your email later.';
            $meta['verification_required'] = false;
        }

        if ($verificationMode === 'disabled') {
            $message = 'Account created successfully.';
            $meta['verification_required'] = false;
        }

        return (new AuthUserResource($user))
            ->additional([
                'message' => $message,
                'meta' => $meta,
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
        $verificationMode = $this->authProviderService->emailVerificationMode('email');

        if ($verificationMode === 'required' && ! $user->hasVerifiedEmail()) {
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
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    /**
     * Re-send an email verification notification to an existing unverified account.
     *
     * @param  ResendVerificationEmailRequest  $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(ResendVerificationEmailRequest $request): JsonResponse
    {
        if ($this->authProviderService->emailVerificationMode('email') === 'disabled') {
            return response()->json([
                'message' => 'If the account exists and still needs verification, a verification email will be sent.',
            ]);
        }

        /** @var array{email:string} $validated */
        $validated = $request->validated();

        $user = User::query()->where('email', $validated['email'])->first();

        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => 'If the account exists and still needs verification, a verification email will be sent.',
        ]);
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

    /**
     * Consume a signed verification link and redirect the user back to the SPA status screen.
     *
     * @param  Request  $request
     * @param  int  $id
     * @param  string  $hash
     * @return RedirectResponse
     */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($id);

        if (! $user instanceof User || ! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->to('/verify-email?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->to('/verify-email?status=already-verified&email='.urlencode($user->email));
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->to('/verify-email?status=verified&email='.urlencode($user->email));
    }
}
