<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auth\Contracts\AuthProviderDriverInterface;
use App\Models\AuthProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthProviderResource extends JsonResource
{
    /**
     * Transform a resolved auth provider into the public login contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{provider:AuthProvider,driver:AuthProviderDriverInterface,active:bool} $resource */
        $resource = $this->resource;
        $provider = $resource['provider'];
        $driver = $resource['driver'];

        return [
            'key' => $provider->key,
            'display_name' => $provider->display_name,
            'type' => $provider->type->value,
            'icon' => $provider->icon,
            'visibility' => $provider->visibility,
            'active' => $resource['active'],
            'capabilities' => $provider->capabilities,
            'email_verification_mode' => $provider->key === 'email'
                ? 'required'
                : $provider->email_verification_mode,
            'metadata' => $driver->publicMetadata($provider),
        ];
    }
}
