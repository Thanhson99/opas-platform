<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingMachine;
use App\Support\RepositoryPathMatcher;

class LocalMachineService
{
    /**
     * Resolve and persist the local machine identity for the current repository.
     *
     * @param  string  $repositoryPath
     * @return AutoCodingMachine
     */
    public function resolve(string $repositoryPath): AutoCodingMachine
    {
        return $this->recordHeartbeat([
            'machine_key' => $this->resolveMachineKey($repositoryPath),
            'hostname' => $this->resolveHostname(),
            'operating_system' => PHP_OS_FAMILY,
            'repository_path' => $repositoryPath,
            'metadata' => [
                'php_version' => PHP_VERSION,
                'user' => get_current_user(),
            ],
        ]);
    }

    /**
     * Persist one heartbeat payload reported by a local auto-coding machine.
     *
     * @param  array{
     *   machine_key:string,
     *   hostname:string,
     *   operating_system:string,
     *   availability_status?:string|null,
     *   repository_path?:string|null,
     *   capabilities?:array<string, mixed>|null,
     *   workspace_bindings?:array<int, array<string, mixed>>|null,
     *   max_parallel_tasks?:int|null,
     *   metadata?:array<string, mixed>|null
     * }  $heartbeat
     * @return AutoCodingMachine
     */
    public function recordHeartbeat(array $heartbeat): AutoCodingMachine
    {
        /** @var AutoCodingMachine $machine */
        $machine = AutoCodingMachine::query()->firstOrNew([
            'machine_key' => trim($heartbeat['machine_key']),
        ]);

        $machine->hostname = trim($heartbeat['hostname']);
        $machine->operating_system = trim($heartbeat['operating_system']);
        $machine->availability_status = $this->normalizeAvailabilityStatus($heartbeat['availability_status'] ?? null);
        $machine->repository_path = $this->resolveRepositoryPath($heartbeat);
        $machine->capabilities = $this->normalizeCapabilities($heartbeat['capabilities'] ?? null);
        $machine->workspace_bindings = $this->normalizeWorkspaceBindings(
            $heartbeat['workspace_bindings'] ?? null,
            $machine->repository_path,
        );
        $machine->max_parallel_tasks = $this->normalizeMaxParallelTasks($heartbeat['max_parallel_tasks'] ?? null);
        $machine->metadata = $this->normalizeMetadata($heartbeat);
        $machine->last_seen_at = now();
        $machine->save();

        return $machine;
    }

    /**
     * Resolve the repository path that should be stored for one machine heartbeat.
     *
     * @param  array{repository_path?:string|null}  $heartbeat
     * @return string|null
     */
    protected function resolveRepositoryPath(array $heartbeat): ?string
    {
        $repositoryPath = $heartbeat['repository_path'] ?? null;

        return is_string($repositoryPath) && trim($repositoryPath) !== ''
            ? trim($repositoryPath)
            : null;
    }

    /**
     * Normalize one reported machine availability value.
     *
     * @param  mixed  $availabilityStatus
     * @return string
     */
    protected function normalizeAvailabilityStatus(mixed $availabilityStatus): string
    {
        if (! is_string($availabilityStatus)) {
            return 'idle';
        }

        $normalizedStatus = trim($availabilityStatus);

        return in_array($normalizedStatus, ['idle', 'busy', 'draining', 'offline'], true)
            ? $normalizedStatus
            : 'idle';
    }

    /**
     * Normalize machine capability flags into a string-keyed payload.
     *
     * @param  mixed  $capabilities
     * @return array<string, bool>|null
     */
    protected function normalizeCapabilities(mixed $capabilities): ?array
    {
        if (! is_array($capabilities)) {
            return null;
        }

        $normalizedCapabilities = [];

        foreach ($capabilities as $key => $value) {
            $capabilityName = is_string($key) ? $key : $value;

            if (! is_string($capabilityName) || trim($capabilityName) === '') {
                continue;
            }

            $normalizedCapabilities[strtolower(trim($capabilityName))] = is_bool($value) ? $value : true;
        }

        return $normalizedCapabilities !== [] ? $normalizedCapabilities : null;
    }

