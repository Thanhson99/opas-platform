<?php

namespace App\Services\Python;

/**
 * Interface for Python service calls.
 */
interface PythonServiceInterface
{
    /**
     * Execute a Python script with given arguments.
     *
     * @param  array<string, string|int|float>  $args  Arguments passed to Python script
     * @return string|null Script output or null on failure
     */
    public function runScript(string $scriptName, array $args = []): ?string;
}
