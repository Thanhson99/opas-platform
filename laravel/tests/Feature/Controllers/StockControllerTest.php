<?php

namespace Tests\Feature;

use App\Services\Stock\FavoriteStockServiceInterface;
use App\Services\Stock\StockServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the stock index page loads successfully.
     */
    public function test_it_can_display_stock_index_page(): void
    {
        $stockService = Mockery::mock(StockServiceInterface::class);
        $stockService->shouldReceive('getListedStocks')->once()->andReturn([
            ['symbol' => 'VNM', 'name' => 'Vietnam Dairy Products JSC', 'exchange' => 'HOSE'],
        ]);

        $favoriteService = Mockery::mock(FavoriteStockServiceInterface::class);
        $favoriteService->shouldReceive('getSymbols')->once()->andReturn([
            'VNM',
        ]);

        $this->app->instance(StockServiceInterface::class, $stockService);
        $this->app->instance(FavoriteStockServiceInterface::class, $favoriteService);

        $response = $this->get(route('stocks.index'));

        $response->assertStatus(200);
        $response->assertViewIs('stocks.index');
        $response->assertViewHas('stocks');
        $response->assertViewHas('favorites', ['VNM']);
    }
}
