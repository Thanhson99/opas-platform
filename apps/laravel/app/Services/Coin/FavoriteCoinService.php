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
