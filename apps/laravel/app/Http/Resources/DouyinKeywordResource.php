<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DouyinKeyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DouyinKeywordResource extends JsonResource
{
    /**
     * Transform a Douyin keyword into API fields.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DouyinKeyword $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'category' => $resource->category,
            'source' => $resource->source,
            'priority' => $resource->priority,
            'is_active' => $resource->is_active,
            'last_crawled_at' => $resource->last_crawled_at?->toISOString(),
        ];
    }
}
