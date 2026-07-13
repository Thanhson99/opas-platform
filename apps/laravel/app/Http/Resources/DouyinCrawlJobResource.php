<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DouyinCrawlJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DouyinCrawlJobResource extends JsonResource
{
    /**
     * Transform a Douyin crawl job into API fields.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DouyinCrawlJob $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'keyword_id' => $resource->keyword_id,
            'keyword' => $resource->keyword,
            'limit' => $resource->limit,
            'status' => $resource->status,
            'total_found' => $resource->total_found,
            'total_selected' => $resource->total_selected,
            'total_downloaded' => $resource->total_downloaded,
            'error_message' => $resource->error_message,
            'started_at' => $resource->started_at?->toISOString(),
            'finished_at' => $resource->finished_at?->toISOString(),
            'created_at' => $resource->created_at?->toISOString(),
            'videos_count' => $this->whenCounted('videos'),
            'videos' => DouyinVideoResource::collection($this->whenLoaded('videos')),
        ];
    }
}
