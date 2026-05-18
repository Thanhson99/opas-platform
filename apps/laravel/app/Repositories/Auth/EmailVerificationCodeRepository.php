<?php

declare(strict_types=1);

namespace App\Repositories\Auth;

use App\Models\EmailVerificationCode;
use App\Repositories\Auth\Interfaces\EmailVerificationCodeRepositoryInterface;
use App\Repositories\BaseRepository;
use Illuminate\Support\Carbon;

/**
 * @extends BaseRepository<EmailVerificationCode>
 */
class EmailVerificationCodeRepository extends BaseRepository implements EmailVerificationCodeRepositoryInterface
{
    /**
     * Inject the email verification code model used by the repository.
     *
     * @return void
     */
    public function __construct(EmailVerificationCode $model)
    {
        parent::__construct($model);
    }

    /**
     * Find the stored verification code record for the given user.
     *
     * @param  int  $userId
     * @return EmailVerificationCode|null
     */
    public function findByUserId(int $userId): ?EmailVerificationCode
    {
        $verificationCode = $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->first();

        return $verificationCode instanceof EmailVerificationCode ? $verificationCode : null;
    }

    /**
     * Persist a fresh verification code payload for the given user.
     *
     * @param  int  $userId
     * @param  string  $codeHash
     * @param  Carbon  $expiresAt
     * @param  Carbon  $sentAt
     * @return EmailVerificationCode
     */
    public function upsertForUser(int $userId, string $codeHash, Carbon $expiresAt, Carbon $sentAt): EmailVerificationCode
    {
        /** @var EmailVerificationCode $verificationCode */
        $verificationCode = $this->model
            ->newQuery()
            ->updateOrCreate(
                ['user_id' => $userId],
                [
                    'code_hash' => $codeHash,
                    'expires_at' => $expiresAt,
                    'last_sent_at' => $sentAt,
                    'verified_at' => null,
                ],
            );

        return $verificationCode->refresh();
    }

    /**
     * Mark the verification code as consumed.
     *
     * @param  EmailVerificationCode  $verificationCode
     * @param  Carbon  $verifiedAt
     * @return EmailVerificationCode
     */
    public function markAsVerified(EmailVerificationCode $verificationCode, Carbon $verifiedAt): EmailVerificationCode
    {
        $verificationCode->forceFill([
            'verified_at' => $verifiedAt,
        ])->save();

        return $verificationCode->refresh();
    }
}
