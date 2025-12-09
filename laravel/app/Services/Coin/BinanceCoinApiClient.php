<?php

declare(strict_types=1);

namespace App\Services\Coin;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Class BinanceCoinApiClient
 *
 * Handles requests to Binance public API.
 */
class BinanceCoinApiClient implements CoinApiClientInterface
{
    protected string $baseUrl;

    public function __construct()
    {
        $baseUrl = config('services.binance.base_url', 'https://api.binance.com');

        $this->baseUrl = is_string($baseUrl) ? $baseUrl : 'https://api.binance.com';
    }

    /**
     * Get market data for top coins.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchTopCoins(): array
    {
        /** @var Response $response */
        $response = Http::get($this->baseUrl.'/api/v3/ticker/24hr');

        if ($response->successful()) {
            /** @var array<int, array<string, mixed>> $data */
            $data = $response->json();

            /** @var array<int, array<string, mixed>> $result */
            $result = collect($data)
                ->sortByDesc('quoteVolume')
                ->take(10)
                ->values()
                ->toArray();

            return $result;
        }

        return [];
    }

    /**
     * Get detailed info for a specific coin.
     *
     * @return array<string, mixed>|null
     */
    public function fetchCoinDetail(string $coinId): ?array
    {
        /** @var Response $response */
        $response = Http::get($this->baseUrl.'/api/v3/ticker/24hr', [
            'symbol' => strtoupper($coinId),
        ]);

        if ($response->successful()) {
            /** @var array<string, mixed> $result */
            $result = $response->json();

            return $result;
        }

        return null;
    }
}
