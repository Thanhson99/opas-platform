<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\AuthProvider;
use App\Models\User;
use App\Notifications\QueuedVerifyEmailNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Cover the email/password auth endpoints that remain as the baseline login method.
 */
class AuthApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Registration should create the account and queue an email verification message.
     */
    public function test_it_can_register_a_member_account(): void
    {
        Notification::fake();

        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'OPAS User',
            'email' => 'member@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'member@gmail.com')
            ->assertJsonPath('data.role', UserRole::Member->value)
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('meta.verification_required', true);

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'member@gmail.com',
            'role' => UserRole::Member->value,
        ]);

        $user = User::query()->where('email', 'member@gmail.com')->firstOrFail();
        Notification::assertSentTo($user, QueuedVerifyEmailNotification::class);
    }

    /**
     * Registration should mark the account verified immediately when the email provider disables verification.
     */
    public function test_it_registers_a_verified_account_when_email_verification_is_disabled(): void
    {
        Notification::fake();

        AuthProvider::query()->where('key', 'email')->update([
            'email_verification_mode' => 'disabled',
        ]);

        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'OPAS User',
            'email' => 'disabled-mode@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'disabled-mode@gmail.com')
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('meta.verification_required', false);

        $user = User::query()->where('email', 'disabled-mode@gmail.com')->firstOrFail();

        $this->assertNotNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    /**
     * A valid login should establish the session and allow the current user endpoint to resolve.
     */
    public function test_it_can_login_and_fetch_current_user(): void
    {
        $user = User::factory()->create([
            'email' => 'vip@gmail.com',
            'password' => 'Password123!',
            'role' => UserRole::Vip,
            'email_verified_at' => now(),
        ]);

        $loginResponse = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', UserRole::Vip->value);

        $meResponse = $this->getJson(route('api.auth.me'));

        $meResponse->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role_label', UserRole::Vip->label());
    }

    /**
     * Logging out should terminate the current session cleanly.
     */
    public function test_it_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson(route('api.auth.logout'));

        $response->assertOk()
            ->assertJson([
                'message' => 'Đăng xuất thành công.',
            ]);

        $this->assertGuest();
    }

    public function test_it_blocks_login_when_email_is_not_verified(): void
    {
        $user = User::factory()->create([
            'email' => 'member@gmail.com',
            'password' => 'Password123!',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('meta.verification_required', true)
            ->assertJsonPath('meta.email', $user->email);

        $this->assertGuest();
    }

    /**
     * Optional verification mode should allow unverified users to sign in while still supporting verification mail.
     */
    public function test_it_allows_login_when_email_verification_is_optional(): void
    {
        AuthProvider::query()->where('key', 'email')->update([
            'email_verification_mode' => 'optional',
        ]);

        $user = User::factory()->create([
            'email' => 'optional-mode@gmail.com',
            'password' => 'Password123!',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    /**
     * The baseline email login endpoint must respect provider availability rules.
     */
    public function test_it_blocks_email_login_when_provider_is_disabled(): void
    {
        AuthProvider::query()->where('key', 'email')->update([
            'enabled' => false,
        ]);

        $user = User::factory()->create([
            'password' => 'Password123!',
        ]);

        $response = $this->postJson(route('api.auth.login'), [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Email login is not available.',
            ]);
    }

    /**
     * Registration must also stop when the email provider has been disabled.
     */
    public function test_it_blocks_email_registration_when_provider_is_disabled(): void
    {
        AuthProvider::query()->where('key', 'email')->update([
            'enabled' => false,
        ]);

        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'OPAS User',
            'email' => 'member@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'message' => 'Email registration is not available.',
            ]);
    }

    public function test_it_can_resend_verification_email_for_unverified_account(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'member@gmail.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson(route('api.auth.verification.send'), [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If the account exists and still needs verification, a verification email will be sent.',
            ]);

        Notification::assertSentTo($user, QueuedVerifyEmailNotification::class);
    }

    /**
     * The resend verification endpoint should not leak whether an email exists or is already verified.
     */
    public function test_resend_verification_email_does_not_enumerate_accounts(): void
    {
        Notification::fake();

        $verifiedUser = User::factory()->create([
            'email' => 'verified@gmail.com',
            'email_verified_at' => now(),
        ]);

        $missingAccountResponse = $this->postJson(route('api.auth.verification.send'), [
            'email' => 'missing@gmail.com',
        ]);

        $verifiedUserResponse = $this->postJson(route('api.auth.verification.send'), [
            'email' => $verifiedUser->email,
        ]);

        $missingAccountResponse->assertOk()
            ->assertJson([
                'message' => 'If the account exists and still needs verification, a verification email will be sent.',
            ]);

        $verifiedUserResponse->assertOk()
            ->assertJson([
                'message' => 'If the account exists and still needs verification, a verification email will be sent.',
            ]);

        Notification::assertNothingSent();
    }

    /**
     * Resend verification should remain generic and avoid sending mail when verification is disabled.
     */
    public function test_resend_verification_email_stays_generic_when_verification_is_disabled(): void
    {
        Notification::fake();

        AuthProvider::query()->where('key', 'email')->update([
            'email_verification_mode' => 'disabled',
        ]);

        $user = User::factory()->create([
            'email' => 'disabled-verification@gmail.com',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson(route('api.auth.verification.send'), [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If the account exists and still needs verification, a verification email will be sent.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_it_marks_email_as_verified_from_signed_link(): void
    {
        $user = User::factory()->create([
            'email' => 'member@gmail.com',
            'email_verified_at' => null,
        ]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ],
        );

        $response = $this->get($url);

        $response->assertRedirect('/verify-email?status=verified&email=member%40gmail.com');
        $this->assertNotNull($user->fresh()?->email_verified_at);
    }

    public function test_it_can_send_a_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'member@gmail.com',
        ]);

        $response = $this->postJson(route('api.auth.password.email'), [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If the account exists, a password reset link will be sent.',
            ]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * The forgot password endpoint should not reveal whether an email address exists.
     */
    public function test_forgot_password_does_not_enumerate_accounts(): void
    {
        Notification::fake();

        $response = $this->postJson(route('api.auth.password.email'), [
            'email' => 'missing@gmail.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If the account exists, a password reset link will be sent.',
            ]);

        Notification::assertNothingSent();
    }

    /**
     * Auth endpoints should disable browser caching because they operate on sensitive session state.
     */
    public function test_auth_provider_listing_is_not_cacheable(): void
    {
        $response = $this->getJson(route('api.auth.providers.index'));

        $cacheControl = (string) $response->headers->get('Cache-Control', '');

        $response->assertOk()
            ->assertHeader('Pragma', 'no-cache');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function test_it_can_reset_the_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'member@gmail.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson(route('api.auth.password.update'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Password reset successfully.',
            ]);

        $this->assertTrue(Hash::check('NewPassword123!', (string) $user->fresh()?->password));
    }
}
