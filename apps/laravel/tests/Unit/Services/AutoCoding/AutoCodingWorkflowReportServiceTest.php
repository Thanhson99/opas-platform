<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingWorkflowStep;
use App\Enums\AutoCodingWorkflowStepStatus;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Models\AutoCodingTaskRunStep;
use App\Services\AutoCoding\AutoCodingWorkflowReportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class AutoCodingWorkflowReportServiceTest extends TestCase
{
    /**
     * Confirm follow-up and preflight reports are normalized for blocked workflow runs.
     *
     * @return void
     */
    public function test_it_builds_follow_up_and_preflight_reports(): void
    {
        $service = $this->app->make(AutoCodingWorkflowReportService::class);
        $task = new AutoCodingTask([
            'context_payload' => [
                'follow_up_answers' => [[
                    'response' => 'allow',
                    'response_type' => 'confirmation',
                    'response_payload' => [
                        'type' => 'confirmation',
                        'value' => 'allow',
                    ],
                    'submitted_at' => '2026-05-21T10:00:00+07:00',
                ]],
            ],
        ]);
        $task->id = 10;

        $run = new AutoCodingTaskRun([
            'repository_snapshot' => [
                'is_dirty' => true,
                'changed_files' => [
                    ['path' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php'],
                    ['path' => 'docs/notes.md'],
                ],
            ],
        ]);
        $run->id = 22;

        $scopeAnalysis = $service->buildScopeAnalysis(
            $run->repository_snapshot,
            ['apps/laravel/app/Services/AutoCoding'],
            'block',
        );
        $preflight = $service->buildPreflightReport(
            $run->repository_snapshot,
            'block',
            $scopeAnalysis,
        );
        $followUp = $service->buildFollowUpReport($task, $run, [
            'required' => true,
            'reason' => 'dirty_workspace',
            'message' => 'Workspace needs confirmation.',
            'questions' => [[
                'id' => 'workspace_confirmation',
                'prompt' => 'Proceed on dirty workspace?',
                'input_type' => 'confirmation',
                'required' => true,
            ]],
            'input_contract' => [
                'accepted_values' => ['allow', 'continue'],
            ],
        ]);

        self::assertSame('blocked', $preflight['overall_status']);
        self::assertSame('dirty_workspace', $preflight['blocking_reason']);
        self::assertSame('workspace_confirmation', $followUp['question_contracts'][0]['id'] ?? null);
        self::assertSame('Proceed on dirty workspace?', $followUp['questions'][0] ?? null);
        self::assertTrue($followUp['answered']);
        self::assertSame(1, $followUp['answer_count']);
        self::assertSame('allow', $followUp['last_answer']['response'] ?? null);
        self::assertSame('task:10:run:22:blocked', $followUp['input_contract']['resume_token'] ?? null);
    }

    /**
     * Confirm workflow and retry reports summarize persisted step attempts.
     *
     * @return void
     */
    public function test_it_builds_workflow_and_retry_reports(): void
    {
        config()->set('opas.auto_coding.workflow.validation_retry_limit', 3);

        $service = $this->app->make(AutoCodingWorkflowReportService::class);
        $run = new AutoCodingTaskRun;
        $run->setRelation('steps', new EloquentCollection([
            new AutoCodingTaskRunStep([
                'step_key' => AutoCodingWorkflowStep::InspectRepository,
                'sequence' => 1,
                'attempt' => 1,
                'status' => AutoCodingWorkflowStepStatus::Completed,
                'is_retryable' => false,
            ]),
            new AutoCodingTaskRunStep([
                'step_key' => AutoCodingWorkflowStep::RunValidation,
                'sequence' => 2,
                'attempt' => 1,
                'status' => AutoCodingWorkflowStepStatus::Failed,
                'is_retryable' => true,
                'error_message' => 'Lint failed.',
            ]),
            new AutoCodingTaskRunStep([
                'step_key' => AutoCodingWorkflowStep::RunValidation,
                'sequence' => 3,
                'attempt' => 2,
                'status' => AutoCodingWorkflowStepStatus::Blocked,
                'is_retryable' => true,
            ]),
        ]));

        $workflow = $service->buildWorkflowReport($run);
        $retry = $service->buildRetryReport($run);

        self::assertSame('run_validation', $workflow['current_step']);
        self::assertSame('run_validation', $workflow['last_failed_step']);
        self::assertSame('run_validation', $workflow['last_blocked_step']);
        self::assertSame('blocked', $workflow['current_decision_point']['type'] ?? null);
        self::assertTrue($retry['overall_retryable']);
        self::assertSame(2, $retry['validation']['attempts_used']);
        self::assertSame(1, $retry['validation']['remaining_attempts']);
        self::assertSame('run_validation', $retry['retryable_steps'][0]['step'] ?? null);
        self::assertSame(2, $retry['retryable_steps'][0]['attempts_used'] ?? null);
    }
}
