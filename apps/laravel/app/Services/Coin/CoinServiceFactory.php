<?php

declare(strict_types=1);

namespace App\Services\Coin;

use InvalidArgumentException;

/**
 * Class CoinServiceFactory
 *
 * Dynamically resolve the appropriate coin service by source.
 */
class CoinServiceFactory
{
    /**
     * Resolve service by source name.
     */
    public static function make(string $source): CoinServiceInterface
    {
        return match (strtolower($source)) {
            'binance' => app(BinanceCoinService::class),
            default => throw new InvalidArgumentException("Unsupported source [$source]"),
        };
    }
}
