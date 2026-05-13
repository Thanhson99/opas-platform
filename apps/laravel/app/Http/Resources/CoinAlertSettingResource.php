<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CoinAlertSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoinAlertSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CoinAlertSetting $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'mode' => $resource->mode,
            'threshold_percent' => $resource->threshold_percent,
            'type' => $resource->type,
            'direction' => $resource->direction,
            'is_active' => (bool) $resource->is_active,
        ];
    }
}
