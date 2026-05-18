<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Enums\AuthProviderType;
use App\Models\AuthProvider;

class EmailAuthProviderDriver extends AbstractAuthProviderDriver
{
    /**
     * Return the stable key used by the built-in email/password provider.
     */
    public function key(): string
    {
        return 'email';
    }

    /**
     * Mark the built-in provider as a password-based auth flow.
     */
    public function type(): AuthProviderType
    {
        return AuthProviderType::Password;
    }

    /**
     * Return the default admin/login label for the email provider.
     */
    public function defaultDisplayName(): string
    {
        return 'Email and Password';
    }

    /**
     * Return the SPA icon name used for local email/password login.
     */
    public function defaultIcon(): ?string
    {
        return 'mail';
    }

    /**
     * Return the capability flags for the built-in password flow.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return [
            'login' => true,
            'register' => true,
            'link_account' => false,
            'requires_redirect' => false,
            'supports_email_verification' => true,
            'supports_password' => true,
        ];
    }

    /**
     * Email/password does not require extra public configuration to become ready.
     *
     * @return list<string>
     */
    public function requiredPublicConfigKeys(): array
    {
        return [];
    }

    /**
     * Email/password does not require encrypted third-party secrets to become ready.
     *
     * @return list<string>
     */
    public function requiredSecretConfigKeys(): array
    {
        return [];
    }

    /**
     * Expose frontend-safe metadata for the built-in password flow.
     *
     * @return array<string, mixed>
     */
    public function publicMetadata(AuthProvider $provider): array
    {
        $config = $provider->public_config;

        return [
            'button_text' => $config['button_text'] ?? 'Continue with email',
            'password_reset_enabled' => (bool) ($config['password_reset_enabled'] ?? false),
        ];
    }
}
