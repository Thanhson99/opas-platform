<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertFavoriteCoinRequest;
use App\Services\Coin\FavoriteCoinServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Handle favorite coin mutations through the shared favorite coin service.
 */
class FavoriteCoinApiController extends Controller
{
    /**
     * @return void
     */
    public function __construct(
        private readonly FavoriteCoinServiceInterface $favoriteCoinService,
    ) {}

    /**
     * Add a validated coin symbol to the favorites list.
     *
     * @param  UpsertFavoriteCoinRequest  $request
     * @param  string  $symbol
     * @return JsonResponse
     */
    public function store(UpsertFavoriteCoinRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond(
            is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '',
            fn (string $validatedSymbol): array => $this->favoriteCoinService->addSymbol($validatedSymbol),
            true,
        );
    }

    /**
     * Remove a validated coin symbol from the favorites list.
     *
     * @param  UpsertFavoriteCoinRequest  $request
     * @param  string  $symbol
     * @return JsonResponse
     */
    public function destroy(UpsertFavoriteCoinRequest $request, string $symbol): JsonResponse
    {
        $validated = $request->validated();

        return $this->respond(
            is_string($validated['symbol'] ?? null) ? $validated['symbol'] : '',
            fn (string $validatedSymbol): array => $this->favoriteCoinService->removeSymbol($validatedSymbol),
        );
    }

    /**
     * Build the common JSON contract used by favorite coin mutations.
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
