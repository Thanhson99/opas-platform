<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUserApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admins should see managed user accounts and role metadata.
     *
     * @return void
     */
    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.users.index'));

        $response->assertOk()
            ->assertJsonFragment([
                'email' => $admin->email,
                'role' => UserRole::Admin->value,
            ])
            ->assertJsonFragment([
                'email' => $member->email,
                'role' => UserRole::Member->value,
            ])
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1);
    }

    /**
     * Admins should be able to paginate and search managed user accounts.
     *
     * @return void
     */
    public function test_admin_can_paginate_and_search_users(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        User::factory()->count(11)->create([
            'role' => UserRole::Member,
        ]);

        $target = User::factory()->create([
            'name' => 'Special Search User',
            'email' => 'special-search@example.com',
            'role' => UserRole::Vip,
        ]);

        $response = $this
            ->actingAs($admin)
            ->getJson(route('api.admin.users.index', [
                'search' => 'special-search',
                'per_page' => 10,
            ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 10);
    }

    /**
     * Non-admin users must not access the admin user management endpoint.
     *
     * @return void
     */
    public function test_non_admin_cannot_list_users(): void
    {
        $member = User::factory()->create([
            'role' => UserRole::Member,
        ]);

        $response = $this
            ->actingAs($member)
            ->getJson(route('api.admin.users.index'));

        $response->assertForbidden();
    }

    /**
     * Admins should be able to change account details and role without changing email identity.
     *
     * @return void
     */
    public function test_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Member,
        ]);
        $originalEmail = $user->email;

        $response = $this
            ->actingAs($admin)
            ->putJson(route('api.admin.users.update', ['id' => $user->id]), [
                'name' => 'Updated Name',
                'role' => UserRole::Vip->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', $originalEmail)
            ->assertJsonPath('data.role', UserRole::Vip->value);

        $user->refresh();

        $this->assertSame('Updated Name', $user->name);
        $this->assertSame($originalEmail, $user->email);
        $this->assertSame(UserRole::Vip, $user->role);
    }

    /**
     * The system must not allow the last remaining admin account to lose admin role.
     *
     * @return void
     */
    public function test_admin_cannot_demote_last_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(route('api.admin.users.update', ['id' => $admin->id]), [
                'name' => $admin->name,
                'role' => UserRole::Member->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Admins must not be able to remove admin access from the account they are currently using.
     *
     * @return void
     */
    public function test_admin_cannot_demote_current_account_even_if_other_admins_exist(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->putJson(route('api.admin.users.update', ['id' => $admin->id]), [
                'name' => $admin->name,
                'role' => UserRole::Member->value,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    /**
     * Admins should be able to delete a non-critical user account.
     *
     * @return void
     */
    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Plus,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(route('api.admin.users.destroy', ['id' => $user->id]));

        $response->assertOk()
            ->assertJson([
                'message' => 'User account deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * Admins should be able to reset a user password and trigger the notification email.
     *
     * @return void
     */
    public function test_admin_can_reset_user_password(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $user = User::factory()->create([
            'role' => UserRole::Member,
            'password' => Hash::make('OldPassword1!'),
        ]);

        $oldHash = $user->password;

        $response = $this
            ->actingAs($admin)
            ->postJson(route('api.admin.users.reset-password', ['id' => $user->id]));

        $response->assertOk()
            ->assertJson([
                'message' => 'A temporary password has been generated and emailed to the user.',
            ]);

        $this->assertNotSame($oldHash, (string) $user->fresh()->password);
        Notification::assertSentTo($user, AdminResetPasswordNotification::class);
    }

    /**
     * Admins must not be able to delete the final remaining admin account.
     *
     * @return void
     */
    public function test_admin_cannot_delete_last_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(route('api.admin.users.destroy', ['id' => $admin->id]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }

    /**
     * Admins must not be able to delete the account they are currently using.
     *
     * @return void
     */
    public function test_admin_cannot_delete_current_account_even_if_other_admins_exist(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $response = $this
            ->actingAs($admin)
            ->deleteJson(route('api.admin.users.destroy', ['id' => $admin->id]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user']);
    }
}
