<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Models\User;
use App\Models\UserAuthIdentity;
use App\Services\Auth\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cover authenticated account settings behavior for profile updates and linked-provider management.
 */
class AccountApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authenticated users should be able to update their own display name.
     *
     * @return void
     */
    public function test_authenticated_user_can_update_their_profile_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([AuthSessionService::LOGIN_PROVIDER_SESSION_KEY => 'email'])
            ->putJson(route('api.auth.account.update'), [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.current_sign_in_provider.key', 'email');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Account profile updates should strip HTML tags and normalize whitespace before persistence.
     *
     * @return void
     */
    public function test_profile_name_is_sanitized_before_it_is_saved(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([AuthSessionService::LOGIN_PROVIDER_SESSION_KEY => 'email'])
            ->putJson(route('api.auth.account.update'), [
                'name' => '  <script>alert(1)</script>  Son   Hopee  ',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'alert(1) Son Hopee');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'alert(1) Son Hopee',
        ]);
    }

    /**
     * Guests must not be able to update authenticated account settings.
     *
     * @return void
     */
    public function test_guest_cannot_update_account_settings(): void
    {
        $response = $this->putJson(route('api.auth.account.update'), [
            'name' => 'Updated Name',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Email-session users should be able to unlink a previously linked Google provider safely.
     *
     * @return void
     */
    public function test_email_session_user_can_unlink_google_provider(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        UserAuthIdentity::query()->create([
            'user_id' => $user->id,
            'provider_key' => 'google',
            'provider_user_id' => 'google-user-1',
            'provider_email' => $user->email,
            'metadata' => ['sub' => 'google-user-1'],
            'access_token' => 'token-123',
            'refresh_token' => 'refresh-123',
            'token_expires_at' => now()->addHour(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([AuthSessionService::LOGIN_PROVIDER_SESSION_KEY => 'email'])
            ->deleteJson(route('api.auth.account.providers.destroy', ['key' => 'google']));

        $response->assertOk()
            ->assertJsonPath('data.current_sign_in_provider.key', 'email')
            ->assertJsonCount(0, 'data.linked_providers');

        $this->assertDatabaseMissing('user_auth_identities', [
            'user_id' => $user->id,
            'provider_key' => 'google',
        ]);
    }

    /**
     * The provider currently powering the session must stay linked until the user signs in another way.
     *
     * @return void
     */
    public function test_current_session_provider_cannot_be_unlinked(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        UserAuthIdentity::query()->create([
            'user_id' => $user->id,
            'provider_key' => 'google',
            'provider_user_id' => 'google-user-1',
            'provider_email' => $user->email,
            'metadata' => ['sub' => 'google-user-1'],
            'access_token' => 'token-123',
            'refresh_token' => 'refresh-123',
            'token_expires_at' => now()->addHour(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([AuthSessionService::LOGIN_PROVIDER_SESSION_KEY => 'google'])
            ->deleteJson(route('api.auth.account.providers.destroy', ['key' => 'google']));

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.provider.0',
                'You cannot unlink the login provider used by the current session.',
            );

        $this->assertDatabaseHas('user_auth_identities', [
            'user_id' => $user->id,
            'provider_key' => 'google',
        ]);
    }

    /**
     * Unlinking a provider that is not attached to the current account should fail clearly.
     *
     * @return void
     */
    public function test_it_rejects_unlinking_a_provider_that_is_not_linked(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([AuthSessionService::LOGIN_PROVIDER_SESSION_KEY => 'email'])
            ->deleteJson(route('api.auth.account.providers.destroy', ['key' => 'google']));

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.provider.0',
                'This login provider is not linked to the current account.',
            );
    }
}
