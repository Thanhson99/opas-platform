<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\FavoriteCoin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify favorite coin endpoints, including verification-gated access rules.
 */
class FavoriteCoinControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a valid symbol is added to favorites.
     */
    public function test_it_can_add_coin_to_favorites(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('api.coins.favorites.store', ['symbol' => 'BTCUSDT']));

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Added to favorites',
                'data' => ['status' => 'added'],
                'success' => true,
            ]);

        $this->assertDatabaseHas('favorite_coins', ['symbol' => 'BTCUSDT']);
    }

    /**
     * Test that an existing symbol is removed from favorites.
     */
    public function test_it_can_remove_coin_from_favorites(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        FavoriteCoin::create(['symbol' => 'BTCUSDT']);

        $response = $this
            ->actingAs($user)
            ->deleteJson(route('api.coins.favorites.destroy', ['symbol' => 'BTCUSDT']));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Removed from favorites',
                'data' => ['status' => 'removed'],
                'success' => true,
            ]);

        $this->assertDatabaseMissing('favorite_coins', ['symbol' => 'BTCUSDT']);
    }

    /**
     * Test that an invalid symbol is rejected.
     */
    public function test_it_returns_error_for_invalid_symbol(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('api.coins.favorites.store', ['symbol' => '!!!']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symbol']);
    }

    /**
     * Guests must authenticate before they can add favorite coins.
     */
    public function test_guest_cannot_add_coin_to_favorites(): void
    {
        $response = $this->putJson(route('api.coins.favorites.store', ['symbol' => 'BTCUSDT']));

        $response->assertStatus(401);
    }

    /**
     * Signed-in users must still verify their email before using protected favorite endpoints.
     */
    public function test_unverified_user_cannot_add_coin_to_favorites(): void
    {
        $user = User::factory()->unverified()->create(['role' => UserRole::Member]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('api.coins.favorites.store', ['symbol' => 'BTCUSDT']));

        $response->assertForbidden()
            ->assertJsonPath('meta.verification_required', true)
            ->assertJsonPath('meta.email', $user->email);
    }
}
