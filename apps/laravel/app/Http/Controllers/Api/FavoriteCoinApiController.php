<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertFavoriteCoinRequest;
use App\Services\Coin\FavoriteCoinServiceInterface;
use Illuminate\Http\JsonResponse;

class FavoriteCoinApiController extends Controller
{
    public function __construct(
        private readonly FavoriteCoinServiceInterface $favoriteCoinService,
    ) {}

    public function store(UpsertFavoriteCoinRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();
        $validatedSymbol = is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '';
        $result = $this->favoriteCoinService->addSymbol($validatedSymbol);

        return response()->json([
            'data' => [
                'symbol' => $validatedSymbol,
                'status' => $result['status'],
            ],
            'message' => $result['message'],
            'success' => true,
        ], $result['status'] === 'added' ? 201 : 200);
    }

    public function destroy(UpsertFavoriteCoinRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();
        $validatedSymbol = is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '';
        $result = $this->favoriteCoinService->removeSymbol($validatedSymbol);

        return response()->json([
            'data' => [
                'symbol' => $validatedSymbol,
                'status' => $result['status'],
            ],
            'message' => $result['message'],
            'success' => true,
        ]);
    }
}
