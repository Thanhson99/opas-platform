<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AutoCodingRunArtifact;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoCodingRunArtifactResource extends JsonResource
{
    /**
     * Transform one local auto-coding run artifact into the admin-facing API contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AutoCodingRunArtifact $artifact */
        $artifact = $this->resource;

        return [
            'id' => $artifact->id,
            'task_run_id' => $artifact->task_run_id,
            'type' => $artifact->type,
            'label' => $artifact->label,
            'payload' => $artifact->payload,
            'created_at' => $artifact->created_at instanceof DateTimeInterface
                ? $artifact->created_at->format(DateTimeInterface::ATOM)
                : null,
        ];
    }
}
