<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coins;

use App\Http\Controllers\Controller;
use App\Services\Coin\CoinServiceInterface;
use App\Services\Coin\FavoriteCoinServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Class CoinController
 *
 * Handles display of coin list and watchlist actions.
 */
class CoinController extends Controller
{
    public function __construct(
        protected CoinServiceInterface $coinService,
        protected FavoriteCoinServiceInterface $favoriteCoinService
    ) {}

    /**
     * Display a list of popular coins from selected source.
     *
     * @param  Request  $request  Incoming HTTP request.
     */
    public function index(Request $request): View
    {
        $coins = $this->coinService->getTopCoins();
        $favorites = $this->favoriteCoinService->getSymbols();
        $source = $request->get('source', 'binance');

        return view('coins.index', compact('coins', 'favorites', 'source'));
    }

    /**
     * Display detail of a single coin.
     *
     * @param  string  $symbol  Coin symbol.
     */
    public function show(string $symbol): View
    {
        $coins = $this->coinService->getCoinById($symbol);

        return view('coins.show', compact('coins'));
    }
}
