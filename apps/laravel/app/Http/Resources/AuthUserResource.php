<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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
            'current_sign_in_provider' => $this->currentSignInProvider($request),
            'linked_providers' => $this->linkedProviders($resource),
        ];
    }

    /**
     * Return the provider used for the current session when the backend knows it.
     *
     * @param  Request  $request
     * @return array{key:string,display_name:string,icon:string|null}|null
     */
    private function currentSignInProvider(Request $request): ?array
    {
        $providerKey = $request->session()->get(AuthSessionService::LOGIN_PROVIDER_SESSION_KEY);

        if (! is_string($providerKey) || trim($providerKey) === '') {
            return null;
        }

        return [
            'key' => $providerKey,
            'display_name' => $this->providerDisplayName($providerKey),
            'icon' => $this->providerIcon($providerKey),
        ];
    }

    /**
     * Transform linked OAuth identities into a compact frontend-safe provider list.
     *
     * @param  User  $user
     * @return list<array{key:string,display_name:string,icon:string|null}>
     */
    private function linkedProviders(User $user): array
    {
        $identities = $user->relationLoaded('authIdentities')
            ? $user->authIdentities
            : $user->authIdentities()->get(['provider_key']);

        $providers = [];

        foreach ($identities as $identity) {
            $key = $identity->provider_key;

            if (trim($key) === '') {
                continue;
            }

            $providers[$key] = [
                'key' => $key,
                'display_name' => $this->providerDisplayName($key),
                'icon' => $this->providerIcon($key),
            ];
        }

        return array_values($providers);
    }

    /**
     * Resolve a stable display label for a linked login provider key.
     *
     * @param  string  $key
     * @return string
     */
    private function providerDisplayName(string $key): string
    {
        return match ($key) {
            'google' => 'Google',
            'github' => 'GitHub',
            'facebook' => 'Facebook',
            'email' => 'Email',
            default => Str::headline(str_replace(['-', '_'], ' ', $key)),
        };
    }

    /**
     * Resolve the SPA icon name for a linked login provider key when one exists.
     *
     * @param  string  $key
     * @return string|null
     */
    private function providerIcon(string $key): ?string
    {
        return match ($key) {
            'google', 'github', 'facebook', 'email' => $key,
            default => null,
        };
    }
}
