<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
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
            'name' => is_string($resource['name'] ?? null) ? $resource['name'] : '',
            'exchange' => is_string($resource['exchange'] ?? null) ? $resource['exchange'] : '',
            'is_favorite' => (bool) ($resource['is_favorite'] ?? false),
        ];
    }
}
