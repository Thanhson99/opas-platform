<?php

declare(strict_types=1);

namespace App\Services\Stock;

/**
 * Interface FavoriteStockServiceInterface
 *
 * Defines methods for managing user's favorite stocks.
 */
interface FavoriteStockServiceInterface
{
    /**
     * Get all favorited stock symbols.
     *
     * @return array<int, string> List of favorited symbols.
     */
    public function getSymbols(): array;

    /**
     * Add a stock to favorites.
     *
     * @param  string  $symbol  Stock symbol to add.
     * @return array{message: string, status: string}
     */
    public function addSymbol(string $symbol): array;

    /**
     * Remove a stock from favorites.
     *
     * @param  string  $symbol  Stock symbol to remove.
     * @return array{message: string, status: string}
     */
    public function removeSymbol(string $symbol): array;

    /**
     * Toggle the favorite status of a stock.
     *
     * @param  string  $symbol  Stock symbol to toggle.
     * @return array{message: string, status: string}
     */
    public function toggleSymbol(string $symbol): array;
}
