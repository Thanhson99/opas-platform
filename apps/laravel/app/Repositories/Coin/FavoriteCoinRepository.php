<?php

declare(strict_types=1);

namespace App\Repositories\Coin;

use App\Models\FavoriteCoin;
use App\Repositories\BaseRepository;
use App\Repositories\Coin\Interfaces\FavoriteCoinRepositoryInterface;

/**
 * Class FavoriteCoinRepository
 *
 * Handles data operations related to favorite coins.
 *
 * @extends BaseRepository<FavoriteCoin>
 */
class FavoriteCoinRepository extends BaseRepository implements FavoriteCoinRepositoryInterface
{
    /**
     * FavoriteCoinRepository constructor.
     *
     * @param  FavoriteCoin  $model  Favorite coin model instance.
     * @return void
     */
    public function __construct(FavoriteCoin $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all favorite coin symbols.
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
     * @param  string  $symbol  The coin symbol to add.
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
     * @param  string  $symbol  The coin symbol to remove.
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
     * @param  string  $symbol  The coin symbol to toggle.
     * @return array<string, string> Result message and status.
     */
    public function toggleSymbol(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        // Check if the symbol is already marked as favorite
        $existing = $this->model->newQuery()->where('symbol', $symbol)->first();

        if ($existing) {
            $existing->delete();

            return [
                'message' => 'Removed from favorites',
                'status' => 'removed',
            ];
        }

        // Add new favorite entry
        $this->model->newQuery()->create(['symbol' => $symbol]);

        return [
            'message' => 'Added to favorites',
            'status' => 'added',
        ];
    }
}
