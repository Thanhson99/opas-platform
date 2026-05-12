<?php

declare(strict_types=1);

namespace App\Services\Coin;

/**
 * Interface FavoriteCoinServiceInterface
 *
 * Defines methods for managing user's favorite coins.
 */
interface FavoriteCoinServiceInterface
{
    /**
     * Get all favorited coin symbols.
     *
     * @return array<int, string> List of favorited symbols.
     */
    public function getSymbols(): array;

    /**
     * Toggle the favorite status of a coin.
     *
     * @param  string  $symbol  Coin symbol to toggle.
     * @return array{message: string, status: string}
     */
    public function toggleSymbol(string $symbol): array;
}
