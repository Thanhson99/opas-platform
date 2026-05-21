<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingFollowUpWorkflowService;
use Tests\TestCase;

class AutoCodingFollowUpWorkflowServiceTest extends TestCase
{
    /**
     * Confirm provider payloads are normalized into a stable follow-up request contract.
     *
     * @return void
     */
    public function test_it_extracts_one_follow_up_request_from_provider_result(): void
    {
        $service = $this->app->make(AutoCodingFollowUpWorkflowService::class);

        $followUp = $service->extractFollowUpRequest([
            'status' => 'needs_follow_up',
            'message' => 'Need clarification.',
            'questions' => [
                'Which module should this task focus on?',
            ],
            'input_contract' => [
                'type' => 'free_text',
                'expected_input' => 'provide_clarification',
            ],
        ]);

        self::assertTrue($followUp['required']);
        self::assertSame('Need clarification.', $followUp['message'] ?? null);
        self::assertSame('Which module should this task focus on?', $followUp['questions'][0] ?? null);
        self::assertSame('free_text', $followUp['input_contract']['type'] ?? null);
    }

    /**
     * Confirm dirty workspace follow-up answers can relax the workspace policy on resume.
     *
     * @return void
     */
    public function test_it_resolves_dirty_workspace_policy_from_resume(): void
    {
        $service = $this->app->make(AutoCodingFollowUpWorkflowService::class);
        $task = new AutoCodingTask([
            'context_payload' => [
                'dirty_workspace_policy' => 'block',
            ],
            'latest_report' => [
                'follow_up' => [
                    'reason' => 'dirty_workspace',
                ],
            ],
        ]);

        $policy = $service->resolveDirtyWorkspacePolicyFromResume($task, 'allow');

        self::assertSame('allow', $policy);
    }

    /**
     * Confirm scope mismatch follow-up answers can relax the scope policy on resume.
     *
     * @return void
     */
    public function test_it_resolves_scope_policy_from_resume(): void
    {
        $service = $this->app->make(AutoCodingFollowUpWorkflowService::class);
        $task = new AutoCodingTask([
            'context_payload' => [
                'scope_policy' => 'block',
            ],
            'latest_report' => [
                'follow_up' => [
                    'reason' => 'scope_mismatch',
                ],
            ],
        ]);

        $policy = $service->resolveScopePolicyFromResume($task, 'continue');

        self::assertSame('allow', $policy);
    }
}
