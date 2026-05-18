<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    /**
     * Transform the authenticated user into the SPA auth session payload.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $resource */
        $resource = $this->resource;
        $role = $resource->role;

        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'email' => $resource->email,
            'email_verified' => $resource->hasVerifiedEmail(),
            'email_verified_at' => $resource->email_verified_at?->toIso8601String(),
            'role' => $role->value,
            'role_label' => $role->label(),
        ];
    }
}
