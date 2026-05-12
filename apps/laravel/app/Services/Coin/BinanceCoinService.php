<?php

declare(strict_types=1);

namespace App\Services\Coin;

/**
 * Class BinanceCoinService
 *
 * Service for handling coin data from Binance.
 */
class BinanceCoinService implements CoinServiceInterface
{
    protected CoinApiClientInterface $client;

    /**
     * BinanceCoinService constructor.
     *
     * @param  CoinApiClientInterface  $client  Coin API client implementation.
     * @return void
     */
    public function __construct(CoinApiClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Get a list of top coins with pricing info.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopCoins(): array
    {
        return $this->client->fetchTopCoins();
    }

    /**
     * Get detailed info for a specific coin.
     *
     * @param  string  $coinId  Coin symbol or identifier.
     * @return array<string, mixed>|null
     */
    public function getCoinById(string $coinId): ?array
    {
        return $this->client->fetchCoinDetail($coinId);
    }
}
