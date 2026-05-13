<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Services\Stock\FavoriteStockServiceInterface;
use App\Services\Stock\StockServiceInterface;
use Illuminate\Http\JsonResponse;

class StockApiController extends Controller
{
    public function __construct(
        private readonly StockServiceInterface $stockService,
        private readonly FavoriteStockServiceInterface $favoriteStockService,
    ) {}

    public function index(): JsonResponse
    {
        $favorites = $this->favoriteStockService->getSymbols();
        $favoriteLookup = array_fill_keys($favorites, true);

        $stocks = array_map(
            fn (array $stock): array => [
                ...$stock,
                'is_favorite' => isset($favoriteLookup[$stock['symbol'] ?? '']),
            ],
            $this->stockService->getListedStocks(),
        );

        return StockResource::collection(collect($stocks))
            ->additional([
                'meta' => [
                    'favorites' => $favorites,
                ],
            ])
            ->response();
    }
}
