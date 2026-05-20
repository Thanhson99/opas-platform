<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingMachine;

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
     *   repository_path?:string|null,
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
        $machine->repository_path = $this->resolveRepositoryPath($heartbeat);
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
