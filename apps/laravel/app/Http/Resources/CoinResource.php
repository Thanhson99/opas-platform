<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $resource */
        $resource = is_array($this->resource) ? $this->resource : [];

        return [
            'symbol' => is_string($resource['symbol'] ?? null) ? $resource['symbol'] : '',
            'lastPrice' => $resource['lastPrice'] ?? null,
            'highPrice' => $resource['highPrice'] ?? null,
            'lowPrice' => $resource['lowPrice'] ?? null,
            'openPrice' => $resource['openPrice'] ?? null,
            'quoteVolume' => $resource['quoteVolume'] ?? null,
            'priceChangePercent' => $resource['priceChangePercent'] ?? null,
            'is_favorite' => (bool) ($resource['is_favorite'] ?? false),
        ];
    }
}
