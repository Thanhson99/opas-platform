<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->forceSafeTestingDatabase();
        $this->assertSafeTestingDatabase();

        parent::setUp();

        $this->assertSafeTestingDatabaseConfig();
        $this->withoutVite();
    }

    /**
     * Force the test process away from Docker's local Postgres environment.
     *
     * @return void
     */
    private function forceSafeTestingDatabase(): void
    {
        $safeEnvironment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => $this->resolveTestingDatabaseHost(),
            'DB_PORT' => $this->resolveTestingDatabasePort(),
            'DB_DATABASE' => 'laravel_testing',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => 'change-me',
        ];

        foreach ($safeEnvironment as $key => $value) {
            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Block tests from booting against the local development database.
     *
     * @return void
     */
    private function assertSafeTestingDatabase(): void
    {
        if (getenv('APP_ENV') !== 'testing') {
            throw new RuntimeException('Tests must run with APP_ENV=testing.');
        }

        if (getenv('DB_CONNECTION') !== 'pgsql') {
            throw new RuntimeException('Tests must use the dedicated PostgreSQL testing connection.');
        }

        if (! in_array(getenv('DB_HOST'), $this->allowedTestingDatabaseHosts(), true) || getenv('DB_DATABASE') !== 'laravel_testing') {
            throw new RuntimeException('Tests must use the isolated postgres-testing database.');
        }
    }

    /**
     * Verify Laravel booted with the isolated testing database config.
     *
     * @return void
     */
    private function assertSafeTestingDatabaseConfig(): void
    {
        if (config('database.default') !== 'pgsql') {
            throw new RuntimeException('Laravel test config must use pgsql.');
        }

        $testingConnection = config('database.connections.pgsql');

        if (! in_array($testingConnection['host'], $this->allowedTestingDatabaseHosts(), true) || $testingConnection['database'] !== 'laravel_testing') {
            throw new RuntimeException('Laravel test config must use the isolated postgres-testing database.');
        }
    }

    /**
     * Resolve the test database host for Docker Compose and GitHub Actions.
     *
     * @return string
     */
    private function resolveTestingDatabaseHost(): string
    {
        return getenv('CI') === 'true' ? '127.0.0.1' : 'postgres-testing';
    }

    /**
     * Resolve the test database port for Docker Compose and GitHub Actions.
     *
     * @return string
     */
    private function resolveTestingDatabasePort(): string
    {
        return '5432';
    }

    /**
     * Return hosts that are allowed to serve the isolated test database.
     *
     * @return list<string>
     */
    private function allowedTestingDatabaseHosts(): array
    {
        return ['postgres-testing', '127.0.0.1'];
    }
}
