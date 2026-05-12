<?php

declare(strict_types=1);

namespace App\Services\Stock;

/**
 * Interface StockServiceInterface
 *
 * Defines methods for fetching stock data.
 */
interface StockServiceInterface
{
    /**
     * Get a list of listed stocks.
     *
     * @return array<int, array<string, string>>
     */
    public function getListedStocks(): array;
}
