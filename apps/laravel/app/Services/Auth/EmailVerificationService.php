<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\QueuedVerifyEmailNotification;
use App\Repositories\Auth\Interfaces\EmailVerificationCodeRepositoryInterface;
use App\Repositories\User\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class EmailVerificationService
{
    /**
     * @return void
     */
    public function __construct(
        private readonly EmailVerificationCodeRepositoryInterface $emailVerificationCodeRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * Issue a new verification code and send it to the given user.
     *
     * @param  User  $user
     * @return void
     */
    public function sendCode(User $user): void
    {
        $code = $this->generateCode();
        $sentAt = now();

        $this->emailVerificationCodeRepository->upsertForUser(
            $user->id,
            Hash::make($code),
            $sentAt->copy()->addMinutes($this->expireMinutes()),
            $sentAt,
        );

        $user->notify(new QueuedVerifyEmailNotification($code, $this->expireMinutes()));
    }

    /**
     * Re-issue a verification code for the matching unverified account.
     *
     * @param  string  $email
     * @return void
     */
    public function resendCode(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user instanceof User || $user->hasVerifiedEmail()) {
            return;
        }

        $this->sendCode($user);
    }

    /**
     * Validate the submitted verification code and activate the account when valid.
     *
     * @param  string  $email
     * @param  string  $code
     * @return array{status:'verified'|'already-verified'|'invalid'|'expired',user:User|null}
     */
    public function verifyCode(string $email, string $code): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user instanceof User) {
            return [
                'status' => 'invalid',
                'user' => null,
            ];
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'status' => 'already-verified',
                'user' => $user,
            ];
        }

        $verificationCode = $this->emailVerificationCodeRepository->findByUserId($user->id);

        if ($verificationCode === null || $verificationCode->verified_at !== null) {
            return [
                'status' => 'invalid',
                'user' => $user,
            ];
        }

        if ($verificationCode->expires_at->isPast()) {
            return [
                'status' => 'expired',
                'user' => $user,
            ];
        }

        if (! Hash::check($code, $verificationCode->code_hash)) {
            return [
                'status' => 'invalid',
                'user' => $user,
            ];
        }

        $verifiedAt = now();

        $this->userRepository->markEmailVerified($user, $verifiedAt);
        $this->emailVerificationCodeRepository->markAsVerified($verificationCode, $verifiedAt);

        return [
            'status' => 'verified',
            'user' => $user->fresh(),
        ];
    }

    /**
     * Return the configured verification code lifetime in minutes.
     *
     * @return int
     */
    public function expireMinutes(): int
    {
        $configuredExpiry = config('opas.auth.email_verification.expire_minutes', 10);

        if (! is_int($configuredExpiry)) {
            return 10;
        }

        return max(5, min($configuredExpiry, 15));
    }

    /**
     * Return the configured numeric verification code length.
     *
     * @return int
     */
    public function codeLength(): int
    {
        $configuredLength = config('opas.auth.email_verification.code_length', 6);

        if (! is_int($configuredLength)) {
            return 6;
        }

        return max(4, min($configuredLength, 8));
    }

    /**
     * Generate a short numeric verification code for email confirmation.
     *
     * @return string
     */
    private function generateCode(): string
    {
        $minimum = 10 ** ($this->codeLength() - 1);
        $maximum = (10 ** $this->codeLength()) - 1;

        return (string) random_int($minimum, $maximum);
    }
}
