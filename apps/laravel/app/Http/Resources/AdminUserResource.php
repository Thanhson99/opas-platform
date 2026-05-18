<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'available_roles' => array_map(
                static fn (UserRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                UserRole::cases(),
            ),
        ];
    }
}
