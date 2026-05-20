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
        private readonly AuthSecurityAuditService $authSecurityAuditService,
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
     * @return string
     */
    public function resendCode(string $email): string
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user instanceof User || $user->hasVerifiedEmail()) {
            $this->authSecurityAuditService->logVerificationResendRequested($email, $user, false);

            return 'ignored';
        }

        $this->sendCode($user);

        $this->authSecurityAuditService->logVerificationResendRequested($email, $user, true);

        return 'sent';
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
            return $this->buildVerificationResult($email, 'invalid', null);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->buildVerificationResult($email, 'already-verified', $user);
        }

        $verificationCode = $this->emailVerificationCodeRepository->findByUserId($user->id);

        if ($verificationCode === null || $verificationCode->verified_at !== null) {
            return $this->buildVerificationResult($email, 'invalid', $user);
        }

        if ($verificationCode->expires_at->isPast()) {
            return $this->buildVerificationResult($email, 'expired', $user);
        }

        if (! Hash::check($code, $verificationCode->code_hash)) {
            return $this->buildVerificationResult($email, 'invalid', $user);
        }

        $verifiedAt = now();

        $verifiedUser = $this->userRepository->markEmailVerified($user, $verifiedAt);
        $this->emailVerificationCodeRepository->markAsVerified($verificationCode, $verifiedAt);

        return $this->buildVerificationResult($email, 'verified', $verifiedUser);
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

    /**
     * Return one verification result payload and emit the matching audit event.
     *
     * @param  string  $email
     * @param  'verified'|'already-verified'|'invalid'|'expired'  $status
     * @param  User|null  $user
     * @return array{status:'verified'|'already-verified'|'invalid'|'expired',user:User|null}
     */
    private function buildVerificationResult(string $email, string $status, ?User $user): array
    {
        $this->authSecurityAuditService->logVerificationCodeChecked($email, $status, $user);

        return [
            'status' => $status,
            'user' => $user,
        ];
    }
}
