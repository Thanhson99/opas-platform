<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coins;

use App\Http\Controllers\Controller;
use App\Services\Coin\FavoriteCoinServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class FavoriteCoinController
 *
 * Handles requests related to user's favorite coins.
 */
class FavoriteCoinController extends Controller
{
    public function __construct(
        protected FavoriteCoinServiceInterface $favoriteCoinService
    ) {}

    /**
     * Toggle favorite status for a coin.
     *
     * @param  Request  $request  Incoming HTTP request.
     */
    public function favoritesToggle(Request $request): JsonResponse
    {
        $symbol = $request->input('symbol');

        if (! is_string($symbol)) {
            return response()->json([
                'message' => 'Invalid symbol format',
                'success' => false,
            ], 400);
        }

        $symbol = strtoupper($symbol);

        // Validate symbol format (3-10 uppercase letters or numbers)
        if (! preg_match('/^[A-Z0-9]{3,10}$/', $symbol)) {
            return response()->json([
                'message' => 'Invalid symbol',
                'success' => false,
            ], 400);
        }

        $result = $this->favoriteCoinService->toggleSymbol($symbol);

        return response()->json([
            'message' => $result['message'],
            'status' => $result['status'],
            'success' => true,
        ]);
    }
}
