<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Coin;

use App\Services\Coin\BinanceCoinService;
use App\Services\Coin\CoinServiceFactory;
use App\Services\Coin\CoinServiceInterface;
use Tests\TestCase;

class CoinServiceFactoryTest extends TestCase
{
    /**
     * Ensure the factory returns the Binance implementation for the Binance source.
     *
     * @return void
     */
    public function test_make_returns_binance_service()
    {
        $service = CoinServiceFactory::make('binance');

        $this->assertInstanceOf(CoinServiceInterface::class, $service);
        $this->assertInstanceOf(BinanceCoinService::class, $service);
    }

    /**
     * Ensure an unsupported source raises an explicit exception.
     *
     * @return void
     */
    public function test_make_throws_exception_for_unsupported_source()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported source [unknown]');

        CoinServiceFactory::make('unknown');
    }
}
