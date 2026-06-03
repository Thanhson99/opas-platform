<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;

/**
 * Build and mutate queue-state report payloads for local auto-coding tasks.
 */
class AutoCodingQueueStateService
{
    /**
     * Build the initial pending report returned before queue execution starts.
     *
     * @param  string  $summary
     * @param  string|null  $issueKey
     * @param  string  $repositoryPath
     * @return array<string, mixed>
     */
    public function buildPendingReport(string $summary, ?string $issueKey, string $repositoryPath): array
    {
        return [
            'status' => AutoCodingExecutionStatus::Pending->value,
            'task' => [
                'summary' => $summary,
                'issue_key' => $issueKey,
            ],
            'queue' => [
                'status' => 'queued',
            ],
            'repository' => [
                'repository_path' => $repositoryPath,
            ],
            'provider_result' => [
                'status' => 'pending',
            ],
            'validation' => [
                'overall_status' => 'pending',
            ],
            'workflow' => [
                'current_step' => null,
                'steps' => [],
            ],
            'summary' => [
                'artifact_count' => 0,
            ],
        ];
    }

    /**
     * Resolve the existing queue report block from one local auto-coding task safely.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    public function resolveQueueReport(AutoCodingTask $task): array
    {
        $latestReport = $task->latest_report;
        $queueReport = is_array($latestReport['queue'] ?? null) ? $latestReport['queue'] : [];

        /** @var array<string, mixed> $queueReport */
        return $queueReport;
    }

    /**
     * Build one claimed queue-report transition for a pending task.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    public function buildClaimedLatestReport(AutoCodingTask $task): array
    {
        $queueReport = $this->resolveQueueReport($task);

        return array_merge($task->latest_report ?? [], [
            'status' => AutoCodingExecutionStatus::Running->value,
            'queue' => array_merge($queueReport, [
                'status' => 'claimed',
                'claimed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Build one resumed queue-report transition for a blocked task.
     *
     * @param  AutoCodingTask  $task
     * @return array<string, mixed>
     */
    public function buildResumedLatestReport(AutoCodingTask $task): array
    {
        $queueReport = $this->resolveQueueReport($task);

        return array_merge($task->latest_report ?? [], [
            'status' => AutoCodingExecutionStatus::Pending->value,
            'follow_up' => [
                'required' => false,
                'answered' => true,
            ],
            'queue' => array_merge($queueReport, [
                'status' => 'resumed',
                'resumed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Build one cancelled queue-report transition for an operator-stopped task.
     *
     * @param  AutoCodingTask  $task
     * @param  string  $scope
     * @return array<string, mixed>
     */
    public function buildCancelledLatestReport(AutoCodingTask $task, string $scope = 'single'): array
    {
        $queueReport = $this->resolveQueueReport($task);

        return array_merge($task->latest_report ?? [], [
            'status' => AutoCodingExecutionStatus::Cancelled->value,
            'queue' => array_merge($queueReport, [
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
                'cancel_scope' => trim($scope) !== '' ? trim($scope) : 'single',
            ]),
            'recommended_action' => [
                'action' => 'task_complete',
                'reason' => 'user_cancelled',
                'message' => 'Task was cancelled by operator request.',
            ],
            'follow_up' => [
                'required' => false,
                'questions' => [],
            ],
        ]);
    }
}
