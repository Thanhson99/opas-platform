<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingWorkflowStep;
use App\Enums\AutoCodingWorkflowStepStatus;
use App\Models\AutoCodingTaskRun;
use App\Models\AutoCodingTaskRunStep;

class AutoCodingWorkflowTracker
{
    /**
     * Start one persisted workflow step attempt.
     *
     * @param  AutoCodingTaskRun  $run
     * @param  AutoCodingWorkflowStep  $step
     * @param  int  $attempt
     * @param  bool  $isRetryable
     * @param  array<string, mixed>  $inputPayload
     * @return AutoCodingTaskRunStep
     */
    public function startStep(
        AutoCodingTaskRun $run,
        AutoCodingWorkflowStep $step,
        int $attempt,
        bool $isRetryable,
        array $inputPayload = [],
    ): AutoCodingTaskRunStep {
        /** @var AutoCodingTaskRunStep $stepRecord */
        $stepRecord = $run->steps()->create([
            'step_key' => $step,
            'sequence' => $this->resolveNextSequence($run),
            'attempt' => $attempt,
            'status' => AutoCodingWorkflowStepStatus::Running,
            'is_retryable' => $isRetryable,
            'input_payload' => $inputPayload,
            'started_at' => now(),
        ]);

        return $stepRecord;
    }

    /**
     * Mark one workflow step attempt as completed.
     *
     * @param  AutoCodingTaskRunStep  $step
     * @param  array<string, mixed>  $outputPayload
     * @return AutoCodingTaskRunStep
     */
    public function completeStep(AutoCodingTaskRunStep $step, array $outputPayload = []): AutoCodingTaskRunStep
    {
        $step->update([
            'status' => AutoCodingWorkflowStepStatus::Completed,
            'output_payload' => $outputPayload,
            'completed_at' => now(),
        ]);

        /** @var AutoCodingTaskRunStep $freshStep */
        $freshStep = $step->fresh();

        return $freshStep;
    }

    /**
     * Mark one workflow step attempt as failed.
     *
     * @param  AutoCodingTaskRunStep  $step
     * @param  string  $errorMessage
     * @param  array<string, mixed>  $outputPayload
     * @return AutoCodingTaskRunStep
     */
    public function failStep(AutoCodingTaskRunStep $step, string $errorMessage, array $outputPayload = []): AutoCodingTaskRunStep
    {
        $step->update([
            'status' => AutoCodingWorkflowStepStatus::Failed,
            'output_payload' => $outputPayload,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        /** @var AutoCodingTaskRunStep $freshStep */
        $freshStep = $step->fresh();

        return $freshStep;
    }

    /**
     * Mark one workflow step attempt as blocked.
     *
     * @param  AutoCodingTaskRunStep  $step
     * @param  string  $errorMessage
     * @param  array<string, mixed>  $outputPayload
     * @return AutoCodingTaskRunStep
     */
    public function blockStep(AutoCodingTaskRunStep $step, string $errorMessage, array $outputPayload = []): AutoCodingTaskRunStep
    {
        $step->update([
            'status' => AutoCodingWorkflowStepStatus::Blocked,
            'output_payload' => $outputPayload,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);

        /** @var AutoCodingTaskRunStep $freshStep */
        $freshStep = $step->fresh();

        return $freshStep;
    }

    /**
     * Mark one workflow step attempt as skipped.
     *
     * @param  AutoCodingTaskRunStep  $step
     * @param  array<string, mixed>  $outputPayload
     * @return AutoCodingTaskRunStep
     */
    public function skipStep(AutoCodingTaskRunStep $step, array $outputPayload = []): AutoCodingTaskRunStep
    {
        $step->update([
            'status' => AutoCodingWorkflowStepStatus::Skipped,
            'output_payload' => $outputPayload,
            'completed_at' => now(),
        ]);

        /** @var AutoCodingTaskRunStep $freshStep */
        $freshStep = $step->fresh();

        return $freshStep;
    }

    /**
     * Resolve the next workflow-step sequence for one run.
     *
     * @param  AutoCodingTaskRun  $run
     * @return int
     */
    protected function resolveNextSequence(AutoCodingTaskRun $run): int
    {
        $maxSequence = $run->steps()->max('sequence');

        return (is_numeric($maxSequence) ? (int) $maxSequence : 0) + 1;
    }
}
