<?php

declare(strict_types=1);

namespace App\Repositories\Stock\Interfaces;

/**
 * Interface FavoriteStockRepositoryInterface
 *
 * Defines the contract for managing favorite stock data.
 */
interface FavoriteStockRepositoryInterface
{
    /**
     * Get all favorited stock symbols.
     *
     * @return array<int, string> List of favorited stock symbols.
     */
    public function getAllSymbols(): array;

    /**
     * Add a symbol to favorites.
     *
     * @param  string  $symbol  The stock symbol to add.
     * @return array<string, string> Contains message and status keys.
     */
    public function addSymbol(string $symbol): array;

    /**
     * Remove a symbol from favorites.
     *
     * @param  string  $symbol  The stock symbol to remove.
     * @return array<string, string> Contains message and status keys.
     */
    public function removeSymbol(string $symbol): array;

    /**
     * Toggle the favorite status of a stock by symbol.
     *
     * If the symbol is already favorited, it will be removed.
     * If it is not, it will be added to the favorites.
     *
     * @param  string  $symbol  The stock symbol to toggle.
     * @return array<string, string> Contains message and status keys.
     */
    public function toggleSymbol(string $symbol): array;
}
