<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TelegramBotConfigAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AdminTelegramBotConfigAuditResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TelegramBotConfigAudit $audit */
        $audit = $this->resource;
        $createdAt = $audit->getAttribute('created_at');

        return [
            'id' => $audit->id,
            'action' => $audit->action,
            'metadata' => $audit->metadata ?? [],
            'actor' => $audit->actor !== null ? [
                'id' => $audit->actor->id,
                'name' => $audit->actor->name,
                'email' => $audit->actor->email,
            ] : null,
            'created_at' => $createdAt instanceof Carbon
                ? $createdAt->toIso8601String()
                : null,
        ];
    }
}
