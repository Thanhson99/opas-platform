<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stocks;

use App\Http\Controllers\Controller;
use App\Services\Stock\FavoriteStockServiceInterface;
use App\Services\Stock\StockServiceInterface;
use Illuminate\Contracts\View\View;

/**
 * Class StockController
 *
 * Handles display of listed stocks and watchlist actions.
 */
class StockController extends Controller
{
    public function __construct(
        protected StockServiceInterface $stockService,
        protected FavoriteStockServiceInterface $favoriteStockService
    ) {}

    /**
     * Display a list of listed Vietnam stocks.
     */
    public function index(): View
    {
        $stocks = $this->stockService->getListedStocks();
        $favorites = $this->favoriteStockService->getSymbols();

        if ($favorites !== []) {
            $favoriteLookup = array_fill_keys($favorites, true);

            usort($stocks, function (array $left, array $right) use ($favoriteLookup): int {
                $leftFav = isset($favoriteLookup[$left['symbol']]);
                $rightFav = isset($favoriteLookup[$right['symbol']]);

                if ($leftFav !== $rightFav) {
                    return $leftFav ? -1 : 1;
                }

                return strcmp($left['symbol'], $right['symbol']);
            });
        }

        return view('stocks.index', compact('stocks', 'favorites'));
    }
}
