<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\FavoriteCoin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteCoinControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a valid symbol is added to favorites.
     */
    public function test_it_can_add_coin_to_favorites(): void
    {
        $response = $this->postJson(route('coins.favorites.toggle'), [
            'symbol' => 'BTCUSDT',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Added to favorites',
                'status' => 'added',
                'success' => true,
            ]);

        $this->assertDatabaseHas('favorite_coins', ['symbol' => 'BTCUSDT']);
    }

    /**
     * Test that an existing symbol is removed from favorites.
     */
    public function test_it_can_remove_coin_from_favorites(): void
    {
        FavoriteCoin::create(['symbol' => 'BTCUSDT']);

        $response = $this->postJson(route('coins.favorites.toggle'), [
            'symbol' => 'BTCUSDT',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Removed from favorites',
                'status' => 'removed',
                'success' => true,
            ]);

        $this->assertDatabaseMissing('favorite_coins', ['symbol' => 'BTCUSDT']);
    }

    /**
     * Test that an invalid symbol is rejected.
     */
    public function test_it_returns_error_for_invalid_symbol(): void
    {
        $response = $this->postJson(route('coins.favorites.toggle'), [
            'symbol' => '!!!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid symbol',
                'success' => false,
            ]);
    }
}
