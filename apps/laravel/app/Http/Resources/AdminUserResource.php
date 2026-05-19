<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AdminUserResource extends JsonResource
{
    /**
     * Transform a managed user account into the admin user management contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $verifiedAt = $user->email_verified_at;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'is_current_user' => $request->user() instanceof User && $request->user()->id === $user->id,
            'email_verified' => $user->hasVerifiedEmail(),
            'email_verified_at' => $verifiedAt instanceof DateTimeInterface ? $verifiedAt->format(DateTimeInterface::ATOM) : null,
            'auth_identity_count' => (int) ($user->auth_identities_count ?? $user->authIdentities()->count()),
            'linked_providers' => $this->linkedProviders($user),
            'available_roles' => array_map(
                static fn (UserRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                UserRole::cases(),
            ),
        ];
    }

    /**
     * Transform linked login identities into a compact admin-facing provider list.
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
     * Resolve a stable display name for a linked provider key.
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
     * Resolve the frontend icon name for a linked provider key when available.
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
