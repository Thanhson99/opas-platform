<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $resource */
        $resource = is_array($this->resource) ? $this->resource : [];

        return [
            'keyword' => is_string($resource['keyword'] ?? null) ? $resource['keyword'] : '',
            'links' => array_values(array_filter(
                is_array($resource['links'] ?? null) ? $resource['links'] : [],
                static fn (mixed $link): bool => is_string($link) && $link !== '',
            )),
        ];
    }
}
