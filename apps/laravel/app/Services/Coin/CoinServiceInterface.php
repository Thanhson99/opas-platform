<?php

declare(strict_types=1);

namespace App\Services\Coin;

/**
 * Interface CoinServiceInterface
 *
 * Define methods for fetching coin data.
 */
interface CoinServiceInterface
{
    /**
     * Get a list of top coins with pricing info.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTopCoins(): array;

    /**
     * Get detailed info for a specific coin.
     *
     * @param  string  $coinId  Coin symbol or identifier.
     * @return array<string, mixed>|null
     */
    public function getCoinById(string $coinId): ?array;
}
