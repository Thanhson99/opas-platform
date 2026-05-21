<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\AutoCodingFollowUpRequestService;
use Tests\TestCase;

class AutoCodingFollowUpRequestServiceTest extends TestCase
{
    /**
     * Confirm provider and preflight follow-up requests are normalized into a stable payload.
     *
     * @return void
     */
    public function test_it_builds_one_normalized_follow_up_request(): void
    {
        $service = $this->app->make(AutoCodingFollowUpRequestService::class);

        $followUp = $service->buildRequest(
            true,
            'Need clarification.',
            [[
                'id' => 'target_file',
                'prompt' => 'Which file should be edited first?',
                'input_type' => 'text',
                'required' => true,
            ]],
            'provider_follow_up',
            [
                'type' => 'free_text',
                'format' => 'single_text',
                'expected_input' => 'provide_clarification',
            ],
        );

        self::assertTrue($followUp['required']);
        self::assertSame('provider_follow_up', $followUp['reason'] ?? null);
        self::assertSame('Which file should be edited first?', $followUp['questions'][0] ?? null);
        self::assertSame('target_file', $followUp['question_contracts'][0]['id'] ?? null);
        self::assertSame('free_text', $followUp['input_contract']['type'] ?? null);
    }

    /**
     * Confirm the dirty-workspace gate emits a confirmation follow-up only when blocking applies.
     *
     * @return void
     */
    public function test_it_builds_one_dirty_workspace_follow_up(): void
    {
        $service = $this->app->make(AutoCodingFollowUpRequestService::class);

        $followUp = $service->buildDirtyWorkspaceFollowUp([
            'is_dirty' => true,
            'changed_files' => [
                ['path' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php'],
                ['path' => 'docs/roadmap/opas-0069-ai-coding-control-system-vi.md'],
            ],
        ], 'block');

        self::assertTrue($followUp['required']);
        self::assertSame('dirty_workspace', $followUp['reason'] ?? null);
        self::assertSame('workspace_confirmation', $followUp['question_contracts'][0]['id'] ?? null);
        self::assertSame('confirmation', $followUp['input_contract']['type'] ?? null);
        self::assertSame(['allow', 'continue', 'proceed', 'yes'], $followUp['input_contract']['accepted_values'] ?? null);
    }

    /**
     * Confirm the scope-mismatch gate only blocks when changed files fall outside the requested scope.
     *
     * @return void
     */
    public function test_it_builds_one_scope_mismatch_follow_up(): void
    {
        $service = $this->app->make(AutoCodingFollowUpRequestService::class);

        $followUp = $service->buildScopeMismatchFollowUp([
            'changed_files' => [
                ['path' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php'],
                ['path' => 'docs/rules/github-rules.md'],
            ],
        ], [
            'apps/laravel/app/Services/AutoCoding',
        ], 'block');

        self::assertTrue($followUp['required']);
        self::assertSame('scope_mismatch', $followUp['reason'] ?? null);
        self::assertSame('scope_confirmation', $followUp['question_contracts'][0]['id'] ?? null);
        self::assertSame('confirmation', $followUp['input_contract']['type'] ?? null);
    }
}
