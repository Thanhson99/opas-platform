<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\FavoriteStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteStockControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that a valid symbol is added to favorites.
     */
    public function test_it_can_add_stock_to_favorites(): void
    {
        $response = $this->postJson(route('stocks.favorites.add'), [
            'symbol' => 'VNM',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Added to favorites',
                'status' => 'added',
                'success' => true,
            ]);

        $this->assertDatabaseHas('favorite_stocks', ['symbol' => 'VNM']);
    }

    /**
     * Test that an existing symbol is removed from favorites.
     */
    public function test_it_can_remove_stock_from_favorites(): void
    {
        FavoriteStock::create(['symbol' => 'VNM']);

        $response = $this->postJson(route('stocks.favorites.remove'), [
            'symbol' => 'VNM',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Removed from favorites',
                'status' => 'removed',
                'success' => true,
            ]);

        $this->assertDatabaseMissing('favorite_stocks', ['symbol' => 'VNM']);
    }

    /**
     * Test that an invalid symbol is rejected.
     */
    public function test_it_returns_error_for_invalid_symbol(): void
    {
        $response = $this->postJson(route('stocks.favorites.add'), [
            'symbol' => '!!!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid symbol',
                'success' => false,
            ]);
    }

    /**
     * Test that toggle endpoint still works.
     */
    public function test_it_can_toggle_stock_favorite(): void
    {
        $response = $this->postJson(route('stocks.favorites.toggle'), [
            'symbol' => 'VNM',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Added to favorites',
                'status' => 'added',
                'success' => true,
            ]);

        $this->assertDatabaseHas('favorite_stocks', ['symbol' => 'VNM']);
    }
}
