<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DouyinVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DouyinVideoResource extends JsonResource
{
    /**
     * Transform a Douyin video into API fields.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DouyinVideo $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'crawl_job_id' => $resource->crawl_job_id,
            'keyword' => $resource->keyword,
            'video_id' => $resource->video_id,
            'source_url' => $resource->source_url,
            'title' => $resource->title,
            'author' => $resource->author,
            'cover_url' => $resource->cover_url,
            'duration' => $resource->duration,
            'like_count' => $resource->like_count,
            'local_path' => $resource->local_path,
            'metadata_path' => $resource->metadata_path,
            'selected' => $resource->selected,
            'status' => $resource->status,
            'error_message' => $resource->error_message,
            'downloaded_at' => $resource->downloaded_at?->toISOString(),
            'processed_at' => $resource->processed_at?->toISOString(),
            'posted_at' => $resource->posted_at?->toISOString(),
            'created_at' => $resource->created_at?->toISOString(),
        ];
    }
}
