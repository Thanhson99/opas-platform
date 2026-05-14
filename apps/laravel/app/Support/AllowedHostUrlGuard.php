<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

class AllowedHostUrlGuard
{
    /**
     * @param  array<int, string>  $allowedHosts
     */
    public static function assert(string $url, array $allowedHosts): void
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! is_string($scheme) || ! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http/https URLs are allowed.');
        }

        if (! is_string($host) || ! in_array(strtolower($host), array_map('strtolower', $allowedHosts), true)) {
            throw new InvalidArgumentException("Outbound host [$host] is not in the allowed list.");
        }
    }
}
