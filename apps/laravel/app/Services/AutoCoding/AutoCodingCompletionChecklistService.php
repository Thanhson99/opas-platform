<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;

class AutoCodingCompletionChecklistService
{
    /**
     * Build one completion checklist for the current task execution state.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, mixed>  $validationResults
     * @param  array<string, mixed>  $report
     * @return array{is_complete: bool, items: array<int, array{key: string, status: string, message: string}>}
     */
    public function build(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        array $providerResult,
        array $validationResults,
        array $report,
    ): array {
        $items = [
            $this->buildProviderChecklistItem($providerResult),
            $this->buildValidationChecklistItem($validationResults),
            $this->buildScopeChecklistItem($report),
            $this->buildFailureChecklistItem($report),
            $this->buildRecommendationChecklistItem($report),
            $this->buildStateChecklistItem($task, $run),
            $this->buildReportChecklistItem($report),
        ];

        $isComplete = count(array_filter(
            $items,
            static fn (array $item): bool => $item['status'] === 'passed' || $item['status'] === 'skipped'
        )) === count($items);

        return [
            'is_complete' => $isComplete,
            'items' => $items,
        ];
    }

    /**
     * Build the provider-state checklist item.
     *
     * @param  array<string, mixed>  $providerResult
     * @return array{key: string, status: string, message: string}
     */
    protected function buildProviderChecklistItem(array $providerResult): array
    {
        $status = is_string($providerResult['status'] ?? null) ? $providerResult['status'] : 'unknown';

        return match ($status) {
            'completed' => [
                'key' => 'provider',
                'status' => 'passed',
                'message' => 'Provider finished planning successfully.',
            ],
            'skipped' => [
                'key' => 'provider',
                'status' => 'skipped',
                'message' => 'Provider step was intentionally skipped.',
            ],
            'needs_follow_up', 'blocked' => [
                'key' => 'provider',
                'status' => 'blocked',
                'message' => 'Provider requires follow-up input before execution can complete.',
            ],
            default => [
                'key' => 'provider',
                'status' => 'failed',
                'message' => 'Provider did not complete successfully.',
            ],
        };
    }

    /**
     * Build the validation-state checklist item.
     *
     * @param  array<string, mixed>  $validationResults
     * @return array{key: string, status: string, message: string}
     */
    protected function buildValidationChecklistItem(array $validationResults): array
    {
        $status = is_string($validationResults['overall_status'] ?? null)
            ? $validationResults['overall_status']
            : 'unknown';

        return match ($status) {
            'passed' => [
                'key' => 'validation',
                'status' => 'passed',
                'message' => 'Validation commands passed.',
            ],
            'skipped', 'not_configured' => [
                'key' => 'validation',
                'status' => 'skipped',
                'message' => 'Validation did not block completion.',
            ],
            default => [
                'key' => 'validation',
                'status' => 'failed',
                'message' => 'Validation still has failing commands.',
            ],
        };
    }

    /**
     * Build the changed-file scope checklist item.
     *
     * @param  array<string, mixed>  $report
     * @return array{key: string, status: string, message: string}
     */
    protected function buildScopeChecklistItem(array $report): array
    {
        $scope = is_array($report['scope'] ?? null) ? $report['scope'] : null;
        if ($scope === null) {
            return [
                'key' => 'scope',
                'status' => 'skipped',
                'message' => 'No changed-file scope constraints were requested.',
            ];
        }

        $policy = is_string($scope['policy'] ?? null) ? $scope['policy'] : 'warn';
        $inScope = ($scope['in_scope'] ?? null) === true;
        $hasScope = is_array($scope['requested_paths'] ?? null) && $scope['requested_paths'] !== [];

        if (! $hasScope) {
            return [
                'key' => 'scope',
                'status' => 'skipped',
                'message' => 'No changed-file scope constraints were requested.',
            ];
        }

        if ($inScope) {
            return [
                'key' => 'scope',
                'status' => 'passed',
                'message' => 'Changed files stayed within the requested task scope.',
            ];
        }

        if ($policy === 'block') {
            return [
                'key' => 'scope',
                'status' => 'blocked',
                'message' => 'Changed files fall outside the requested task scope.',
            ];
        }

        return [
            'key' => 'scope',
            'status' => 'failed',
            'message' => 'Changed files fall outside the requested task scope.',
        ];
    }

