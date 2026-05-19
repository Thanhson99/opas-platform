<?php

declare(strict_types=1);

namespace App\Services\Coin;

use App\Repositories\Coin\Interfaces\FavoriteCoinRepositoryInterface;

/**
 * Coordinate favorite coin mutations through the coin repository contract.
 */
class FavoriteCoinService implements FavoriteCoinServiceInterface
{
    /**
     * Inject the repository that persists favorite coin symbols.
     *
     * @return void
     */
    public function __construct(
        private readonly FavoriteCoinRepositoryInterface $repository,
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
