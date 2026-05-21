<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Services\AutoCoding\AutoCodingFollowUpContractService;
use Tests\TestCase;

class AutoCodingFollowUpContractServiceTest extends TestCase
{
    /**
     * Confirm provider follow-up input contracts are normalized into a stable payload.
     *
     * @return void
     */
    public function test_it_normalizes_follow_up_contract_definitions(): void
    {
        $service = $this->app->make(AutoCodingFollowUpContractService::class);

        $normalizedContract = $service->normalizeDefinition([
            'type' => ' confirmation ',
            'format' => ' single_text ',
            'expected_input' => ' confirm_to_continue ',
            'accepted_values' => ['allow', '', 123, 'continue'],
            'safe_to_retry' => 1,
            'idempotent_while_blocked' => true,
            'free_text_allowed' => false,
        ]);

        self::assertSame('confirmation', $normalizedContract['type'] ?? null);
        self::assertSame('single_text', $normalizedContract['format'] ?? null);
        self::assertSame('confirm_to_continue', $normalizedContract['expected_input'] ?? null);
        self::assertSame(['allow', 'continue'], $normalizedContract['accepted_values'] ?? null);
        self::assertTrue($normalizedContract['safe_to_retry'] ?? false);
        self::assertTrue($normalizedContract['idempotent_while_blocked'] ?? false);
        self::assertFalse($normalizedContract['free_text_allowed'] ?? true);
    }

    /**
     * Confirm blocked confirmation follow-ups receive a resume-ready client contract.
     *
     * @return void
     */
    public function test_it_builds_one_resolved_follow_up_input_contract(): void
    {
        $service = $this->app->make(AutoCodingFollowUpContractService::class);
        $task = new AutoCodingTask;
        $task->id = 12;
        $run = new AutoCodingTaskRun;
        $run->id = 34;

        $resolvedContract = $service->buildResolvedInputContract($task, $run, [
            'required' => true,
            'reason' => 'dirty_workspace',
            'input_contract' => [
                'accepted_values' => ['allow', 'continue'],
            ],
        ]);

        self::assertSame('confirmation', $resolvedContract['type'] ?? null);
        self::assertSame('confirm_to_continue', $resolvedContract['expected_input'] ?? null);
        self::assertSame(['allow', 'continue'], $resolvedContract['accepted_values'] ?? null);
        self::assertSame('accepted_values_only', $resolvedContract['validation_mode'] ?? null);
        self::assertSame(
            ['task_id' => 12, 'run_id' => 34],
            $resolvedContract['resume_target'] ?? null
        );
        self::assertSame('task:12:run:34:blocked', $resolvedContract['resume_token'] ?? null);
    }
}
