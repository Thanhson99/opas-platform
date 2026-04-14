<?php

declare(strict_types=1);

namespace App\Services\Coin;

/**
 * Interface CoinApiClientInterface
 *
 * Interface for calling external coin APIs.
 */
interface CoinApiClientInterface
{
    /**
     * Get market data for top coins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTopCoins(): array;

    /**
     * Get detailed info for a specific coin.
     *
     * @param  string  $coinId  Coin symbol or identifier.
     * @return array<string, mixed>|null
     */
    public function fetchCoinDetail(string $coinId): ?array;
}
