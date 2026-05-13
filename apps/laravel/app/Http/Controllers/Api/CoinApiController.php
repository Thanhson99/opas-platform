<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoinResource;
use App\Services\Coin\CoinServiceInterface;
use App\Services\Coin\FavoriteCoinServiceInterface;
use Illuminate\Http\JsonResponse;

class CoinApiController extends Controller
{
    public function __construct(
        private readonly CoinServiceInterface $coinService,
        private readonly FavoriteCoinServiceInterface $favoriteCoinService,
    ) {}

    /**
     * Return the top coin list for the SPA.
     *
     * Adds a derived `is_favorite` flag so the frontend does not need
     * to merge favorite state manually.
     */
    public function index(): JsonResponse
    {
        $favorites = $this->favoriteCoinService->getSymbols();
        $favoriteLookup = array_fill_keys($favorites, true);

        $coins = array_map(
            fn (array $coin): array => [
                ...$coin,
                'is_favorite' => isset($favoriteLookup[$coin['symbol'] ?? '']),
            ],
            $this->coinService->getTopCoins(),
        );

        return CoinResource::collection(collect($coins))
            ->additional([
                'meta' => [
                    'favorites' => $favorites,
                ],
            ])
            ->response();
    }

    /**
     * Return the detail payload for a single coin symbol.
     */
    public function show(string $symbol): JsonResponse
    {
        $coin = $this->coinService->getCoinById($symbol);

        if ($coin === null) {
            return response()->json([
                'message' => 'Coin not found.',
            ], 404);
        }

        return (new CoinResource($coin))->response();
    }
}
