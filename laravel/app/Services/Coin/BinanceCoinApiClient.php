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
    /**
     * Large-cap spot pairs should always be considered first when building
     * the top-coin list, even if smaller assets briefly lead by raw volume.
     *
     * @var array<int, string>
     */
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

    /**
     * Create a new Binance coin API client instance.
     *
     * @return void
     */
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
            $data = $response->json();
            if (! is_array($data)) {
                return [];
            }

            /** @var array<int, array<string, mixed>> $tickers */
            $tickers = array_values(array_filter($data, 'is_array'));

            /** @var array<int, array<string, mixed>> $rankedByVolume */
            $rankedByVolume = collect($tickers)
                ->filter(fn (array $ticker): bool => $this->isValidUsdtSpotTicker($ticker))
                ->sortByDesc(fn (array $ticker): float => $this->toFloat($ticker['quoteVolume'] ?? null))
                ->values()
                ->toArray();

            if ($rankedByVolume === []) {
                return [];
            }

            /** @var array<string, array<string, mixed>> $tickerBySymbol */
            $tickerBySymbol = collect($rankedByVolume)
                ->keyBy(fn (array $ticker): string => $this->toString($ticker['symbol'] ?? null))
                ->toArray();

            /** @var array<int, array<string, mixed>> $prioritized */
            $prioritized = collect(self::LARGE_CAP_USDT_PAIRS)
                ->map(fn (string $symbol): ?array => $tickerBySymbol[$symbol] ?? null)
                ->filter()
                ->values()
                ->toArray();

            /** @var array<int, array<string, mixed>> $remaining */
            $remaining = collect($rankedByVolume)
                ->reject(fn (array $ticker): bool => in_array($this->toString($ticker['symbol'] ?? null), self::LARGE_CAP_USDT_PAIRS, true))
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
     * @param  string  $coinId  Coin symbol or identifier.
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
        $symbol = strtoupper($this->toString($ticker['symbol'] ?? null));

        if ($symbol === '' || ! str_ends_with($symbol, 'USDT')) {
            return false;
        }

        $baseAsset = substr($symbol, 0, -4);
        if ($baseAsset === '' || $this->isLeveragedToken($baseAsset) || $this->isStablecoin($baseAsset)) {
            return false;
        }

        return true;
    }

    /**
     * Exclude Binance leveraged token suffixes from the spot market ranking.
     *
     * @param  string  $baseAsset  Base asset symbol without the quote currency.
     */
    private function isLeveragedToken(string $baseAsset): bool
    {
        foreach (['UP', 'DOWN', 'BULL', 'BEAR'] as $suffix) {
            if (str_ends_with($baseAsset, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filter out quote pairs backed by stablecoins rather than tradable assets.
     *
     * @param  string  $asset  Base asset symbol.
     */
    private function isStablecoin(string $asset): bool
    {
        return in_array($asset, ['USDT', 'USDC', 'FDUSD', 'BUSD', 'TUSD', 'DAI', 'USDP'], true);
    }

    /**
     * Safely normalize loosely typed numeric payload values from the Binance API.
     *
     * @param  mixed  $value  Raw API value.
     */
    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Safely normalize scalar API values before they are used as symbols or keys.
     *
     * @param  mixed  $value  Raw API value.
     */
    private function toString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
