<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FeedKeyword;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedKeywordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FeedKeyword $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'keyword' => $resource->keyword,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
