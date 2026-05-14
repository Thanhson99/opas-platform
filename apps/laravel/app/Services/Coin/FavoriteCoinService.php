<?php

declare(strict_types=1);

namespace App\Services\Coin;

use App\Repositories\Coin\Interfaces\FavoriteCoinRepositoryInterface;

/**
 * Class FavoriteCoinService
 *
 * Provides business logic for managing user's favorite coins.
 */
class FavoriteCoinService implements FavoriteCoinServiceInterface
{
    /**
     * FavoriteCoinService constructor.
     */
    public function __construct(
        protected FavoriteCoinRepositoryInterface $repository
    ) {}

    /**
     * Get all favorited coin symbols.
     *
     * @return array<int, string>
     */
    public function getSymbols(): array
    {
        return $this->repository->getAllSymbols();
    }

    /**
     * Add a coin to favorites.
     *
     * @param  string  $symbol  The coin symbol to add.
     * @return array<string, string> Contains message and status.
     */
    public function addSymbol(string $symbol): array
    {
        return $this->repository->addSymbol($symbol);
    }

    /**
     * Remove a coin from favorites.
     *
     * @param  string  $symbol  The coin symbol to remove.
     * @return array<string, string> Contains message and status.
     */
    public function removeSymbol(string $symbol): array
    {
        return $this->repository->removeSymbol($symbol);
    }

    /**
     * Toggle the favorite status of a coin.
     *
     * @param  string  $symbol  The coin symbol to toggle.
     * @return array<string, string> Contains message and status.
     */
    public function toggleSymbol(string $symbol): array
    {
        return $this->repository->toggleSymbol($symbol);
    }
}
