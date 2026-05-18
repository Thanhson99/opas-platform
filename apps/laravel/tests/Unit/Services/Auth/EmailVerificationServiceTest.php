<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verify email verification service branching independently from HTTP transport.
 */
class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The configured verification lifetime should default to ten minutes for the email flow.
     */
    public function test_expire_minutes_defaults_to_ten_minutes(): void
    {
        config()->set('opas.auth.email_verification.expire_minutes', 10);

        $minutes = $this->app->make(EmailVerificationService::class)->expireMinutes();

        $this->assertSame(10, $minutes);
    }

    /**
     * Verification lifetime must never exceed the short upper bound enforced by the service.
     */
    public function test_expire_minutes_is_capped_at_fifteen_minutes(): void
    {
        config()->set('opas.auth.email_verification.expire_minutes', 60);

        $minutes = $this->app->make(EmailVerificationService::class)->expireMinutes();

        $this->assertSame(15, $minutes);
    }

    /**
     * Verification lifetime should clamp tiny config values up to the minimum supported window.
     */
    public function test_expire_minutes_uses_minimum_five_minutes(): void
    {
        config()->set('opas.auth.email_verification.expire_minutes', 1);

        $minutes = $this->app->make(EmailVerificationService::class)->expireMinutes();

        $this->assertSame(5, $minutes);
    }

    /**
     * Already verified users should resolve to the idempotent verified-state response.
     */
    public function test_verify_code_returns_already_verified_for_verified_user(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@gmail.com',
            'email_verified_at' => now(),
        ]);

        $result = $this->app->make(EmailVerificationService::class)->verifyCode($user->email, '123456');

        $this->assertSame('already-verified', $result['status']);
        $this->assertSame($user->id, $result['user']?->id);
    }

    /**
     * Missing verification code records should produce the invalid-code response.
     */
    public function test_verify_code_returns_invalid_when_no_code_exists(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'member@gmail.com',
        ]);

        $result = $this->app->make(EmailVerificationService::class)->verifyCode($user->email, '123456');

        $this->assertSame('invalid', $result['status']);
        $this->assertSame($user->id, $result['user']?->id);
    }

    /**
     * Matching codes should verify both the user account and the stored code record.
     */
    public function test_verify_code_marks_user_and_code_as_verified_when_code_matches(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'member@gmail.com',
        ]);

        EmailVerificationCode::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'last_sent_at' => now(),
            'verified_at' => null,
        ]);

        $result = $this->app->make(EmailVerificationService::class)->verifyCode($user->email, '123456');

        $this->assertSame('verified', $result['status']);
        $this->assertNotNull($user->fresh()?->email_verified_at);
        $this->assertNotNull(
            EmailVerificationCode::query()->where('user_id', $user->id)->firstOrFail()->verified_at,
        );
    }
}
