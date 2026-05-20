<?php

declare(strict_types=1);

namespace App\Services\AutoCoding\Contracts;

interface CommandRunnerInterface
{
    /**
     * Execute one shell command and return the normalized result.
     *
     * @param  string  $command
     * @param  string|null  $workingDirectory
     * @return array{successful: bool, exit_code: int, output: string, error_output: string}
     */
    public function run(string $command, ?string $workingDirectory = null): array;
}
