<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stocks;

use App\Http\Controllers\Controller;
use App\Services\Stock\FavoriteStockServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class FavoriteStockController
 *
 * Handles requests related to user's favorite stocks.
 */
class FavoriteStockController extends Controller
{
    public function __construct(
        protected FavoriteStockServiceInterface $favoriteStockService
    ) {}

    /**
     * Add a stock to favorites.
     */
    public function favoritesAdd(Request $request): JsonResponse
    {
        $symbol = $this->normalizeSymbol($request);

        if (! is_string($symbol)) {
            return $symbol;
        }

        $result = $this->favoriteStockService->addSymbol($symbol);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'success' => true,
        ]);
    }

    /**
     * Remove a stock from favorites.
     */
    public function favoritesRemove(Request $request): JsonResponse
    {
        $symbol = $this->normalizeSymbol($request);

        if (! is_string($symbol)) {
            return $symbol;
        }

        $result = $this->favoriteStockService->removeSymbol($symbol);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'success' => true,
        ]);
    }

    /**
     * Toggle favorite status for a stock.
     */
    public function favoritesToggle(Request $request): JsonResponse
    {
        $symbol = $this->normalizeSymbol($request);

        if (! is_string($symbol)) {
            return $symbol;
        }

        $result = $this->favoriteStockService->toggleSymbol($symbol);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'success' => true,
        ]);
    }

    /**
     * Normalize and validate stock symbol from request.
     *
     * @return string|JsonResponse
     */
    private function normalizeSymbol(Request $request): string|JsonResponse
    {
        $symbol = $request->input('symbol');

        if (! is_string($symbol)) {
            return response()->json([
                'message' => 'Invalid symbol format',
                'success' => false,
            ], 400);
        }

        $symbol = strtoupper($symbol);

        // Validate symbol format (1-10 uppercase letters or numbers)
        if (! preg_match('/^[A-Z0-9]{1,10}$/', $symbol)) {
            return response()->json([
                'message' => 'Invalid symbol',
                'success' => false,
            ], 400);
        }

        return $symbol;
    }
}
