<?php

namespace Tests\Feature;

use App\Services\Coin\CoinServiceFactory;
use App\Services\Coin\CoinServiceInterface;
use App\Services\Coin\FavoriteCoinServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CoinControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the coin index page loads successfully.
     */
    public function test_it_can_display_coin_index_page(): void
    {
        $coinService = Mockery::mock(CoinServiceInterface::class);
        $coinService->shouldReceive('getTopCoins')->once()->andReturn([
            ['symbol' => 'BTCUSDT', 'price' => '65000'],
        ]);

        $favoriteService = Mockery::mock(FavoriteCoinServiceInterface::class);
        $favoriteService->shouldReceive('getSymbols')->once()->andReturn([
            'BTCUSDT',
        ]);

        $this->app->instance(CoinServiceInterface::class, $coinService);
        $this->app->instance(FavoriteCoinServiceInterface::class, $favoriteService);

        $response = $this->get(route('coins.index'));

        $response->assertStatus(200);
        $response->assertViewIs('coins.index');
        $response->assertViewHas('coins');
        $response->assertViewHas('favorites', ['BTCUSDT']);
    }

    /**
     * Test the coin detail page loads successfully.
     */
    public function test_it_can_display_coin_detail_page(): void
    {
        // Override CoinServiceFactory to return mocked service
        $mockedService = Mockery::mock(CoinServiceInterface::class);
        $mockedService->shouldReceive('getCoinById')
            ->with('BTCUSDT')
            ->once()
            ->andReturn([
                'symbol' => 'BTCUSDT',
                'lastPrice' => 66666,
                'quoteVolume' => 12345678,
                'priceChangePercent' => 5.12,
                'highPrice' => 67000,
                'lowPrice' => 64000,
                'openPrice' => 65000,
                'closeTime' => now()->getTimestampMs(),
            ]);

        $this->app->instance(CoinServiceInterface::class, $mockedService);

        $response = $this->get(route('coins.show', 'BTCUSDT'));

        $response->assertStatus(200);
        $response->assertViewIs('coins.show');
        $response->assertViewHas('coins');
    }
}
