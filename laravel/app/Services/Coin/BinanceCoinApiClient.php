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
    /** @var array<int, string> */
    private const LARGE_CAP_USDT_PAIRS = [
        'BTCUSDT',
        'ETHUSDT',
        'BNBUSDT',
        'SOLUSDT',
        'XRPUSDT',
        'ADAUSDT',
        'DOGEUSDT',
        'TRXUSDT',
        'LINKUSDT',
        'AVAXUSDT',
    ];

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

            /** @var array<int, array<string, mixed>> $rankedByVolume */
            $rankedByVolume = collect($data)
                ->filter(fn (array $ticker): bool => $this->isValidUsdtSpotTicker($ticker))
                ->sortByDesc(fn (array $ticker): float => (float) ($ticker['quoteVolume'] ?? 0))
                ->values()
                ->toArray();

            if ($rankedByVolume === []) {
                return [];
            }

            /** @var array<string, array<string, mixed>> $tickerBySymbol */
            $tickerBySymbol = collect($rankedByVolume)
                ->keyBy(fn (array $ticker): string => (string) $ticker['symbol'])
                ->toArray();

            /** @var array<int, array<string, mixed>> $prioritized */
            $prioritized = collect(self::LARGE_CAP_USDT_PAIRS)
                ->map(fn (string $symbol): ?array => $tickerBySymbol[$symbol] ?? null)
                ->filter()
                ->values()
                ->toArray();

            /** @var array<int, array<string, mixed>> $remaining */
            $remaining = collect($rankedByVolume)
                ->reject(fn (array $ticker): bool => in_array((string) $ticker['symbol'], self::LARGE_CAP_USDT_PAIRS, true))
                ->values()
                ->toArray();

            /** @var array<int, array<string, mixed>> $result */
            $result = array_slice(array_merge($prioritized, $remaining), 0, 10);

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

    /**
     * Keep only standard USDT spot symbols and skip leveraged/stablecoin pairs.
     *
     * @param  array<string, mixed>  $ticker
     */
    private function isValidUsdtSpotTicker(array $ticker): bool
    {
        $symbol = strtoupper((string) ($ticker['symbol'] ?? ''));

        if ($symbol === '' || ! str_ends_with($symbol, 'USDT')) {
            return false;
        }

        $baseAsset = substr($symbol, 0, -4);
        if ($baseAsset === '' || $this->isLeveragedToken($baseAsset) || $this->isStablecoin($baseAsset)) {
            return false;
        }

        return true;
    }

    private function isLeveragedToken(string $baseAsset): bool
    {
        foreach (['UP', 'DOWN', 'BULL', 'BEAR'] as $suffix) {
            if (str_ends_with($baseAsset, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isStablecoin(string $asset): bool
    {
        return in_array($asset, ['USDT', 'USDC', 'FDUSD', 'BUSD', 'TUSD', 'DAI', 'USDP'], true);
    }
}
