<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    /**
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
            'role' => $role->value,
            'role_label' => $role->label(),
        ];
    }
}
