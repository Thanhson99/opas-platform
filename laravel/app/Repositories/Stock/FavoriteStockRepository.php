<?php

declare(strict_types=1);

namespace App\Repositories\Stock;

use App\Models\FavoriteStock;
use App\Repositories\BaseRepository;
use App\Repositories\Stock\Interfaces\FavoriteStockRepositoryInterface;

/**
 * Class FavoriteStockRepository
 *
 * Handles data operations related to favorite stocks.
 *
 * @extends BaseRepository<FavoriteStock>
 */
class FavoriteStockRepository extends BaseRepository implements FavoriteStockRepositoryInterface
{
    /**
     * FavoriteStockRepository constructor.
     *
     * @param  FavoriteStock  $model  Favorite stock model instance.
     * @return void
     */
    public function __construct(FavoriteStock $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all favorite stock symbols.
     *
     * @return array<int, string> List of favorite symbols.
     */
    public function getAllSymbols(): array
    {
        return $this->model
            ->newQuery()
            ->pluck('symbol')
            ->filter(fn ($value) => is_string($value))
            ->values()
            ->all();
    }

    /**
     * Add a symbol to favorites.
     *
     * @param  string  $symbol  The stock symbol to add.
     * @return array<string, string> Result message and status.
     */
    public function addSymbol(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        $existing = $this->model->newQuery()->where('symbol', $symbol)->first();

        if ($existing) {
            return [
                'message' => 'Already in favorites',
                'status' => 'exists',
            ];
        }

        $this->model->newQuery()->create(['symbol' => $symbol]);

        return [
            'message' => 'Added to favorites',
            'status' => 'added',
        ];
    }

    /**
     * Remove a symbol from favorites.
     *
     * @param  string  $symbol  The stock symbol to remove.
     * @return array<string, string> Result message and status.
     */
    public function removeSymbol(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        $existing = $this->model->newQuery()->where('symbol', $symbol)->first();

        if (! $existing) {
            return [
                'message' => 'Not in favorites',
                'status' => 'missing',
            ];
        }

        $existing->delete();

        return [
            'message' => 'Removed from favorites',
            'status' => 'removed',
        ];
    }

    /**
     * Toggle favorite status for a given symbol.
     *
     * If the symbol exists, it will be removed.
     * If it does not exist, it will be added.
     *
     * @param  string  $symbol  The stock symbol to toggle.
     * @return array<string, string> Result message and status.
     */
    public function toggleSymbol(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        $existing = $this->model->newQuery()->where('symbol', $symbol)->first();

        if ($existing) {
            $existing->delete();

            return [
                'message' => 'Removed from favorites',
                'status' => 'removed',
            ];
        }

        $this->model->newQuery()->create(['symbol' => $symbol]);

        return [
            'message' => 'Added to favorites',
            'status' => 'added',
        ];
    }
}
