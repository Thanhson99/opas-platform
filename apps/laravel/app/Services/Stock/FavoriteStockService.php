<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Repositories\Stock\Interfaces\FavoriteStockRepositoryInterface;

/**
 * Coordinate favorite stock mutations through the stock repository contract.
 */
class FavoriteStockService implements FavoriteStockServiceInterface
{
    /**
     * Inject the repository that persists favorite stock symbols.
     *
     * @return void
     */
    public function __construct(
        private readonly FavoriteStockRepositoryInterface $repository,
    ) {}

    /**
     * Get all favorited stock symbols.
     *
     * @return array<int, string>
     */
    public function getSymbols(): array
    {
        return $this->repository->getAllSymbols();
    }

    /**
     * Add a stock to favorites.
     *
     * @param  string  $symbol  Stock symbol to add.
     * @return array<string, string> Contains message and status.
     */
    public function addSymbol(string $symbol): array
    {
        return $this->repository->addSymbol($symbol);
    }

    /**
     * Remove a stock from favorites.
     *
     * @param  string  $symbol  Stock symbol to remove.
     * @return array<string, string> Contains message and status.
     */
    public function removeSymbol(string $symbol): array
    {
        return $this->repository->removeSymbol($symbol);
    }

    /**
     * Toggle the favorite status of a stock.
     *
     * @param  string  $symbol  Stock symbol to toggle.
     * @return array<string, string> Contains message and status.
     */
    public function toggleSymbol(string $symbol): array
    {
        return $this->repository->toggleSymbol($symbol);
    }
}
