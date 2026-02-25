<?php

declare(strict_types=1);

namespace App\Repositories\Stock;

use App\Models\Stock;
use App\Repositories\BaseRepository;
use App\Repositories\Stock\Interfaces\StockRepositoryInterface;

/**
 * Class StockRepository
 *
 * Handles data operations related to listed stocks.
 *
 * @extends BaseRepository<Stock>
 */
class StockRepository extends BaseRepository implements StockRepositoryInterface
{
    /**
     * StockRepository constructor.
     */
    public function __construct(Stock $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all listed stocks.
     *
     * @return array<int, array<string, string>>
     */
    public function getAllStocks(): array
    {
        return $this->model
            ->newQuery()
            ->orderBy('symbol')
            ->get(['symbol', 'name', 'exchange'])
            ->map(function ($stock) {
                return [
                    'symbol' => (string) $stock->symbol,
                    'name' => (string) $stock->name,
                    'exchange' => (string) $stock->exchange,
                ];
            })
            ->all();
    }
}
