<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use Illuminate\Support\Facades\Process;

class ProcessCommandRunner implements CommandRunnerInterface
{
    /**
     * Execute one shell command and return the normalized result.
     *
     * @param  string  $command
     * @param  string|null  $workingDirectory
     * @return array{successful: bool, exit_code: int, output: string, error_output: string}
     */
    public function run(string $command, ?string $workingDirectory = null): array
    {
        $result = $workingDirectory !== null
            ? Process::path($workingDirectory)->run($command)
            : Process::run($command);

        return [
            'successful' => $result->successful(),
            'exit_code' => $result->exitCode() ?? 1,
            'output' => trim($result->output()),
            'error_output' => trim($result->errorOutput()),
        ];
    }
}
