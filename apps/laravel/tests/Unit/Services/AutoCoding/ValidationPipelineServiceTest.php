<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\Contracts\CommandRunnerInterface;
use App\Services\AutoCoding\ValidationPipelineService;
use Tests\TestCase;

class ValidationPipelineServiceTest extends TestCase
{
    /**
     * Confirm the validation pipeline summarizes successful command results.
     *
     * @return void
     */
    public function test_it_reports_passed_validation_commands(): void
    {
        config()->set('opas.auto_coding.validation_commands', [
            'lint' => ['lint-command'],
            'tests' => ['test-command'],
        ]);

        $service = new ValidationPipelineService(new ValidationFakeCommandRunner([
            'lint-command' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'lint ok',
                'error_output' => '',
            ],
            'test-command' => [
                'successful' => true,
                'exit_code' => 0,
                'output' => 'tests ok',
                'error_output' => '',
            ],
        ]));

        $result = $service->run('/tmp/example-repo', true);

        self::assertTrue($result['requested']);
        self::assertSame('passed', $result['overall_status']);
        self::assertSame(2, $result['total_commands']);
        self::assertSame(0, $result['failed_commands']);
        self::assertSame('2 validation command(s) passed.', $result['summary']);
    }

    /**
     * Confirm the validation pipeline reports failures and failed command counts.
     *
     * @return void
     */
    public function test_it_reports_failed_validation_commands(): void
    {
        config()->set('opas.auto_coding.validation_commands', [
            'static_analysis' => ['phpstan-command'],
        ]);

        $service = new ValidationPipelineService(new ValidationFakeCommandRunner([
            'phpstan-command' => [
                'successful' => false,
                'exit_code' => 1,
                'output' => '',
                'error_output' => 'phpstan failed',
            ],
        ]));

        $result = $service->run('/tmp/example-repo', true);

        self::assertSame('failed', $result['overall_status']);
        self::assertSame(1, $result['total_commands']);
        self::assertSame(1, $result['failed_commands']);
        self::assertSame('1 validation command(s) failed.', $result['summary']);
    }
}

final class ValidationFakeCommandRunner implements CommandRunnerInterface
{
    /**
     * @param  array<string, array{successful: bool, exit_code: int, output: string, error_output: string}>  $results
     */
    public function __construct(
        private readonly array $results,
    ) {}

    /**
     * Execute one shell command and return the normalized result.
     *
     * @param  string  $command
     * @param  string|null  $workingDirectory
     * @return array{successful: bool, exit_code: int, output: string, error_output: string}
     */
    public function run(string $command, ?string $workingDirectory = null): array
    {
        return $this->results[$command];
    }
}
