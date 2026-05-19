<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRole;
use App\Models\FavoriteStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify favorite stock endpoints and their access guards.
 */
class FavoriteStockControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Members should be able to add a valid stock symbol to favorites.
     *
     * @return void
     */
    public function test_it_can_add_stock_to_favorites(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('api.stocks.favorites.store', ['symbol' => 'VNM']));

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Added to favorites',
                'data' => ['status' => 'added'],
                'success' => true,
            ]);

        $this->assertDatabaseHas('favorite_stocks', ['symbol' => 'VNM']);
    }

    /**
     * Members should be able to remove an existing stock symbol from favorites.
     *
     * @return void
     */
    public function test_it_can_remove_stock_from_favorites(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);
        FavoriteStock::create(['symbol' => 'VNM']);

        $response = $this
            ->actingAs($user)
            ->deleteJson(route('api.stocks.favorites.destroy', ['symbol' => 'VNM']));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Removed from favorites',
                'data' => ['status' => 'removed'],
                'success' => true,
            ]);

        $this->assertDatabaseMissing('favorite_stocks', ['symbol' => 'VNM']);
    }

    /**
     * Invalid stock symbols should be rejected by request validation.
     *
     * @return void
     */
    public function test_it_returns_error_for_invalid_symbol(): void
    {
        $user = User::factory()->create(['role' => UserRole::Member]);

        $response = $this
            ->actingAs($user)
            ->putJson(route('api.stocks.favorites.store', ['symbol' => '!!!']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['symbol']);
    }

    /**
     * Guests must authenticate before they can save favorite stocks.
     *
     * @return void
     */
    public function test_guest_cannot_add_stock_to_favorites(): void
    {
        $response = $this->putJson(route('api.stocks.favorites.store', ['symbol' => 'VNM']));

        $response->assertStatus(401);
    }
}
