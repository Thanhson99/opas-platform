<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;

class ValidationPipelineService
{
    public function __construct(
        private readonly CommandRunnerInterface $commandRunner,
    ) {}

    /**
     * Run the configured local validation commands for the repository.
     *
     * @param  string  $repositoryPath
     * @param  bool  $shouldRunValidation
     * @return array{
     *   requested: bool,
     *   overall_status: string,
     *   total_commands: int,
     *   failed_commands: int,
     *   commands: array<int, array{group: string, command: string, successful: bool, exit_code: int, output: string, error_output: string}>,
     *   summary: string
     * }
     */
    public function run(string $repositoryPath, bool $shouldRunValidation): array
    {
        if (! $shouldRunValidation) {
            return [
                'requested' => false,
                'overall_status' => 'skipped',
                'total_commands' => 0,
                'failed_commands' => 0,
                'commands' => [],
                'summary' => 'Validation commands were not requested.',
            ];
        }

        $configuredGroups = config('opas.auto_coding.validation_commands', []);
        if (! is_array($configuredGroups)) {
            $configuredGroups = [];
        }

        $results = [];

        foreach ($configuredGroups as $group => $commands) {
            if (! is_array($commands)) {
                continue;
            }

            foreach ($commands as $command) {
                if (! is_string($command) || trim($command) === '') {
                    continue;
                }

                $commandResult = $this->commandRunner->run($command, $repositoryPath);
                $results[] = [
                    'group' => (string) $group,
                    'command' => $command,
                    ...$commandResult,
                ];
            }
        }

        $summary = $results === []
            ? 'No validation commands are configured for local auto coding.'
            : $this->buildSummary($results);
        $failedCount = count(array_filter($results, static fn (array $result): bool => $result['successful'] === false));

        return [
            'requested' => true,
            'overall_status' => $results === []
                ? 'not_configured'
                : ($failedCount > 0 ? 'failed' : 'passed'),
            'total_commands' => count($results),
            'failed_commands' => $failedCount,
            'commands' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * Build a short validation summary for reporting.
     *
     * @param  array<int, array{group: string, command: string, successful: bool, exit_code: int, output: string, error_output: string}>  $results
     * @return string
     */
    protected function buildSummary(array $results): string
    {
        $failedCount = count(array_filter($results, static fn (array $result): bool => $result['successful'] === false));
        if ($failedCount > 0) {
            return sprintf('%d validation command(s) failed.', $failedCount);
        }

        return sprintf('%d validation command(s) passed.', count($results));
    }
}
