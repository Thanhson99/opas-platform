<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertFavoriteStockRequest;
use App\Services\Stock\FavoriteStockServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Handle favorite stock mutations through the shared favorite stock service.
 */
class FavoriteStockApiController extends Controller
{
    /**
     * @return void
     */
    public function __construct(
        private readonly FavoriteStockServiceInterface $favoriteStockService,
    ) {}

    /**
     * Add a validated stock symbol to the favorites list.
     *
     * @param  UpsertFavoriteStockRequest  $request
     * @param  string  $symbol
     * @return JsonResponse
     */
    public function store(UpsertFavoriteStockRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond(
            is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '',
            fn (string $validatedSymbol): array => $this->favoriteStockService->addSymbol($validatedSymbol),
            true,
        );
    }

    /**
     * Remove a validated stock symbol from the favorites list.
     *
     * @param  UpsertFavoriteStockRequest  $request
     * @param  string  $symbol
     * @return JsonResponse
     */
    public function destroy(UpsertFavoriteStockRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond(
            is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '',
            fn (string $validatedSymbol): array => $this->favoriteStockService->removeSymbol($validatedSymbol),
        );
    }

    /**
     * Build the common JSON contract used by favorite stock mutations.
     *
     * @param  string  $symbol
     * @param  callable(string): array<string, string>  $action
     * @param  bool  $createdOnAdded
     * @return JsonResponse
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
