<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Repositories\Stock\Interfaces\StockRepositoryInterface;

/**
 * Class StockService
 *
 * Provides business logic for retrieving listed stocks.
 */
class StockService implements StockServiceInterface
{
    /**
     * StockService constructor.
     */
    public function __construct(
        protected StockRepositoryInterface $repository
    ) {}

    /**
     * Get a list of listed stocks.
     *
     * @return array<int, array<string, string>>
     */
    public function getListedStocks(): array
    {
        return $this->repository->getAllStocks();
    }
}
