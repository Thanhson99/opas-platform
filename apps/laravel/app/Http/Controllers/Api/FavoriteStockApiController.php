<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertFavoriteStockRequest;
use App\Services\Stock\FavoriteStockServiceInterface;
use Illuminate\Http\JsonResponse;

class FavoriteStockApiController extends Controller
{
    public function __construct(
        private readonly FavoriteStockServiceInterface $favoriteStockService,
    ) {}

    public function store(UpsertFavoriteStockRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond(
            is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '',
            fn (string $validatedSymbol): array => $this->favoriteStockService->addSymbol($validatedSymbol),
            true
        );
    }

    public function destroy(UpsertFavoriteStockRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond(
            is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '',
            fn (string $validatedSymbol): array => $this->favoriteStockService->removeSymbol($validatedSymbol)
        );
    }

    /**
     * @param  callable(string): array<string, string>  $action
     */
    private function respond(string $symbol, callable $action, bool $createdOnAdded = false): JsonResponse
    {
        /** @var array<string, string> $result */
        $result = $action($symbol);

        return response()->json([
            'data' => [
                'symbol' => $symbol,
                'status' => $result['status'],
            ],
            'message' => $result['message'],
            'success' => true,
        ], $createdOnAdded && $result['status'] === 'added' ? 201 : 200);
    }
}
