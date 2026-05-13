<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_register_a_member_account(): void
    {
        $response = $this->postJson(route('api.auth.register'), [
            'name' => 'OPAS User',
            'email' => 'member@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'member@gmail.com')
            ->assertJsonPath('data.role', UserRole::Member->value);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'member@gmail.com',
            'role' => UserRole::Member->value,
        ]);
    }

    public function test_it_can_login_and_fetch_current_user(): void
    {
        $user = User::factory()->create([
            'email' => 'vip@gmail.com',
            'password' => 'Password123!',
            'role' => UserRole::Vip,
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
}
