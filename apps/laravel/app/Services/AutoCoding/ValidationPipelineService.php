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
     *   groups: array<int, array{group: string, retryable: bool, required: bool, overall_status: string, total_commands: int, failed_commands: int, commands: array<int, array{group: string, command: string, successful: bool, exit_code: int, output: string, error_output: string}>}>,
     *   commands: array<int, array{group: string, command: string, successful: bool, exit_code: int, output: string, error_output: string}>,
     *   summary: string,
     *   can_retry: bool,
     *   completion_ready: bool
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
                'groups' => [],
                'commands' => [],
                'summary' => 'Validation commands were not requested.',
                'can_retry' => false,
                'completion_ready' => true,
            ];
        }

        $results = [];
        $groupResults = [];

        foreach ($this->normalizeConfiguredGroups() as $groupConfig) {
            $groupName = $groupConfig['group'];
            $commands = $groupConfig['commands'];

            $groupCommandResults = [];
            foreach ($commands as $command) {
                $commandResult = $this->commandRunner->run($command, $repositoryPath);
                $groupCommandResults[] = [
                    'group' => $groupName,
                    'command' => $command,
                    ...$commandResult,
                ];
            }

            $groupFailedCount = count(array_filter(
                $groupCommandResults,
                static fn (array $result): bool => $result['successful'] === false
            ));

            $groupResults[] = [
                'group' => $groupName,
                'retryable' => $groupConfig['retryable'],
                'required' => $groupConfig['required'],
                'overall_status' => $groupCommandResults === []
                    ? 'not_configured'
                    : ($groupFailedCount > 0 ? 'failed' : 'passed'),
                'total_commands' => count($groupCommandResults),
                'failed_commands' => $groupFailedCount,
                'commands' => $groupCommandResults,
            ];

            array_push($results, ...$groupCommandResults);
        }

        $summary = $results === []
            ? 'No validation commands are configured for local auto coding.'
            : $this->buildSummary($results);
        $failedCount = count(array_filter($results, static fn (array $result): bool => $result['successful'] === false));
        $canRetry = count(array_filter(
            $groupResults,
            static fn (array $group): bool => $group['overall_status'] === 'failed' && $group['retryable'] === true
        )) > 0;
        $completionReady = count(array_filter(
            $groupResults,
            static fn (array $group): bool => $group['overall_status'] === 'failed' && $group['required'] === true
        )) === 0;

        return [
            'requested' => true,
            'overall_status' => $results === []
                ? 'not_configured'
                : ($failedCount > 0 ? 'failed' : 'passed'),
            'total_commands' => count($results),
            'failed_commands' => $failedCount,
            'groups' => $groupResults,
            'commands' => $results,
            'summary' => $summary,
            'can_retry' => $canRetry,
            'completion_ready' => $completionReady,
        ];
    }

    /**
     * Normalize the configured validation groups into one explicit workflow payload.
     *
     * @return array<int, array{group: string, commands: list<string>, retryable: bool, required: bool}>
     */
    protected function normalizeConfiguredGroups(): array
    {
        $configuredGroups = config('opas.auto_coding.validation_commands', []);
        if (! is_array($configuredGroups)) {
            return [];
        }

        $retryableGroups = config('opas.auto_coding.workflow.retryable_validation_groups', []);
        $retryableGroupNames = is_array($retryableGroups) ? $retryableGroups : [];
        $normalizedGroups = [];

        foreach ($configuredGroups as $group => $definition) {
            if (! is_string($group) || trim($group) === '') {
                continue;
            }

            $commands = [];
            $retryable = in_array($group, $retryableGroupNames, true);
            $required = true;

            if (is_array($definition) && array_is_list($definition)) {
                $commands = $this->normalizeCommands($definition);
            } elseif (is_array($definition)) {
                $commands = $this->normalizeCommands($definition['commands'] ?? []);
                $retryable = is_bool($definition['retryable'] ?? null) ? $definition['retryable'] : $retryable;
                $required = is_bool($definition['required'] ?? null) ? $definition['required'] : true;
            }

            $normalizedGroups[] = [
                'group' => $group,
                'commands' => $commands,
                'retryable' => $retryable,
                'required' => $required,
            ];
        }

        return $normalizedGroups;
    }

    /**
     * Normalize one command list into trimmed non-empty shell commands.
     *
     * @param  mixed  $commands
     * @return list<string>
     */
    protected function normalizeCommands(mixed $commands): array
    {
        if (! is_array($commands)) {
            return [];
        }

        $normalizedCommands = [];

        foreach ($commands as $command) {
            if (! is_string($command) || trim($command) === '') {
                continue;
            }

            $normalizedCommands[] = trim($command);
        }

        return $normalizedCommands;
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