    /**
     * Build the failure-classification checklist item.
     *
     * @param  array<string, mixed>  $report
     * @return array{key: string, status: string, message: string}
     */
    protected function buildFailureChecklistItem(array $report): array
    {
        $failure = is_array($report['failure'] ?? null) ? $report['failure'] : null;

        if ($failure === null) {
            return [
                'key' => 'failure',
                'status' => 'skipped',
                'message' => 'No failure classification is available.',
            ];
        }

        $category = is_string($failure['category'] ?? null) ? $failure['category'] : 'unknown';
        $message = is_string($failure['message'] ?? null) ? $failure['message'] : 'No failure message is available.';

        return match ($category) {
            'none' => [
                'key' => 'failure',
                'status' => 'passed',
                'message' => 'Execution completed without a classified failure.',
            ],
            'provider_follow_up', 'preflight_block' => [
                'key' => 'failure',
                'status' => 'blocked',
                'message' => $message,
            ],
            default => [
                'key' => 'failure',
                'status' => 'failed',
                'message' => $message,
            ],
        };
    }

    /**
     * Build the action-recommendation checklist item.
     *
     * @param  array<string, mixed>  $report
     * @return array{key: string, status: string, message: string}
     */
    protected function buildRecommendationChecklistItem(array $report): array
    {
        $recommendation = is_array($report['recommended_action'] ?? null) ? $report['recommended_action'] : null;

        if ($recommendation === null) {
            return [
                'key' => 'recommendation',
                'status' => 'skipped',
                'message' => 'No recommended next action is available.',
            ];
        }

        $action = is_string($recommendation['action'] ?? null) ? $recommendation['action'] : 'unknown';
        $message = is_string($recommendation['message'] ?? null)
            ? $recommendation['message']
            : 'Review the workflow result to determine the next action.';

        return $action === 'task_complete'
            ? [
                'key' => 'recommendation',
                'status' => 'passed',
                'message' => $message,
            ]
            : [
                'key' => 'recommendation',
                'status' => 'blocked',
                'message' => $message,
            ];
    }

    /**
     * Build the persisted-state checklist item.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @return array{key: string, status: string, message: string}
     */
    protected function buildStateChecklistItem(AutoCodingTask $task, AutoCodingTaskRun $run): array
    {
        if ($task->status === AutoCodingExecutionStatus::Completed && $run->status === AutoCodingExecutionStatus::Completed) {
            return [
                'key' => 'state',
                'status' => 'passed',
                'message' => 'Task and run are marked completed.',
            ];
        }

        if ($task->status === AutoCodingExecutionStatus::Blocked || $run->status === AutoCodingExecutionStatus::Blocked) {
            return [
                'key' => 'state',
                'status' => 'blocked',
                'message' => 'Task is waiting for follow-up input.',
            ];
        }

        if ($task->status === AutoCodingExecutionStatus::Cancelled && $run->status === AutoCodingExecutionStatus::Cancelled) {
            return [
                'key' => 'state',
                'status' => 'passed',
                'message' => 'Task and run were cancelled by operator request.',
            ];
        }

        return [
            'key' => 'state',
            'status' => 'failed',
            'message' => 'Task or run is not in a completed state.',
        ];
    }

    /**
     * Build the final-report checklist item.
     *
     * @param  array<string, mixed>  $report
     * @return array{key: string, status: string, message: string}
     */
    protected function buildReportChecklistItem(array $report): array
    {
        $hasTask = is_array($report['task'] ?? null);
        $hasRun = is_array($report['run'] ?? null);
        $hasWorkflow = is_array($report['workflow'] ?? null);

        return $hasTask && $hasRun && $hasWorkflow
            ? [
                'key' => 'report',
                'status' => 'passed',
                'message' => 'Structured final report is available.',
            ]
            : [
                'key' => 'report',
                'status' => 'failed',
                'message' => 'Structured final report is incomplete.',
            ];
    }
}
