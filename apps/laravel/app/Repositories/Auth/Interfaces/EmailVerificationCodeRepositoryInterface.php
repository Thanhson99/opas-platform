<?php

declare(strict_types=1);

namespace App\Repositories\Auth\Interfaces;

use App\Models\EmailVerificationCode;
use Illuminate\Support\Carbon;

interface EmailVerificationCodeRepositoryInterface
{
    /**
     * Find the stored verification code record for the given user.
     *
     * @param  int  $userId
     * @return EmailVerificationCode|null
     */
    public function findByUserId(int $userId): ?EmailVerificationCode;

    /**
     * Persist a fresh verification code payload for the given user.
     *
     * @param  int  $userId
     * @param  string  $codeHash
     * @param  Carbon  $expiresAt
     * @param  Carbon  $sentAt
     * @return EmailVerificationCode
     */
    public function upsertForUser(int $userId, string $codeHash, Carbon $expiresAt, Carbon $sentAt): EmailVerificationCode;

    /**
     * Mark the verification code as consumed.
     *
     * @param  EmailVerificationCode  $verificationCode
     * @param  Carbon  $verifiedAt
     * @return EmailVerificationCode
     */
    public function markAsVerified(EmailVerificationCode $verificationCode, Carbon $verifiedAt): EmailVerificationCode;
}