    /**
     * Normalize reported workspace bindings and keep the primary repository addressable.
     *
     * @param  mixed  $workspaceBindings
     * @param  string|null  $repositoryPath
     * @return array<int, array<string, mixed>>|null
     */
    protected function normalizeWorkspaceBindings(mixed $workspaceBindings, ?string $repositoryPath): ?array
    {
        $bindings = is_array($workspaceBindings) ? $this->normalizeReportedWorkspaceBindings($workspaceBindings) : [];

        if ($repositoryPath !== null && ! $this->hasRepositoryBinding($bindings, $repositoryPath)) {
            array_unshift($bindings, [
                'repository_path' => $repositoryPath,
                'workspace_path' => $repositoryPath,
                'active_branch' => null,
            ]);
        }

        return $bindings !== [] ? array_values($bindings) : null;
    }

    /**
     * Normalize explicit workspace binding rows from a heartbeat payload.
     *
     * @param  array<mixed>  $workspaceBindings
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeReportedWorkspaceBindings(array $workspaceBindings): array
    {
        $bindings = [];

        foreach ($workspaceBindings as $binding) {
            if (! is_array($binding)) {
                continue;
            }

            $repositoryPath = $this->normalizeBindingString($binding['repository_path'] ?? null);
            if ($repositoryPath === null) {
                continue;
            }

            if ($this->hasRepositoryBinding($bindings, $repositoryPath)) {
                continue;
            }

            $bindings[] = [
                'repository_path' => $repositoryPath,
                'workspace_path' => $this->normalizeBindingString($binding['workspace_path'] ?? null) ?? $repositoryPath,
                'active_branch' => $this->normalizeBindingString($binding['active_branch'] ?? null),
            ];
        }

        return $bindings;
    }

    /**
     * Determine whether a binding list already includes one repository path.
     *
     * @param  array<int, array<string, mixed>>  $bindings
     * @param  string  $repositoryPath
     * @return bool
     */
    protected function hasRepositoryBinding(array $bindings, string $repositoryPath): bool
    {
        foreach ($bindings as $binding) {
            if (
                is_string($binding['repository_path'] ?? null)
                && RepositoryPathMatcher::matches($binding['repository_path'], $repositoryPath)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize one optional workspace binding string.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function normalizeBindingString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalizedValue = trim($value);

        return $normalizedValue !== '' ? $normalizedValue : null;
    }

    /**
     * Normalize one reported machine parallelism limit.
     *
     * @param  mixed  $maxParallelTasks
     * @return int
     */
    protected function normalizeMaxParallelTasks(mixed $maxParallelTasks): int
    {
        return is_numeric($maxParallelTasks) && (int) $maxParallelTasks > 0
            ? min((int) $maxParallelTasks, 10)
            : 1;
    }

    /**
     * Normalize one optional machine metadata payload before persistence.
     *
     * @param  array{metadata?:array<string, mixed>|null}  $heartbeat
     * @return array<string, mixed>|null
     */
    protected function normalizeMetadata(array $heartbeat): ?array
    {
        $metadata = $heartbeat['metadata'] ?? null;

        return is_array($metadata) ? $metadata : null;
    }

    /**
     * Build a stable machine key from config or local host context.
     *
     * @param  string  $repositoryPath
     * @return string
     */
    public function resolveMachineKey(string $repositoryPath): string
    {
        $configuredKey = config('opas.auto_coding.machine_key');
        if (is_string($configuredKey) && $configuredKey !== '') {
            return $configuredKey;
        }

        return sha1($this->resolveHostname().'|'.$repositoryPath);
    }

    /**
     * Resolve the current host name for task reporting.
     *
     * @return string
     */
    protected function resolveHostname(): string
    {
        $hostname = gethostname();

        return is_string($hostname) && $hostname !== '' ? $hostname : php_uname('n');
    }
}
