<?php

declare(strict_types=1);

namespace App\Repositories\Coin\Interfaces;

/**
 * Interface FavoriteCoinRepositoryInterface
 *
 * Defines the contract for managing favorite coin data.
 */
interface FavoriteCoinRepositoryInterface
{
    /**
     * Get all favorited coin symbols.
     *
     * @return array<int, string> List of favorited coin symbols.
     */
    public function getAllSymbols(): array;

    /**
     * Add a symbol to favorites if it does not already exist.
     *
     * @param  string  $symbol  The coin symbol to add.
     * @return array<string, string> Contains message and status keys.
     */
    public function addSymbol(string $symbol): array;

    /**
     * Remove a symbol from favorites if it exists.
     *
     * @param  string  $symbol  The coin symbol to remove.
     * @return array<string, string> Contains message and status keys.
     */
    public function removeSymbol(string $symbol): array;

    /**
     * Toggle the favorite status of a coin by symbol.
     *
     * If the symbol is already favorited, it will be removed.
     * If it is not, it will be added to the favorites.
     *
     * @param  string  $symbol  The coin symbol to toggle.
     * @return array<string, string> Contains message and status keys.
     */
    public function toggleSymbol(string $symbol): array;
}
