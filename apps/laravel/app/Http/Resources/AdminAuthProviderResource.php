<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auth\Contracts\AuthProviderDriverInterface;
use App\Models\AuthProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAuthProviderResource extends JsonResource
{
    /**
     * Transform a resolved provider payload into the admin-facing configuration contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{provider:AuthProvider,driver:AuthProviderDriverInterface,active:bool,ready:bool,issues:list<string>} $resource */
        $resource = $this->resource;
        $provider = $resource['provider'];
        $driver = $resource['driver'];
        /** @var array<string, mixed> $secretConfig */
        $secretConfig = $provider->secret_config ?? [];
        $secretStatus = [];

        foreach ($driver->requiredSecretConfigKeys() as $key) {
            $secretStatus[$key] = array_key_exists($key, $secretConfig);
        }

        return [
            'key' => $provider->key,
            'display_name' => $provider->display_name,
            'type' => $provider->type->value,
            'enabled' => $provider->enabled,
            'ready' => $resource['ready'],
            'active' => $resource['active'],
            'issues' => $resource['issues'],
            'icon' => $provider->icon,
            'sort_order' => $provider->sort_order,
            'visibility' => $provider->visibility,
            'capabilities' => $provider->capabilities,
            'public_config' => $provider->public_config,
            'secret_status' => $secretStatus,
            'required_secret_keys' => $driver->requiredSecretConfigKeys(),
            'required_public_keys' => $driver->requiredPublicConfigKeys(),
            'email_verification_mode' => $provider->email_verification_mode,
            'metadata' => $driver->publicMetadata($provider),
        ];
    }
}
