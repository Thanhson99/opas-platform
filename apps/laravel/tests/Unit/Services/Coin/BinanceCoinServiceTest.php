<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Coin;

use App\Services\Coin\BinanceCoinService;
use App\Services\Coin\CoinApiClientInterface;
use PHPUnit\Framework\TestCase;

class BinanceCoinServiceTest extends TestCase
{
    /**
     * Verify that the service delegates the top-coin request to the API client.
     *
     * @return void
     */
    public function test_get_top_coins_returns_data(): void
    {
        $mockClient = $this->createMock(CoinApiClientInterface::class);
        $mockClient->method('fetchTopCoins')
            ->willReturn([['symbol' => 'BTCUSDT', 'price' => 30000]]);

        $service = new BinanceCoinService($mockClient);
        $result = $service->getTopCoins();

        $this->assertIsArray($result);
        $this->assertEquals('BTCUSDT', $result[0]['symbol']);
    }

    /**
     * Verify that the service returns the expected coin detail payload.
     *
     * @return void
     */
    public function test_get_coin_by_id_returns_correct_coin(): void
    {
        $mockClient = $this->createMock(CoinApiClientInterface::class);
        $mockClient->method('fetchCoinDetail')
            ->with('BTCUSDT')
            ->willReturn(['symbol' => 'BTCUSDT', 'price' => 30000]);

        $service = new BinanceCoinService($mockClient);
        $coin = $service->getCoinById('BTCUSDT');

        $this->assertIsArray($coin);
        $this->assertEquals('BTCUSDT', $coin['symbol']);
    }
}
