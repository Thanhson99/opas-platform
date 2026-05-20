<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthSecurityAuditService
{
    /**
     * Log a structured audit event for successful admin auth provider changes.
     *
     * @param  User  $actor
     * @param  AuthProvider  $before
     * @param  AuthProvider  $after
     * @return void
     */
    public function logProviderSettingsUpdated(User $actor, AuthProvider $before, AuthProvider $after): void
    {
        $changes = $this->buildProviderChangeSet($before, $after);

        if (! $this->hasProviderChanges($changes)) {
            return;
        }

        Log::info('security.auth_provider_settings_updated', [
            'actor_user_id' => $actor->id,
            'provider_key' => $after->key,
            ...$changes,
        ]);
    }

    /**
     * Log whether a verification resend request issued a fresh code.
     *
     * @param  string  $email
     * @param  User|null  $user
     * @param  bool  $codeIssued
     * @return void
     */
    public function logVerificationResendRequested(string $email, ?User $user, bool $codeIssued): void
    {
        Log::info('security.auth_verification_resend_requested', [
            'email_hash' => $this->hashEmail($email),
            'target_user_id' => $user?->id,
            'code_issued' => $codeIssued,
        ]);
    }

    /**
     * Log the outcome of one verification code submission.
     *
     * @param  string  $email
     * @param  string  $status
     * @param  User|null  $user
     * @return void
     */
    public function logVerificationCodeChecked(string $email, string $status, ?User $user): void
    {
        Log::info('security.auth_verification_code_checked', [
            'email_hash' => $this->hashEmail($email),
            'target_user_id' => $user?->id,
            'status' => $status,
        ]);
    }

    /**
     * Build a safe audit diff without logging secret values or provider payload contents.
     *
     * @param  AuthProvider  $before
     * @param  AuthProvider  $after
     * @return array{
     *     changed_attributes:array<string, array{from:mixed,to:mixed}>,
     *     changed_capability_keys:list<string>,
     *     changed_public_config_keys:list<string>,
     *     changed_secret_config_keys:list<string>
     * }
     */
    private function buildProviderChangeSet(AuthProvider $before, AuthProvider $after): array
    {
        $attributeChanges = [];

        foreach (['enabled', 'display_name', 'icon', 'sort_order', 'visibility', 'email_verification_mode'] as $key) {
            if ($before->{$key} === $after->{$key}) {
                continue;
            }

            $attributeChanges[$key] = [
                'from' => $before->{$key},
                'to' => $after->{$key},
            ];
        }

        return [
            'changed_attributes' => $attributeChanges,
            'changed_capability_keys' => $this->resolveChangedKeys(
                $this->normalizeArray($before->capabilities),
                $this->normalizeArray($after->capabilities),
            ),
            'changed_public_config_keys' => $this->resolveChangedKeys(
                $this->normalizeArray($before->public_config),
                $this->normalizeArray($after->public_config),
            ),
            'changed_secret_config_keys' => $this->resolveChangedKeys(
                $this->normalizeArray($before->secret_config),
                $this->normalizeArray($after->secret_config),
            ),
        ];
    }

    /**
     * Determine whether a provider diff contains any auditable changes.
     *
     * @param  array{
     *     changed_attributes:array<string, array{from:mixed,to:mixed}>,
     *     changed_capability_keys:list<string>,
     *     changed_public_config_keys:list<string>,
     *     changed_secret_config_keys:list<string>
     * }  $changes
     * @return bool
     */
    private function hasProviderChanges(array $changes): bool
    {
        return $changes['changed_attributes'] !== []
            || $changes['changed_capability_keys'] !== []
            || $changes['changed_public_config_keys'] !== []
            || $changes['changed_secret_config_keys'] !== [];
    }

    /**
     * Return the list of keys whose normalized values changed between snapshots.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function resolveChangedKeys(array $before, array $after): array
    {
        $keys = array_unique([
            ...array_keys($before),
            ...array_keys($after),
        ]);

        sort($keys);

        $changedKeys = [];

        foreach ($keys as $key) {
            $beforeValue = $this->normalizeValueForComparison($before[$key] ?? null);
            $afterValue = $this->normalizeValueForComparison($after[$key] ?? null);

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changedKeys[] = $key;
        }

        return $changedKeys;
    }

    /**
     * Normalize a provider config array into a predictable string-keyed shape.
     *
     * @param  mixed  $value
     * @return array<string, mixed>
     */
    private function normalizeArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = $item;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Normalize nested values before comparing audit snapshots.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function normalizeValueForComparison(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $normalized = array_map(
                fn (mixed $item): mixed => $this->normalizeValueForComparison($item),
                $value,
            );

            sort($normalized);

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->normalizeValueForComparison($item);
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Hash a user-supplied email so logs stay correlated without leaking raw addresses.
     *
     * @param  string  $email
     * @return string
     */
    private function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }
}
