<?php

declare(strict_types=1);

namespace App\Repositories\Stock\Interfaces;

/**
 * Interface StockRepositoryInterface
 *
 * Defines the contract for fetching listed stock data.
 */
interface StockRepositoryInterface
{
    /**
     * Get all listed stocks.
     *
     * @return array<int, array<string, string>>
     */
    public function getAllStocks(): array;
}
