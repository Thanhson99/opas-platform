<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Enums\AutoCodingWorkflowStepStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingRunArtifact;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Models\AutoCodingTaskRunStep;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AutoCodingObservabilityService
{
    /**
     * Build the admin observability report for auto-coding operations.
     *
     * @param  int  $days
     * @param  array{repository_path?:string|null,machine_key?:string|null}  $filters
     * @return array<string, mixed>
     */
    public function buildReport(int $days = 7, array $filters = []): array
    {
        $windowDays = max(1, min($days, 30));
        $windowStart = now()->subDays($windowDays);
        $normalizedFilters = $this->normalizeFilters($filters);
        $machineHealth = $this->buildMachineHealth($normalizedFilters);
        $taskStatuses = $this->countTasksByStatus($windowStart, $normalizedFilters);
        $repositorySummary = $this->buildRepositorySummary($windowStart, $normalizedFilters);
        $failureSummary = $this->buildFailureSummary($windowStart, $normalizedFilters);
        $validationSummary = $this->buildValidationSummary($windowStart, $normalizedFilters);
        $notifications = $this->buildNotifications($normalizedFilters);
        $errorSummary = $this->buildErrorSummary($windowStart, $normalizedFilters);
        $machineCapacity = $this->buildMachineCapacitySummary($machineHealth);
        $machineFleet = $this->buildMachineFleetSummary($machineHealth);

        return [
            'generated_at' => now()->toIso8601String(),
            'window' => [
                'days' => $windowDays,
                'started_at' => $windowStart->toIso8601String(),
            ],
            'filters' => $normalizedFilters,
            'filter_options' => $this->buildFilterOptions(),
            'summary' => $this->buildSummary($windowStart, $machineHealth, $normalizedFilters),
            'operational_summary' => $this->buildOperationalSummary(
                $machineHealth,
                $taskStatuses,
                $repositorySummary,
                $failureSummary,
                $validationSummary,
                $notifications,
            ),
            'task_statuses' => $taskStatuses,
            'queue_health' => $this->buildQueueHealth($normalizedFilters),
            'daily_activity' => $this->buildDailyActivity($windowDays, $normalizedFilters),
            'repository_summary' => $repositorySummary,
            'recent_tasks' => $this->buildRecentTasks($normalizedFilters),
            'recent_runs' => $this->buildRecentRuns($normalizedFilters),
            'run_performance' => $this->buildRunPerformance($windowStart, $normalizedFilters),
            'reliability_summary' => $this->buildReliabilitySummary($windowStart, $normalizedFilters),
            'machine_health' => $machineHealth,
            'machine_capacity' => $machineCapacity,
            'machine_fleet' => $machineFleet,
            'resource_summary' => $this->buildResourceSummary($machineHealth),
            'machine_capabilities' => $this->buildMachineCapabilitySummary($machineHealth),
            'workspace_bindings' => $this->buildWorkspaceBindingSummary($machineHealth),
            'ai_usage' => $this->buildAiUsage($windowStart, $normalizedFilters),
            'changed_files' => $this->buildChangedFileSummary($windowStart, $normalizedFilters),
            'artifacts' => $this->buildArtifactSummary($windowStart, $normalizedFilters),
            'review_packages' => $this->buildReviewPackages($windowStart, $normalizedFilters),
            'failure_summary' => $failureSummary,
            'validation_summary' => $validationSummary,
            'error_summary' => $errorSummary,
            'execution_summary' => $this->buildExecutionSummary($windowStart, $normalizedFilters),
            'execution_logs' => $this->buildExecutionLogs($normalizedFilters),
            'activity_timeline' => $this->buildActivityTimeline($normalizedFilters),
            'notification_summary' => $this->buildNotificationSummary($notifications),
            'notifications' => $notifications,
            'review_actions' => $this->buildReviewActions(
                $failureSummary,
                $validationSummary,
                $errorSummary,
                $machineCapacity,
                $machineFleet,
                $notifications,
            ),
        ];
    }

    /**
     * Build top-level operational counters.
     *
     * @param  CarbonInterface  $windowStart
     * @param  list<array<string, mixed>>  $machineHealth
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, int>
     */
    protected function buildSummary(CarbonInterface $windowStart, array $machineHealth, array $filters): array
    {
        return [
            'tasks_total' => $this->applyTaskFilters(AutoCodingTask::query(), $filters)->count(),
            'tasks_in_window' => $this->applyTaskFilters(
                AutoCodingTask::query()->where('created_at', '>=', $windowStart),
                $filters,
            )->count(),
            'active_tasks' => $this->applyTaskFilters(AutoCodingTask::query(), $filters)
                ->whereIn('status', AutoCodingExecutionStatus::activeValues())
                ->count(),
            'runs_in_window' => $this->applyRunFilters(
                AutoCodingTaskRun::query()->where('created_at', '>=', $windowStart),
                $filters,
            )->count(),
            'failed_runs_in_window' => $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
                ->where('created_at', '>=', $windowStart)
                ->where('status', AutoCodingExecutionStatus::Failed->value)
                ->count(),
            'online_machines' => count(array_filter(
                $machineHealth,
                static fn (array $machine): bool => $machine['derived_status'] === 'online',
            )),
        ];
    }

    /**
     * Build filter option hints from known repositories and machines.
     *
     * @return array<string, mixed>
     */
    protected function buildFilterOptions(): array
    {
        return [
            'repository_paths' => $this->buildRepositoryFilterOptions(),
            'machines' => $this->buildMachineFilterOptions(),
        ];
    }

    /**
     * Build repository filter options from tasks and machine workspace bindings.
     *
     * @return list<string>
     */
    protected function buildRepositoryFilterOptions(): array
    {
        $repositoryPaths = [];

        $taskRepositoryPaths = AutoCodingTask::query()
            ->whereNotNull('repository_path')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->pluck('repository_path')
            ->all();

        foreach ($taskRepositoryPaths as $repositoryPath) {
            if (is_string($repositoryPath)) {
                $this->appendRepositoryFilterOption($repositoryPaths, $repositoryPath);
            }
        }

        /** @var Collection<int, AutoCodingMachine> $machines */
        $machines = AutoCodingMachine::query()
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get(['repository_path', 'workspace_bindings']);

        foreach ($machines as $machine) {
            $this->appendRepositoryFilterOption($repositoryPaths, $machine->repository_path);

            if (! is_array($machine->workspace_bindings)) {
                continue;
            }

            foreach ($machine->workspace_bindings as $binding) {
                if (is_string($binding['repository_path'] ?? null)) {
                    $this->appendRepositoryFilterOption($repositoryPaths, $binding['repository_path']);
                }
            }
        }

        sort($repositoryPaths);

        return array_slice($repositoryPaths, 0, 50);
    }

    /**
     * Build machine filter options from registered machine identities.
     *
     * @return list<array<string, mixed>>
     */
    protected function buildMachineFilterOptions(): array
    {
        /** @var Collection<int, AutoCodingMachine> $machines */
        $machines = AutoCodingMachine::query()
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->get(['machine_key', 'hostname', 'operating_system', 'availability_status', 'last_seen_at']);

        $options = [];

        foreach ($machines as $machine) {
            $options[] = [
                'machine_key' => $machine->machine_key,
                'hostname' => $machine->hostname,
                'operating_system' => $machine->operating_system,
                'availability_status' => $machine->availability_status,
                'derived_status' => $this->resolveMachineStatus($machine),
                'last_seen_at' => $this->formatTimestamp($machine->last_seen_at),
            ];
        }

        return $options;
    }

    /**
     * Append one repository path filter option when it is usable and unique.
     *
     * @param  array<string, string>  $repositoryPaths
     * @param  string|null  $repositoryPath
     * @return void
     */
    protected function appendRepositoryFilterOption(array &$repositoryPaths, ?string $repositoryPath): void
    {
        $normalizedPath = $this->stringMetric($repositoryPath, '');

        if ($normalizedPath === '') {
            return;
        }

        $repositoryPaths[$normalizedPath] = $normalizedPath;
    }

    /**
     * Build a reviewable health snapshot for the operational report header.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @param  array<string, int>  $taskStatuses
     * @param  list<array<string, mixed>>  $repositorySummary
     * @param  array<string, mixed>  $failureSummary
     * @param  array<string, mixed>  $validationSummary
     * @param  list<array<string, mixed>>  $notifications
     * @return array<string, mixed>
     */
    protected function buildOperationalSummary(
        array $machineHealth,
        array $taskStatuses,
        array $repositorySummary,
        array $failureSummary,
        array $validationSummary,
        array $notifications,
    ): array {
        $criticalNotifications = $this->countNotificationsBySeverity($notifications, 'critical');
        $warningNotifications = $this->countNotificationsBySeverity($notifications, 'warning');
        $offlineMachines = $this->countMachinesByDerivedStatus($machineHealth, 'offline');
        $staleMachines = $this->countMachinesByDerivedStatus($machineHealth, 'stale');
        $failedRepositories = count(array_filter(
            $repositorySummary,
            fn (array $repository): bool => $this->intMetric($repository['failed_task_count'] ?? 0) > 0
                || $this->intMetric($repository['failed_run_count'] ?? 0) > 0,
        ));
        $validationStatuses = is_array($validationSummary['statuses'] ?? null)
            ? $validationSummary['statuses']
            : [];
        $failureCategories = is_array($failureSummary['categories'] ?? null)
            ? $failureSummary['categories']
            : [];
        $validationFailures = $this->intMetric($validationStatuses['failed'] ?? 0);
        $failedTasks = $taskStatuses[AutoCodingExecutionStatus::Failed->value] ?? 0;

        return [
            'health' => $this->resolveOperationalHealth(
                $criticalNotifications,
                $warningNotifications,
                $offlineMachines,
                $staleMachines,
                $failedTasks,
                $validationFailures,
            ),
            'critical_notifications' => $criticalNotifications,
            'warning_notifications' => $warningNotifications,
            'offline_machines' => $offlineMachines,
            'stale_machines' => $staleMachines,
            'failed_repositories' => $failedRepositories,
            'validation_failures' => $validationFailures,
            'failed_tasks' => $failedTasks,
            'failure_categories' => count($failureCategories),
        ];
    }

    /**
     * Resolve overall operational health from report signals.
     *
     * @param  int  $criticalNotifications
     * @param  int  $warningNotifications
     * @param  int  $offlineMachines
     * @param  int  $staleMachines
     * @param  int  $failedTasks
     * @param  int  $validationFailures
     * @return string
     */
    protected function resolveOperationalHealth(
        int $criticalNotifications,
        int $warningNotifications,
        int $offlineMachines,
        int $staleMachines,
        int $failedTasks,
        int $validationFailures,
    ): string {
        if ($criticalNotifications > 0 || $offlineMachines > 0 || $failedTasks > 0 || $validationFailures > 0) {
            return 'critical';
        }

        if ($warningNotifications > 0 || $staleMachines > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * Count notification candidates by severity.
     *
     * @param  list<array<string, mixed>>  $notifications
     * @param  string  $severity
     * @return int
     */
    protected function countNotificationsBySeverity(array $notifications, string $severity): int
    {
        return count(array_filter(
            $notifications,
            static fn (array $notification): bool => ($notification['severity'] ?? null) === $severity,
        ));
    }

    /**
     * Count machines by derived status.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @param  string  $status
     * @return int
     */
    protected function countMachinesByDerivedStatus(array $machineHealth, string $status): int
    {
        return count(array_filter(
            $machineHealth,
            static fn (array $machine): bool => ($machine['derived_status'] ?? null) === $status,
        ));
    }

    /**
     * Build prioritized operator review actions from report summaries.
     *
     * @param  array<string, mixed>  $failureSummary
     * @param  array<string, mixed>  $validationSummary
     * @param  array<string, mixed>  $errorSummary
     * @param  array<string, int>  $machineCapacity
     * @param  array<string, mixed>  $machineFleet
     * @param  list<array<string, mixed>>  $notifications
     * @return list<array<string, mixed>>
     */
    protected function buildReviewActions(
        array $failureSummary,
        array $validationSummary,
        array $errorSummary,
        array $machineCapacity,
        array $machineFleet,
        array $notifications,
    ): array {
        $actions = [];
        $failedTasks = $this->intMetric($failureSummary['total'] ?? 0);
        $validationStatuses = is_array($validationSummary['statuses'] ?? null)
            ? $validationSummary['statuses']
            : [];
        $validationFailures = $this->intMetric($validationStatuses['failed'] ?? 0);
        $repeatedErrors = $this->intMetric($errorSummary['total'] ?? 0);

        if ($failedTasks > 0) {
            $actions[] = $this->reviewAction(
                'critical',
                'failed_tasks',
                'Review failed tasks',
                sprintf('%d failed task(s) need operator review.', $failedTasks),
            );
        }

        if ($validationFailures > 0) {
            $actions[] = $this->reviewAction(
                'critical',
                'validation_failures',
                'Review validation failures',
                sprintf('%d validation failure(s) were recorded in this window.', $validationFailures),
            );
        }

        if ($repeatedErrors > 0) {
            /** @var list<array<string, mixed>> $errorMessages */
            $errorMessages = is_array($errorSummary['messages'] ?? null) ? $errorSummary['messages'] : [];
            $topError = $errorMessages[0] ?? [];
            $actions[] = $this->reviewAction(
                'warning',
                'repeated_errors',
                'Review repeated errors',
                $this->reviewErrorMessage($topError, $repeatedErrors),
            );
        }

        if (
            $this->intMetric($machineCapacity['machine_count'] ?? 0) > 0
            && $this->intMetric($machineCapacity['available_slots'] ?? 0) === 0
        ) {
            $actions[] = $this->reviewAction(
                'warning',
                'machine_capacity',
                'Check machine capacity',
                'All registered machine execution slots are currently occupied.',
            );
        }

        $offlineMachines = $this->fleetStatusCount($machineFleet, 'offline');
        $staleMachines = $this->fleetStatusCount($machineFleet, 'stale');

        if ($offlineMachines > 0 || $staleMachines > 0) {
            $actions[] = $this->reviewAction(
                'warning',
                'machine_heartbeat',
                'Check machine heartbeats',
                sprintf('%d offline and %d stale machine(s) need heartbeat review.', $offlineMachines, $staleMachines),
            );
        }

        foreach (array_slice($notifications, 0, 3) as $notification) {
            if (($notification['severity'] ?? null) !== 'critical') {
                continue;
            }

            $actions[] = $this->reviewAction(
                'critical',
                'critical_notification',
                $this->stringMetric($notification['title'] ?? null, 'Critical notification'),
                $this->stringMetric($notification['message'] ?? null, 'Review this critical notification.'),
            );
        }

        return array_slice($actions, 0, 8);
    }

    /**
     * Build one review action row.
     *
     * @param  string  $priority
     * @param  string  $type
     * @param  string  $title
     * @param  string  $message
     * @return array<string, string>
     */
    protected function reviewAction(string $priority, string $type, string $title, string $message): array
    {
        return [
            'priority' => $priority,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * Resolve a compact repeated-error review message.
     *
     * @param  array<string, mixed>  $topError
     * @param  int  $total
     * @return string
     */
    protected function reviewErrorMessage(array $topError, int $total): string
    {
        $message = $this->stringMetric($topError['message'] ?? null, 'Unknown error');
        $source = $this->stringMetric($topError['source'] ?? null, 'unknown');

        return sprintf('%d error event(s) recorded. Top source %s: %s', $total, $source, $message);
    }

    /**
     * Resolve one machine fleet status count.
     *
     * @param  array<string, mixed>  $machineFleet
     * @param  string  $status
     * @return int
     */
    protected function fleetStatusCount(array $machineFleet, string $status): int
    {
        $counts = is_array($machineFleet['derived_status_counts'] ?? null)
            ? $machineFleet['derived_status_counts']
            : [];

        return $this->intMetric($counts[$status] ?? 0);
    }

    /**
     * Count tasks by status within the report window.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, int>
     */
    protected function countTasksByStatus(CarbonInterface $windowStart, array $filters): array
    {
        $rawCounts = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->select('status', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $counts = [];

        foreach ($rawCounts as $status => $count) {
            if (is_string($status)) {
                $counts[$status] = $this->intMetric($count);
            }
        }

        foreach (AutoCodingExecutionStatus::allValues() as $status) {
            $counts[$status] ??= 0;
        }

        return $counts;
    }

    /**
     * Build active queue aging metrics for stuck-work review.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildQueueHealth(array $filters): array
    {
        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->with('assignedMachine:id,machine_key,hostname')
            ->whereIn('status', AutoCodingExecutionStatus::activeValues())
            ->orderBy('created_at')
            ->limit(100)
            ->get([
                'id',
                'summary',
                'repository_path',
                'assigned_machine_id',
                'status',
                'created_at',
                'claimed_at',
            ]);

        $statusCounts = array_fill_keys(AutoCodingExecutionStatus::activeValues(), 0);
        $ageMinutes = [];
        $oldestTasks = [];

        foreach ($tasks as $task) {
            $age = $this->ageMinutes($task->created_at);
            $statusCounts[$task->status->value] = $this->intMetric($statusCounts[$task->status->value] ?? 0) + 1;
            $ageMinutes[] = $age;
            $oldestTasks[] = [
                'id' => $task->id,
                'summary' => $task->summary,
                'status' => $task->status->value,
                'repository_path' => $task->repository_path,
                'machine_key' => $task->assignedMachine?->machine_key,
                'age_minutes' => $age,
                'created_at' => $this->formatTimestamp($task->created_at),
                'claimed_at' => $this->formatTimestamp($task->claimed_at),
            ];
        }

        usort(
            $oldestTasks,
            static fn (array $left, array $right): int => $right['age_minutes'] <=> $left['age_minutes'],
        );

        $activeCount = count($ageMinutes);

        return [
            'active_count' => $activeCount,
            'status_counts' => $statusCounts,
            'oldest_age_minutes' => $activeCount > 0 ? max($ageMinutes) : 0,
            'average_age_minutes' => $activeCount > 0 ? (int) round(array_sum($ageMinutes) / $activeCount) : 0,
            'oldest_tasks' => array_slice($oldestTasks, 0, 8),
        ];
    }

    /**
     * Build daily task and run activity buckets for trend review.
     *
     * @param  int  $windowDays
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildDailyActivity(int $windowDays, array $filters): array
    {
        $startedAt = now()->startOfDay()->subDays(max(0, $windowDays - 1));
        $buckets = $this->emptyDailyActivityBuckets($startedAt, $windowDays);

        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->where('created_at', '>=', $startedAt)
            ->get(['id', 'created_at']);

        foreach ($tasks as $task) {
            $date = $this->bucketDate($task->created_at);

            if ($date !== null && isset($buckets[$date])) {
                $buckets[$date]['tasks_created'] = $this->intMetric($buckets[$date]['tasks_created'] ?? 0) + 1;
            }
        }

        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->where('created_at', '>=', $startedAt)
            ->get(['id', 'status', 'created_at']);

        foreach ($runs as $run) {
            $date = $this->bucketDate($run->created_at);

            if ($date === null || ! isset($buckets[$date])) {
                continue;
            }

            $buckets[$date]['runs_created'] = $this->intMetric($buckets[$date]['runs_created'] ?? 0) + 1;

            if ($run->status === AutoCodingExecutionStatus::Completed) {
                $buckets[$date]['completed_runs'] =
                    $this->intMetric($buckets[$date]['completed_runs'] ?? 0) + 1;
            }

            if ($run->status === AutoCodingExecutionStatus::Failed) {
                $buckets[$date]['failed_runs'] = $this->intMetric($buckets[$date]['failed_runs'] ?? 0) + 1;
            }
        }

        return array_values($buckets);
    }

    /**
     * Build empty daily activity buckets.
     *
     * @param  CarbonInterface  $startedAt
     * @param  int  $windowDays
     * @return array<string, array<string, mixed>>
     */
    protected function emptyDailyActivityBuckets(CarbonInterface $startedAt, int $windowDays): array
    {
        $buckets = [];

        for ($dayOffset = 0; $dayOffset < $windowDays; $dayOffset++) {
            $date = $startedAt->copy()->addDays($dayOffset)->toDateString();
            $buckets[$date] = [
                'date' => $date,
                'tasks_created' => 0,
                'runs_created' => 0,
                'completed_runs' => 0,
                'failed_runs' => 0,
            ];
        }

        return $buckets;
    }

    /**
     * Build repository-level task and run visibility for multi-machine routing review.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildRepositorySummary(CarbonInterface $windowStart, array $filters): array
    {
        $rows = $this->buildRepositoryTaskRows($windowStart, $filters);
        $rows = $this->mergeRepositoryRunCounts($rows, $windowStart, $filters);
        $rows = $this->mergeRepositoryLatestTasks($rows, $windowStart, $filters);

        $summaryRows = array_map(
            fn (array $row): array => $this->finalizeRepositorySummaryRow($row),
            array_values($rows),
        );

        usort(
            $summaryRows,
            static fn (array $left, array $right): int => [
                $right['failed_task_count'],
                $right['failed_run_count'],
                $right['active_task_count'],
                $right['task_count'],
            ] <=> [
                $left['failed_task_count'],
                $left['failed_run_count'],
                $left['active_task_count'],
                $left['task_count'],
            ],
        );

        return array_slice($summaryRows, 0, 12);
    }

    /**
     * Build repository rows from task status counts.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, array<string, mixed>>
     */
    protected function buildRepositoryTaskRows(CarbonInterface $windowStart, array $filters): array
    {
        $rawCounts = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->select('repository_path', 'status', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy('repository_path', 'status')
            ->get();
        $rows = [];

        foreach ($rawCounts as $countRow) {
            $repositoryPath = trim($countRow->repository_path) !== ''
                ? $countRow->repository_path
                : 'unknown';
            $row = $rows[$repositoryPath] ?? $this->emptyRepositorySummaryRow($repositoryPath);
            $statusCounts = is_array($row['status_counts'] ?? null) ? $row['status_counts'] : [];
            $statusCounts[$countRow->status->value] = $this->intMetric($countRow->getAttribute('total'));
            $row['status_counts'] = $statusCounts;
            $rows[$repositoryPath] = $row;
        }

        return $rows;
    }

    /**
     * Merge run counts into repository summary rows.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, array<string, mixed>>
     */
    protected function mergeRepositoryRunCounts(array $rows, CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with('task:id,repository_path')
            ->where('created_at', '>=', $windowStart)
            ->get(['id', 'task_id', 'status']);

        foreach ($runs as $run) {
            $repositoryPath = $run->task instanceof AutoCodingTask
                && trim($run->task->repository_path) !== ''
                    ? $run->task->repository_path
                    : 'unknown';
            $rows[$repositoryPath] ??= $this->emptyRepositorySummaryRow($repositoryPath);
            $rows[$repositoryPath]['run_count'] = $this->intMetric($rows[$repositoryPath]['run_count'] ?? 0) + 1;

            if ($run->status === AutoCodingExecutionStatus::Failed) {
                $rows[$repositoryPath]['failed_run_count'] =
                    $this->intMetric($rows[$repositoryPath]['failed_run_count'] ?? 0) + 1;
            }
        }

        return $rows;
    }

    /**
     * Merge latest task references into repository summary rows.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, array<string, mixed>>
     */
    protected function mergeRepositoryLatestTasks(array $rows, CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get(['id', 'summary', 'repository_path', 'status', 'updated_at']);

        foreach ($tasks as $task) {
            $repositoryPath = trim($task->repository_path) !== ''
                ? $task->repository_path
                : 'unknown';
            $rows[$repositoryPath] ??= $this->emptyRepositorySummaryRow($repositoryPath);

            if (($rows[$repositoryPath]['latest_task_id'] ?? null) !== null) {
                continue;
            }

            $rows[$repositoryPath]['latest_task_id'] = $task->id;
            $rows[$repositoryPath]['latest_task_summary'] = $task->summary;
            $rows[$repositoryPath]['latest_task_status'] = $task->status->value;
            $rows[$repositoryPath]['latest_updated_at'] = $this->formatTimestamp($task->updated_at);
        }

        return $rows;
    }

    /**
     * Build an empty repository summary row.
     *
     * @param  string  $repositoryPath
     * @return array<string, mixed>
     */
    protected function emptyRepositorySummaryRow(string $repositoryPath): array
    {
        return [
            'repository_path' => $repositoryPath,
            'status_counts' => array_fill_keys(AutoCodingExecutionStatus::allValues(), 0),
            'run_count' => 0,
            'failed_run_count' => 0,
            'latest_task_id' => null,
            'latest_task_summary' => null,
            'latest_task_status' => null,
            'latest_updated_at' => null,
        ];
    }

    /**
     * Finalize derived repository row counters.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function finalizeRepositorySummaryRow(array $row): array
    {
        $statusCounts = is_array($row['status_counts'] ?? null) ? $row['status_counts'] : [];

        foreach (AutoCodingExecutionStatus::allValues() as $status) {
            $statusCounts[$status] = $this->intMetric($statusCounts[$status] ?? 0);
        }

        return [
            'repository_path' => $row['repository_path'],
            'task_count' => array_sum($statusCounts),
            'active_task_count' => array_sum(array_intersect_key(
                $statusCounts,
                array_fill_keys(AutoCodingExecutionStatus::activeValues(), true),
            )),
            'failed_task_count' => $this->intMetric($statusCounts[AutoCodingExecutionStatus::Failed->value] ?? 0),
            'run_count' => $this->intMetric($row['run_count'] ?? 0),
            'failed_run_count' => $this->intMetric($row['failed_run_count'] ?? 0),
            'status_counts' => $statusCounts,
            'latest_task_id' => $row['latest_task_id'] ?? null,
            'latest_task_summary' => $row['latest_task_summary'] ?? null,
            'latest_task_status' => $row['latest_task_status'] ?? null,
            'latest_updated_at' => $row['latest_updated_at'] ?? null,
        ];
    }

    /**
     * Build recent task history entries for dashboard review.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildRecentTasks(array $filters): array
    {
        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->with(['assignedMachine', 'runs.artifacts'])
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        /** @var list<array<string, mixed>> $entries */
        $entries = $tasks
            ->map(fn (AutoCodingTask $task): array => [
                'id' => $task->id,
                'summary' => $task->summary,
                'issue_key' => $task->issue_key,
                'status' => $task->status->value,
                'repository_path' => $task->repository_path,
                'branch_name' => $task->branch_name,
                'assigned_machine' => $task->assignedMachine === null ? null : [
                    'id' => $task->assignedMachine->id,
                    'machine_key' => $task->assignedMachine->machine_key,
                    'hostname' => $task->assignedMachine->hostname,
                ],
                'run_count' => $task->runs->count(),
                'artifact_count' => $task->runs->sum(
                    fn (AutoCodingTaskRun $run): int => $run->artifacts->count(),
                ),
                'created_at' => $this->formatTimestamp($task->created_at),
                'completed_at' => $this->formatTimestamp($task->completed_at),
            ])
            ->values()
            ->all();

        return $entries;
    }

    /**
     * Build recent run history entries with reviewable execution fields.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildRecentRuns(array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with(['task', 'machine', 'artifacts', 'steps'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        /** @var list<array<string, mixed>> $entries */
        $entries = $runs
            ->map(fn (AutoCodingTaskRun $run): array => [
                'id' => $run->id,
                'task_id' => $run->task_id,
                'task_summary' => $run->task?->summary,
                'status' => $run->status->value,
                'machine_key' => $run->machine?->machine_key,
                'duration_seconds' => $this->durationSeconds($run->started_at, $run->completed_at),
                'changed_file_count' => is_array($run->changed_files) ? count($run->changed_files) : 0,
                'artifact_count' => $run->artifacts->count(),
                'step_count' => $run->steps->count(),
                'provider' => $this->resolveProviderName($run->provider_result),
                'model' => $this->resolveModelName($run->provider_result),
                'started_at' => $this->formatTimestamp($run->started_at),
                'completed_at' => $this->formatTimestamp($run->completed_at),
            ])
            ->values()
            ->all();

        return $entries;
    }

    /**
     * Build run duration performance metrics for the selected window.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildRunPerformance(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with(['task:id,summary', 'machine:id,machine_key'])
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'task_id', 'machine_id', 'status', 'started_at', 'completed_at']);

        $durationRows = [];

        foreach ($runs as $run) {
            $durationSeconds = $this->durationSeconds($run->started_at, $run->completed_at);

            if ($durationSeconds === null) {
                continue;
            }

            $durationRows[] = [
                'id' => $run->id,
                'task_id' => $run->task_id,
                'task_summary' => $run->task?->summary,
                'status' => $run->status->value,
                'machine_key' => $run->machine?->machine_key,
                'duration_seconds' => $durationSeconds,
                'completed_at' => $this->formatTimestamp($run->completed_at),
            ];
        }

        usort(
            $durationRows,
            static fn (array $left, array $right): int => $right['duration_seconds'] <=> $left['duration_seconds'],
        );

        $durations = array_map(
            static fn (array $row): int => (int) $row['duration_seconds'],
            $durationRows,
        );
        $runCount = count($durations);

        return [
            'run_count' => $runCount,
            'average_duration_seconds' => $runCount > 0 ? (int) round(array_sum($durations) / $runCount) : 0,
            'min_duration_seconds' => $runCount > 0 ? min($durations) : 0,
            'max_duration_seconds' => $runCount > 0 ? max($durations) : 0,
            'slowest_runs' => array_slice($durationRows, 0, 5),
        ];
    }

    /**
     * Build run reliability metrics by status, machine, and provider.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildReliabilitySummary(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with('machine:id,machine_key')
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'machine_id', 'status', 'provider_result']);

        $statusCounts = $this->emptyRunReliabilityStatusCounts();
        $machineRows = [];
        $providerRows = [];

        foreach ($runs as $run) {
            $status = $run->status->value;
            $statusCounts[$status] = $this->intMetric($statusCounts[$status] ?? 0) + 1;

            $machineKey = $run->machine instanceof AutoCodingMachine ? $run->machine->machine_key : 'unassigned';
            $provider = $this->resolveProviderName($run->provider_result);
            $this->accumulateReliabilityRow($machineRows, $machineKey, $status);
            $this->accumulateReliabilityRow($providerRows, $provider, $status);
        }

        return [
            'run_count' => $runs->count(),
            'status_counts' => $statusCounts,
            'success_rate_percent' => $this->ratePercent(
                $statusCounts[AutoCodingExecutionStatus::Completed->value] ?? 0,
                $runs->count(),
            ),
            'failure_rate_percent' => $this->ratePercent(
                $statusCounts[AutoCodingExecutionStatus::Failed->value] ?? 0,
                $runs->count(),
            ),
            'machines' => $this->finalizeReliabilityRows($machineRows),
            'providers' => $this->finalizeReliabilityRows($providerRows),
        ];
    }

    /**
     * Build empty run reliability status counters.
     *
     * @return array<string, int>
     */
    protected function emptyRunReliabilityStatusCounts(): array
    {
        return array_fill_keys(AutoCodingExecutionStatus::allValues(), 0);
    }

    /**
     * Accumulate one reliability group row.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  string  $name
     * @param  string  $status
     * @return void
     */
    protected function accumulateReliabilityRow(array &$rows, string $name, string $status): void
    {
        $rows[$name] ??= [
            'name' => $name,
            'run_count' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];
        $rows[$name]['run_count'] = $this->intMetric($rows[$name]['run_count'] ?? 0) + 1;

        if ($status === AutoCodingExecutionStatus::Completed->value) {
            $rows[$name]['completed'] = $this->intMetric($rows[$name]['completed'] ?? 0) + 1;
        }

        if ($status === AutoCodingExecutionStatus::Failed->value) {
            $rows[$name]['failed'] = $this->intMetric($rows[$name]['failed'] ?? 0) + 1;
        }

        if ($status === AutoCodingExecutionStatus::Cancelled->value) {
            $rows[$name]['cancelled'] = $this->intMetric($rows[$name]['cancelled'] ?? 0) + 1;
        }
    }

    /**
     * Finalize reliability rows sorted by failure pressure and volume.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function finalizeReliabilityRows(array $rows): array
    {
        $finalRows = [];

        foreach ($rows as $row) {
            $runCount = $this->intMetric($row['run_count'] ?? 0);
            $completed = $this->intMetric($row['completed'] ?? 0);
            $failed = $this->intMetric($row['failed'] ?? 0);

            $finalRows[] = [
                'name' => $this->stringMetric($row['name'] ?? null, 'unknown'),
                'run_count' => $runCount,
                'completed' => $completed,
                'failed' => $failed,
                'cancelled' => $this->intMetric($row['cancelled'] ?? 0),
                'success_rate_percent' => $this->ratePercent($completed, $runCount),
                'failure_rate_percent' => $this->ratePercent($failed, $runCount),
            ];
        }

        usort(
            $finalRows,
            static fn (array $left, array $right): int => [
                $right['failed'],
                $right['run_count'],
                $right['failure_rate_percent'],
            ] <=> [
                $left['failed'],
                $left['run_count'],
                $left['failure_rate_percent'],
            ],
        );

        return array_slice($finalRows, 0, 8);
    }

    /**
     * Build machine health and resource monitoring snapshots.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildMachineHealth(array $filters): array
    {
        /** @var Collection<int, AutoCodingMachine> $machines */
        $machines = $this->applyMachineFilters(AutoCodingMachine::query(), $filters)
            ->withCount([
                'taskRuns',
                'taskRuns as running_task_runs_count' => fn (Builder $query): Builder => $query
                    ->where('status', AutoCodingExecutionStatus::Running->value),
            ])
            ->orderByDesc('last_seen_at')
            ->get();

        /** @var list<array<string, mixed>> $entries */
        $entries = $machines
            ->map(fn (AutoCodingMachine $machine): array => $this->machineHealthRow($machine))
            ->values()
            ->all();

        return $entries;
    }

    /**
     * Build one machine health row.
     *
     * @param  AutoCodingMachine  $machine
     * @return array<string, mixed>
     */
    protected function machineHealthRow(AutoCodingMachine $machine): array
    {
        $runningRuns = $this->intMetric($machine->running_task_runs_count ?? 0);
        $maxParallelTasks = $this->intMetric($machine->max_parallel_tasks);

        return [
            'id' => $machine->id,
            'machine_key' => $machine->machine_key,
            'hostname' => $machine->hostname,
            'operating_system' => $machine->operating_system,
            'availability_status' => $machine->availability_status,
            'derived_status' => $this->resolveMachineStatus($machine),
            'repository_path' => $machine->repository_path,
            'workspace_count' => is_array($machine->workspace_bindings)
                ? count($machine->workspace_bindings)
                : 0,
            'workspace_bindings' => $this->normalizeMachineWorkspaceBindings($machine->workspace_bindings),
            'capabilities' => $machine->capabilities ?? [],
            'max_parallel_tasks' => $machine->max_parallel_tasks,
            'running_task_runs_count' => $runningRuns,
            'task_run_count' => $this->intMetric($machine->task_runs_count ?? 0),
            'capacity' => $this->buildMachineCapacity($runningRuns, $maxParallelTasks),
            'resources' => $this->extractResourceMetrics($machine->metadata),
            'last_seen_at' => $this->formatTimestamp($machine->last_seen_at),
        ];
    }

    /**
     * Build capacity details for one machine.
     *
     * @param  int  $runningRuns
     * @param  int  $maxParallelTasks
     * @return array<string, int|bool>
     */
    protected function buildMachineCapacity(int $runningRuns, int $maxParallelTasks): array
    {
        $availableSlots = $maxParallelTasks > 0 ? max(0, $maxParallelTasks - $runningRuns) : 0;
        $utilizationPercent = $maxParallelTasks > 0
            ? min(100, (int) round(($runningRuns / $maxParallelTasks) * 100))
            : 0;

        return [
            'running' => $runningRuns,
            'max_parallel' => $maxParallelTasks,
            'available_slots' => $availableSlots,
            'utilization_percent' => $utilizationPercent,
            'is_at_capacity' => $maxParallelTasks > 0 && $runningRuns >= $maxParallelTasks,
        ];
    }

    /**
     * Build aggregate capacity counters from machine health rows.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @return array<string, int>
     */
    protected function buildMachineCapacitySummary(array $machineHealth): array
    {
        $summary = [
            'machine_count' => count($machineHealth),
            'running_slots' => 0,
            'max_parallel_slots' => 0,
            'available_slots' => 0,
            'machines_at_capacity' => 0,
        ];

        foreach ($machineHealth as $machine) {
            $capacity = is_array($machine['capacity'] ?? null) ? $machine['capacity'] : [];
            $summary['running_slots'] += $this->intMetric($capacity['running'] ?? 0);
            $summary['max_parallel_slots'] += $this->intMetric($capacity['max_parallel'] ?? 0);
            $summary['available_slots'] += $this->intMetric($capacity['available_slots'] ?? 0);
            $summary['machines_at_capacity'] += ($capacity['is_at_capacity'] ?? false) === true ? 1 : 0;
        }

        $summary['utilization_percent'] = $summary['max_parallel_slots'] > 0
            ? min(100, (int) round(($summary['running_slots'] / $summary['max_parallel_slots']) * 100))
            : 0;

        return $summary;
    }

    /**
     * Build aggregate resource metrics from machine heartbeat metadata.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @return array<string, mixed>
     */
    protected function buildResourceSummary(array $machineHealth): array
    {
        $metricValues = [];
        $pressureRows = [];
        $reportedMachineKeys = [];

        foreach ($machineHealth as $machine) {
            $resources = is_array($machine['resources'] ?? null) ? $machine['resources'] : [];
            $machineKey = $this->stringMetric($machine['machine_key'] ?? null, 'unknown');
            $hostname = $this->stringMetric($machine['hostname'] ?? null, 'unknown');

            foreach ($this->resourceSummaryMetricKeys() as $metric) {
                $value = $this->numericMetric($resources[$metric] ?? null);

                if ($value === null) {
                    continue;
                }

                $metricValues[$metric][] = $value;
                $reportedMachineKeys[$machineKey] = true;

                if ($this->isResourcePressureMetric($metric)) {
                    $pressureRows[] = [
                        'metric' => $metric,
                        'value' => $value,
                        'machine_key' => $machineKey,
                        'hostname' => $hostname,
                    ];
                }
            }
        }

        usort(
            $pressureRows,
            static fn (array $left, array $right): int => $right['value'] <=> $left['value'],
        );

        return [
            'reported_machines' => count($reportedMachineKeys),
            'metrics' => $this->finalizeResourceMetricSummaries($metricValues),
            'highest_pressure' => array_slice($pressureRows, 0, 8),
        ];
    }

    /**
     * Return resource metric keys that are useful for fleet-level review.
     *
     * @return list<string>
     */
    protected function resourceSummaryMetricKeys(): array
    {
        return [
            'cpu_percent',
            'memory_percent',
            'disk_percent',
            'load_average',
            'process_memory_mb',
            'process_peak_memory_mb',
        ];
    }

    /**
     * Determine whether a metric is comparable as resource pressure.
     *
     * @param  string  $metric
     * @return bool
     */
    protected function isResourcePressureMetric(string $metric): bool
    {
        return in_array($metric, [
            'cpu_percent',
            'memory_percent',
            'disk_percent',
            'load_average',
        ], true);
    }

    /**
     * Finalize aggregate resource metric rows.
     *
     * @param  array<string, list<int|float>>  $metricValues
     * @return array<string, array<string, int|float>>
     */
    protected function finalizeResourceMetricSummaries(array $metricValues): array
    {
        $summaries = [];

        foreach ($this->resourceSummaryMetricKeys() as $metric) {
            $values = $metricValues[$metric] ?? [];
            $reportedCount = count($values);

            $summaries[$metric] = [
                'reported_count' => $reportedCount,
                'average' => $reportedCount > 0 ? round(array_sum($values) / $reportedCount, 2) : 0,
                'max' => $reportedCount > 0 ? max($values) : 0,
            ];
        }

        return $summaries;
    }

    /**
     * Build aggregate machine fleet counts for status, availability, and OS review.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @return array<string, mixed>
     */
    protected function buildMachineFleetSummary(array $machineHealth): array
    {
        $derivedStatusCounts = [
            'online' => 0,
            'stale' => 0,
            'offline' => 0,
            'unknown' => 0,
        ];
        $availabilityCounts = [];
        $operatingSystemCounts = [];

        foreach ($machineHealth as $machine) {
            $derivedStatus = $this->stringMetric($machine['derived_status'] ?? null, 'unknown');
            $availability = $this->stringMetric($machine['availability_status'] ?? null, 'unknown');
            $operatingSystem = $this->stringMetric($machine['operating_system'] ?? null, 'unknown');
            $derivedStatusCounts[$derivedStatus] = $this->intMetric($derivedStatusCounts[$derivedStatus] ?? 0) + 1;
            $availabilityCounts[$availability] = $this->intMetric($availabilityCounts[$availability] ?? 0) + 1;
            $operatingSystemCounts[$operatingSystem] = $this->intMetric($operatingSystemCounts[$operatingSystem] ?? 0) + 1;
        }

        arsort($availabilityCounts);
        arsort($operatingSystemCounts);

        return [
            'machine_count' => count($machineHealth),
            'derived_status_counts' => $derivedStatusCounts,
            'availability_counts' => $availabilityCounts,
            'operating_system_counts' => $operatingSystemCounts,
        ];
    }

    /**
     * Build aggregate capability counts from registered machines.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @return array<string, mixed>
     */
    protected function buildMachineCapabilitySummary(array $machineHealth): array
    {
        $capabilityCounts = [];
        $machinesWithCapabilities = 0;

        foreach ($machineHealth as $machine) {
            $capabilities = is_array($machine['capabilities'] ?? null) ? $machine['capabilities'] : [];

            if ($capabilities !== []) {
                $machinesWithCapabilities++;
            }

            foreach ($capabilities as $capability => $enabled) {
                if (! is_string($capability) || $capability === '') {
                    continue;
                }

                $bucket = $enabled === false ? 'disabled' : 'enabled';
                $capabilityCounts[$capability] ??= [
                    'enabled' => 0,
                    'disabled' => 0,
                ];
                $capabilityCounts[$capability][$bucket] =
                    $this->intMetric($capabilityCounts[$capability][$bucket] ?? 0) + 1;
            }
        }

        uasort(
            $capabilityCounts,
            static fn (array $left, array $right): int => $right['enabled'] <=> $left['enabled'],
        );

        return [
            'machine_count' => count($machineHealth),
            'machines_with_capabilities' => $machinesWithCapabilities,
            'capabilities' => array_slice($capabilityCounts, 0, 12, true),
        ];
    }

    /**
     * Build aggregate workspace binding coverage by repository.
     *
     * @param  list<array<string, mixed>>  $machineHealth
     * @return array<string, mixed>
     */
    protected function buildWorkspaceBindingSummary(array $machineHealth): array
    {
        $repositories = [];
        $totalBindings = 0;

        foreach ($machineHealth as $machine) {
            $machineKey = $this->stringMetric($machine['machine_key'] ?? null, 'unknown');
            $bindings = is_array($machine['workspace_bindings'] ?? null) ? $machine['workspace_bindings'] : [];

            foreach ($bindings as $binding) {
                if (! is_array($binding)) {
                    continue;
                }

                $repositoryPath = $this->stringMetric($binding['repository_path'] ?? null, 'unknown');
                $workspacePath = $this->stringMetric($binding['workspace_path'] ?? null, $repositoryPath);
                $repositories[$repositoryPath] ??= [
                    'repository_path' => $repositoryPath,
                    'workspace_paths' => [],
                    'machine_keys' => [],
                    'binding_count' => 0,
                ];
                $repositories[$repositoryPath]['binding_count'] =
                    $this->intMetric($repositories[$repositoryPath]['binding_count'] ?? 0) + 1;
                $repositories[$repositoryPath]['workspace_paths'][$workspacePath] = true;
                $repositories[$repositoryPath]['machine_keys'][$machineKey] = true;
                $totalBindings++;
            }
        }

        $rows = array_map(
            fn (array $repository): array => $this->finalizeWorkspaceBindingRow($repository),
            array_values($repositories),
        );

        usort(
            $rows,
            static fn (array $left, array $right): int => ($right['binding_count'] ?? 0) <=> ($left['binding_count'] ?? 0),
        );

        return [
            'total_bindings' => $totalBindings,
            'repository_count' => count($rows),
            'repositories' => array_slice($rows, 0, 12),
        ];
    }

    /**
     * Normalize persisted workspace bindings for report consumers.
     *
     * @param  mixed  $workspaceBindings
     * @return list<array<string, string|null>>
     */
    protected function normalizeMachineWorkspaceBindings(mixed $workspaceBindings): array
    {
        if (! is_array($workspaceBindings)) {
            return [];
        }

        $bindings = [];

        foreach ($workspaceBindings as $binding) {
            if (! is_array($binding)) {
                continue;
            }

            $repositoryPath = $this->stringMetric($binding['repository_path'] ?? null, '');

            if ($repositoryPath === '') {
                continue;
            }

            $bindings[] = [
                'repository_path' => $repositoryPath,
                'workspace_path' => $this->stringMetric($binding['workspace_path'] ?? null, $repositoryPath),
                'active_branch' => is_string($binding['active_branch'] ?? null)
                    ? trim($binding['active_branch'])
                    : null,
            ];
        }

        return $bindings;
    }

    /**
     * Finalize one workspace binding row.
     *
     * @param  array<string, mixed>  $repository
     * @return array<string, mixed>
     */
    protected function finalizeWorkspaceBindingRow(array $repository): array
    {
        $workspacePaths = is_array($repository['workspace_paths'] ?? null)
            ? array_keys($repository['workspace_paths'])
            : [];
        $machineKeys = is_array($repository['machine_keys'] ?? null)
            ? array_keys($repository['machine_keys'])
            : [];

        sort($workspacePaths);
        sort($machineKeys);

        return [
            'repository_path' => $this->stringMetric($repository['repository_path'] ?? null, 'unknown'),
            'binding_count' => $this->intMetric($repository['binding_count'] ?? 0),
            'workspace_count' => count($workspacePaths),
            'machine_count' => count($machineKeys),
            'workspace_paths' => $workspacePaths,
            'machine_keys' => $machineKeys,
        ];
    }

    /**
     * Build provider and model usage summaries.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildAiUsage(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('id')
            ->get(['id', 'provider_result', 'status']);

        $providers = [];
        $models = [];
        $tokenTotals = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
        ];

        foreach ($runs as $run) {
            $provider = $this->resolveProviderName($run->provider_result);
            $model = $this->resolveModelName($run->provider_result);
            $providers[$provider] = ($providers[$provider] ?? 0) + 1;
            $models[$model] = ($models[$model] ?? 0) + 1;

            foreach ($this->extractTokenUsage($run->provider_result) as $key => $value) {
                $tokenTotals[$key] += $value;
            }
        }

        arsort($providers);
        arsort($models);

        return [
            'run_count' => $runs->count(),
            'providers' => $providers,
            'models' => $models,
            'tokens' => $tokenTotals,
        ];
    }

    /**
     * Summarize changed files from recent completed runs.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildChangedFileSummary(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with('task')
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('changed_files')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $files = [];
        $statusCounts = [];
        $extensionCounts = [];

        foreach ($runs as $run) {
            foreach ($this->normalizeChangedFiles($run->changed_files) as $file) {
                $statusCounts[$file['status']] = ($statusCounts[$file['status']] ?? 0) + 1;
                $extension = $this->resolveFileExtension($file['path']);
                $extensionCounts[$extension] = ($extensionCounts[$extension] ?? 0) + 1;
                $files[] = [
                    'path' => $file['path'],
                    'status' => $file['status'],
                    'run_id' => $run->id,
                    'task_id' => $run->task_id,
                    'task_summary' => $run->task?->summary,
                ];
            }
        }

        arsort($statusCounts);
        arsort($extensionCounts);

        return [
            'total' => count($files),
            'status_counts' => $statusCounts,
            'extension_counts' => array_slice($extensionCounts, 0, 8, true),
            'files' => array_slice($files, 0, 30),
        ];
    }

    /**
     * Summarize persisted artifact review records.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildArtifactSummary(CarbonInterface $windowStart, array $filters): array
    {
        $rawTypeCounts = $this->applyArtifactFilters(AutoCodingRunArtifact::query(), $filters)
            ->select('type', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();
        $typeCounts = [];

        foreach ($rawTypeCounts as $type => $count) {
            if (is_string($type)) {
                $typeCounts[$type] = $this->intMetric($count);
            }
        }

        /** @var Collection<int, AutoCodingRunArtifact> $recentArtifacts */
        $recentArtifacts = $this->applyArtifactFilters(AutoCodingRunArtifact::query(), $filters)
            ->with('taskRun.task')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return [
            'total' => array_sum($typeCounts),
            'types' => $typeCounts,
            'recent' => $recentArtifacts
                ->map(fn (AutoCodingRunArtifact $artifact): array => [
                    'id' => $artifact->id,
                    'task_run_id' => $artifact->task_run_id,
                    'task_id' => $artifact->taskRun?->task_id,
                    'task_summary' => $artifact->taskRun?->task?->summary,
                    'type' => $artifact->type,
                    'label' => $artifact->label,
                    'created_at' => $this->formatTimestamp($artifact->created_at),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Build run-level review packages for changed files and artifacts.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildReviewPackages(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with(['task:id,summary,repository_path', 'machine:id,machine_key', 'artifacts'])
            ->where('created_at', '>=', $windowStart)
            ->where(function (Builder $query): void {
                $query->whereNotNull('changed_files')
                    ->orWhereHas('artifacts');
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'task_id', 'machine_id', 'status', 'changed_files', 'completed_at', 'updated_at']);

        $packages = [];

        foreach ($runs as $run) {
            $changedFiles = $this->normalizeChangedFiles($run->changed_files);
            $artifactCount = $run->artifacts->count();

            if ($changedFiles === [] && $artifactCount === 0) {
                continue;
            }

            $packages[] = [
                'run_id' => $run->id,
                'task_id' => $run->task_id,
                'task_summary' => $run->task?->summary,
                'repository_path' => $run->task?->repository_path,
                'machine_key' => $run->machine?->machine_key,
                'status' => $run->status->value,
                'changed_file_count' => count($changedFiles),
                'artifact_count' => $artifactCount,
                'changed_file_status_counts' => $this->countChangedFileStatuses($changedFiles),
                'artifact_type_counts' => $this->countArtifactTypes($run->artifacts),
                'completed_at' => $this->formatTimestamp($run->completed_at ?? $run->updated_at),
            ];
        }

        usort(
            $packages,
            static fn (array $left, array $right): int => [
                $right['changed_file_count'],
                $right['artifact_count'],
                $right['run_id'],
            ] <=> [
                $left['changed_file_count'],
                $left['artifact_count'],
                $left['run_id'],
            ],
        );

        return array_slice($packages, 0, 8);
    }

    /**
     * Count changed-file statuses for one review package.
     *
     * @param  list<array{path:string,status:string}>  $changedFiles
     * @return array<string, int>
     */
    protected function countChangedFileStatuses(array $changedFiles): array
    {
        $counts = [];

        foreach ($changedFiles as $file) {
            $counts[$file['status']] = $this->intMetric($counts[$file['status']] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Count artifact types for one review package.
     *
     * @param  Collection<int, AutoCodingRunArtifact>  $artifacts
     * @return array<string, int>
     */
    protected function countArtifactTypes(Collection $artifacts): array
    {
        $counts = [];

        foreach ($artifacts as $artifact) {
            $counts[$artifact->type] = $this->intMetric($counts[$artifact->type] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Summarize failed task and run outcomes for debugging.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildFailureSummary(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->where('created_at', '>=', $windowStart)
            ->where('status', AutoCodingExecutionStatus::Failed->value)
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();
        $categories = [];

        foreach ($tasks as $task) {
            $category = $this->resolveFailureCategory($task->latest_report);
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        return [
            'total' => $tasks->count(),
            'categories' => $categories,
            'recent' => $tasks
                ->take(8)
                ->map(fn (AutoCodingTask $task): array => [
                    'id' => $task->id,
                    'summary' => $task->summary,
                    'issue_key' => $task->issue_key,
                    'category' => $this->resolveFailureCategory($task->latest_report),
                    'message' => $this->resolveFailureMessage($task->latest_report),
                    'updated_at' => $this->formatTimestamp($task->updated_at),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Summarize validation outcomes from recent runs.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildValidationSummary(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with('task')
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('validation_results')
            ->orderByDesc('id')
            ->limit(30)
            ->get();
        $statusCounts = [
            'passed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'unknown' => 0,
        ];
        $recentFailures = [];

        foreach ($runs as $run) {
            $status = $this->resolveValidationStatus($run->validation_results);
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if ($status === 'failed') {
                $recentFailures[] = [
                    'run_id' => $run->id,
                    'task_id' => $run->task_id,
                    'task_summary' => $run->task?->summary,
                    'message' => $this->resolveValidationMessage($run->validation_results),
                    'completed_at' => $this->formatTimestamp($run->completed_at),
                ];
            }
        }

        return [
            'total' => $runs->count(),
            'statuses' => $statusCounts,
            'recent_failures' => array_slice($recentFailures, 0, 8),
        ];
    }

    /**
     * Summarize repeated validation and workflow-step error messages.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildErrorSummary(CarbonInterface $windowStart, array $filters): array
    {
        $messages = [];

        /** @var Collection<int, AutoCodingTaskRun> $validationRuns */
        $validationRuns = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->where('created_at', '>=', $windowStart)
            ->whereNotNull('validation_results')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'validation_results']);

        foreach ($validationRuns as $run) {
            if ($this->resolveValidationStatus($run->validation_results) !== 'failed') {
                continue;
            }

            $message = $this->resolveValidationMessage($run->validation_results);
            $this->appendErrorMessage($messages, 'validation', $message);
        }

        /** @var Collection<int, AutoCodingTaskRunStep> $steps */
        $steps = $this->applyStepFilters(AutoCodingTaskRunStep::query(), $filters)
            ->where('created_at', '>=', $windowStart)
            ->whereIn('status', [
                AutoCodingWorkflowStepStatus::Failed->value,
                AutoCodingWorkflowStepStatus::Blocked->value,
            ])
            ->whereNotNull('error_message')
            ->orderByDesc('id')
            ->limit(80)
            ->get(['id', 'step_key', 'status', 'error_message']);

        foreach ($steps as $step) {
            $this->appendErrorMessage($messages, $step->step_key->value, $step->error_message);
        }

        uasort(
            $messages,
            static fn (array $left, array $right): int => ($right['count'] ?? 0) <=> ($left['count'] ?? 0),
        );

        return [
            'total' => array_sum(array_map(
                fn (array $message): int => $this->intMetric($message['count'] ?? 0),
                $messages,
            )),
            'messages' => array_values(array_slice($messages, 0, 8, true)),
        ];
    }

    /**
     * Append one normalized error message into an aggregate map.
     *
     * @param  array<string, array<string, mixed>>  $messages
     * @param  string  $source
     * @param  string|null  $message
     * @return void
     */
    protected function appendErrorMessage(array &$messages, string $source, ?string $message): void
    {
        $normalizedMessage = $this->normalizeErrorMessage($message);

        if ($normalizedMessage === null) {
            return;
        }

        $key = $source.'|'.$normalizedMessage;
        $messages[$key] ??= [
            'source' => $source,
            'message' => $normalizedMessage,
            'count' => 0,
        ];
        $messages[$key]['count'] = $this->intMetric($messages[$key]['count'] ?? 0) + 1;
    }

    /**
     * Build workflow-step logs for failure investigation.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildExecutionLogs(array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRunStep> $steps */
        $steps = $this->applyStepFilters(AutoCodingTaskRunStep::query(), $filters)
            ->with('run.task')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        /** @var list<array<string, mixed>> $entries */
        $entries = $steps
            ->map(fn (AutoCodingTaskRunStep $step): array => [
                'id' => $step->id,
                'task_run_id' => $step->task_run_id,
                'task_id' => $step->run?->task_id,
                'task_summary' => $step->run?->task?->summary,
                'step_key' => $step->step_key->value,
                'sequence' => $step->sequence,
                'attempt' => $step->attempt,
                'status' => $step->status->value,
                'is_retryable' => $step->is_retryable,
                'error_message' => $step->error_message,
                'started_at' => $this->formatTimestamp($step->started_at),
                'completed_at' => $this->formatTimestamp($step->completed_at),
            ])
            ->values()
            ->all();

        return $entries;
    }

    /**
     * Summarize workflow-step execution outcomes for the selected window.
     *
     * @param  CarbonInterface  $windowStart
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return array<string, mixed>
     */
    protected function buildExecutionSummary(CarbonInterface $windowStart, array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRunStep> $steps */
        $steps = $this->applyStepFilters(AutoCodingTaskRunStep::query(), $filters)
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'step_key', 'status', 'is_retryable', 'attempt']);
        $statusCounts = $this->emptyWorkflowStepStatusCounts();
        $stepFailureCounts = [];
        $retryableCount = 0;
        $maxAttempt = 0;

        foreach ($steps as $step) {
            $statusCounts[$step->status->value] = $this->intMetric($statusCounts[$step->status->value] ?? 0) + 1;
            $retryableCount += $step->is_retryable ? 1 : 0;
            $maxAttempt = max($maxAttempt, $this->intMetric($step->attempt));

            if (! in_array($step->status, [AutoCodingWorkflowStepStatus::Failed, AutoCodingWorkflowStepStatus::Blocked], true)) {
                continue;
            }

            $stepKey = $step->step_key->value;
            $stepFailureCounts[$stepKey] = ($stepFailureCounts[$stepKey] ?? 0) + 1;
        }

        arsort($stepFailureCounts);

        return [
            'total_steps' => $steps->count(),
            'retryable_steps' => $retryableCount,
            'max_attempt' => $maxAttempt,
            'status_counts' => $statusCounts,
            'failed_or_blocked_steps' => array_slice($stepFailureCounts, 0, 6, true),
        ];
    }

    /**
     * Build empty workflow-step status counters.
     *
     * @return array<string, int>
     */
    protected function emptyWorkflowStepStatusCounts(): array
    {
        return [
            AutoCodingWorkflowStepStatus::Running->value => 0,
            AutoCodingWorkflowStepStatus::Completed->value => 0,
            AutoCodingWorkflowStepStatus::Failed->value => 0,
            AutoCodingWorkflowStepStatus::Blocked->value => 0,
            AutoCodingWorkflowStepStatus::Skipped->value => 0,
        ];
    }

    /**
     * Build one unified recent operational activity timeline.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildActivityTimeline(array $filters): array
    {
        $events = array_merge(
            $this->buildTaskTimelineEvents($filters),
            $this->buildRunTimelineEvents($filters),
            $this->buildArtifactTimelineEvents($filters),
            $this->buildStepTimelineEvents($filters),
        );

        usort(
            $events,
            static fn (array $left, array $right): int => ($right['sort_at'] ?? 0) <=> ($left['sort_at'] ?? 0),
        );

        return array_map(
            static function (array $event): array {
                unset($event['sort_at']);

                return $event;
            },
            array_slice($events, 0, 20),
        );
    }

    /**
     * Build task lifecycle entries for the timeline.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildTaskTimelineEvents(array $filters): array
    {
        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->with('assignedMachine')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        /** @var list<array<string, mixed>> $events */
        $events = $tasks
            ->map(fn (AutoCodingTask $task): array => [
                'type' => 'task',
                'tone' => $task->status->value,
                'title' => $task->summary,
                'message' => 'Task '.$task->status->value,
                'task_id' => $task->id,
                'run_id' => null,
                'machine_key' => $task->assignedMachine?->machine_key,
                'occurred_at' => $this->formatTimestamp($task->updated_at),
                'sort_at' => $this->sortTimestamp($task->updated_at),
            ])
            ->values()
            ->all();

        return $events;
    }

    /**
     * Build run lifecycle entries for the timeline.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildRunTimelineEvents(array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRun> $runs */
        $runs = $this->applyRunFilters(AutoCodingTaskRun::query(), $filters)
            ->with(['task', 'machine'])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        /** @var list<array<string, mixed>> $events */
        $events = $runs
            ->map(fn (AutoCodingTaskRun $run): array => [
                'type' => 'run',
                'tone' => $run->status->value,
                'title' => $run->task instanceof AutoCodingTask ? $run->task->summary : 'Run #'.$run->id,
                'message' => 'Run '.$run->status->value,
                'task_id' => $run->task_id,
                'run_id' => $run->id,
                'machine_key' => $run->machine?->machine_key,
                'occurred_at' => $this->formatTimestamp($run->completed_at ?? $run->updated_at),
                'sort_at' => $this->sortTimestamp($run->completed_at ?? $run->updated_at),
            ])
            ->values()
            ->all();

        return $events;
    }

    /**
     * Build artifact entries for the timeline.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildArtifactTimelineEvents(array $filters): array
    {
        /** @var Collection<int, AutoCodingRunArtifact> $artifacts */
        $artifacts = $this->applyArtifactFilters(AutoCodingRunArtifact::query(), $filters)
            ->with('taskRun.task')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        /** @var list<array<string, mixed>> $events */
        $events = $artifacts
            ->map(fn (AutoCodingRunArtifact $artifact): array => [
                'type' => 'artifact',
                'tone' => 'completed',
                'title' => $artifact->label,
                'message' => 'Artifact '.$artifact->type,
                'task_id' => $artifact->taskRun?->task_id,
                'run_id' => $artifact->task_run_id,
                'machine_key' => null,
                'occurred_at' => $this->formatTimestamp($artifact->created_at),
                'sort_at' => $this->sortTimestamp($artifact->created_at),
            ])
            ->values()
            ->all();

        return $events;
    }

    /**
     * Build workflow-step entries for the timeline.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildStepTimelineEvents(array $filters): array
    {
        /** @var Collection<int, AutoCodingTaskRunStep> $steps */
        $steps = $this->applyStepFilters(AutoCodingTaskRunStep::query(), $filters)
            ->with('run.task')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        /** @var list<array<string, mixed>> $events */
        $events = $steps
            ->map(fn (AutoCodingTaskRunStep $step): array => [
                'type' => 'step',
                'tone' => $step->status->value,
                'title' => $step->step_key->value,
                'message' => $step->error_message ?? 'Workflow step '.$step->status->value,
                'task_id' => $step->run?->task_id,
                'run_id' => $step->task_run_id,
                'machine_key' => null,
                'occurred_at' => $this->formatTimestamp($step->completed_at ?? $step->updated_at),
                'sort_at' => $this->sortTimestamp($step->completed_at ?? $step->updated_at),
            ])
            ->values()
            ->all();

        return $events;
    }

    /**
     * Build important operational notification candidates.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildNotifications(array $filters): array
    {
        $notifications = array_merge(
            $this->buildTaskNotifications($filters),
            $this->buildMachineNotifications($filters),
            $this->buildMachineResourceNotifications($filters),
        );

        usort(
            $notifications,
            fn (array $left, array $right): int => [
                $this->notificationSeverityRank($right['severity'] ?? null),
                $this->sortTimestampFromString($right['created_at'] ?? null),
            ] <=> [
                $this->notificationSeverityRank($left['severity'] ?? null),
                $this->sortTimestampFromString($left['created_at'] ?? null),
            ],
        );

        return array_slice($notifications, 0, 12);
    }

    /**
     * Build aggregate notification counters for quick operator review.
     *
     * @param  list<array<string, mixed>>  $notifications
     * @return array<string, mixed>
     */
    protected function buildNotificationSummary(array $notifications): array
    {
        $severityCounts = [
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'unknown' => 0,
        ];
        $typeCounts = [];
        $latestCritical = null;

        foreach ($notifications as $notification) {
            $severity = $this->stringMetric($notification['severity'] ?? null, 'unknown');
            $type = $this->stringMetric($notification['type'] ?? null, 'unknown');
            $severityCounts[$severity] = $this->intMetric($severityCounts[$severity] ?? 0) + 1;
            $typeCounts[$type] = $this->intMetric($typeCounts[$type] ?? 0) + 1;

            if ($latestCritical === null && $severity === 'critical') {
                $latestCritical = [
                    'type' => $type,
                    'title' => $this->stringMetric($notification['title'] ?? null, 'Critical notification'),
                    'message' => $this->stringMetric($notification['message'] ?? null, ''),
                    'created_at' => $notification['created_at'] ?? null,
                ];
            }
        }

        arsort($typeCounts);

        return [
            'total' => count($notifications),
            'severity_counts' => $severityCounts,
            'type_counts' => array_slice($typeCounts, 0, 8, true),
            'latest_critical' => $latestCritical,
        ];
    }

    /**
     * Resolve notification severity ordering.
     *
     * @param  mixed  $severity
     * @return int
     */
    protected function notificationSeverityRank(mixed $severity): int
    {
        return match ($severity) {
            'critical' => 3,
            'warning' => 2,
            'info' => 1,
            default => 0,
        };
    }

    /**
     * Build task event notification candidates.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildTaskNotifications(array $filters): array
    {
        /** @var Collection<int, AutoCodingTask> $tasks */
        $tasks = $this->applyTaskFilters(AutoCodingTask::query(), $filters)
            ->whereIn('status', [
                AutoCodingExecutionStatus::Failed->value,
                AutoCodingExecutionStatus::Blocked->value,
                AutoCodingExecutionStatus::Running->value,
            ])
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        /** @var list<array<string, mixed>> $entries */
        $entries = $tasks
            ->map(fn (AutoCodingTask $task): array => [
                'type' => 'task_'.$task->status->value,
                'severity' => $task->status === AutoCodingExecutionStatus::Failed ? 'critical' : 'warning',
                'title' => $task->summary,
                'message' => $this->notificationMessageForTask($task),
                'task_id' => $task->id,
                'created_at' => $this->formatTimestamp($task->updated_at),
            ])
            ->values()
            ->all();

        return $entries;
    }

    /**
     * Build machine resource pressure notification candidates.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildMachineResourceNotifications(array $filters): array
    {
        /** @var Collection<int, AutoCodingMachine> $machines */
        $machines = $this->applyMachineFilters(AutoCodingMachine::query(), $filters)
            ->orderByDesc('last_seen_at')
            ->get();
        $entries = [];

        foreach ($machines as $machine) {
            foreach ($this->resourceAlertsForMachine($machine) as $alert) {
                $entries[] = $alert;
            }
        }

        return array_slice($entries, 0, 8);
    }

    /**
     * Resolve resource alerts for one machine snapshot.
     *
     * @param  AutoCodingMachine  $machine
     * @return list<array<string, mixed>>
     */
    protected function resourceAlertsForMachine(AutoCodingMachine $machine): array
    {
        $resources = $this->extractResourceMetrics($machine->metadata);
        $alerts = [];

        foreach ($this->resourceAlertThresholds() as $metric => $threshold) {
            $value = $resources[$metric] ?? null;

            if (! is_numeric($value) || $value < $threshold) {
                continue;
            }

            $numericValue = $value + 0;

            $alerts[] = [
                'type' => 'machine_resource_'.$metric,
                'severity' => $numericValue >= 95 ? 'critical' : 'warning',
                'title' => $machine->machine_key,
                'message' => sprintf(
                    '%s is %s%%.',
                    str_replace('_', ' ', $metric),
                    $this->formatNumericMetric($numericValue),
                ),
                'machine_id' => $machine->id,
                'created_at' => $this->formatTimestamp($machine->last_seen_at),
            ];
        }

        return $alerts;
    }

    /**
     * Return resource alert thresholds for percentage metrics.
     *
     * @return array<string, int>
     */
    protected function resourceAlertThresholds(): array
    {
        return [
            'cpu_percent' => 90,
            'memory_percent' => 90,
            'disk_percent' => 90,
        ];
    }

    /**
     * Build machine health notification candidates.
     *
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    protected function buildMachineNotifications(array $filters): array
    {
        /** @var Collection<int, AutoCodingMachine> $machines */
        $machines = $this->applyMachineFilters(AutoCodingMachine::query(), $filters)
            ->orderByDesc('last_seen_at')
            ->get();

        /** @var list<array<string, mixed>> $entries */
        $entries = $machines
            ->filter(fn (AutoCodingMachine $machine): bool => $this->resolveMachineStatus($machine) !== 'online')
            ->map(fn (AutoCodingMachine $machine): array => [
                'type' => 'machine_'.$this->resolveMachineStatus($machine),
                'severity' => 'warning',
                'title' => $machine->machine_key,
                'message' => sprintf('Machine is %s.', $this->resolveMachineStatus($machine)),
                'machine_id' => $machine->id,
                'created_at' => $this->formatTimestamp($machine->last_seen_at),
            ])
            ->values()
            ->all();

        return $entries;
    }

    /**
     * Resolve a task notification message.
     *
     * @param  AutoCodingTask  $task
     * @return string
     */
    protected function notificationMessageForTask(AutoCodingTask $task): string
    {
        return match ($task->status) {
            AutoCodingExecutionStatus::Failed => 'Task failed and needs review.',
            AutoCodingExecutionStatus::Blocked => 'Task is waiting for follow-up input.',
            AutoCodingExecutionStatus::Running => 'Task is currently executing.',
            default => 'Task status changed.',
        };
    }

    /**
     * Normalize report filters before applying them to queries.
     *
     * @param  array{repository_path?:string|null,machine_key?:string|null}  $filters
     * @return array{repository_path:string|null,machine_key:string|null}
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'repository_path' => $this->nonEmptyFilter($filters['repository_path'] ?? null),
            'machine_key' => $this->nonEmptyFilter($filters['machine_key'] ?? null),
        ];
    }

    /**
     * Normalize one string filter value.
     *
     * @param  mixed  $value
     * @return string|null
     */
    protected function nonEmptyFilter(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Apply observability filters to a task query.
     *
     * @param  Builder<AutoCodingTask>  $query
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return Builder<AutoCodingTask>
     */
    protected function applyTaskFilters(Builder $query, array $filters): Builder
    {
        if ($filters['repository_path'] !== null) {
            $query->where('repository_path', $filters['repository_path']);
        }

        if ($filters['machine_key'] !== null) {
            $query->where(function (Builder $taskQuery) use ($filters): void {
                $taskQuery
                    ->whereHas(
                        'assignedMachine',
                        fn (Builder $machineQuery): Builder => $machineQuery
                            ->where('machine_key', $filters['machine_key'])
                    )
                    ->orWhereHas(
                        'runs.machine',
                        fn (Builder $machineQuery): Builder => $machineQuery
                            ->where('machine_key', $filters['machine_key'])
                    );
            });
        }

        return $query;
    }

    /**
     * Apply observability filters to a run query.
     *
     * @param  Builder<AutoCodingTaskRun>  $query
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return Builder<AutoCodingTaskRun>
     */
    protected function applyRunFilters(Builder $query, array $filters): Builder
    {
        if ($filters['repository_path'] !== null) {
            $query->whereHas(
                'task',
                fn (Builder $taskQuery): Builder => $taskQuery->where('repository_path', $filters['repository_path'])
            );
        }

        if ($filters['machine_key'] !== null) {
            $query->whereHas(
                'machine',
                fn (Builder $machineQuery): Builder => $machineQuery->where('machine_key', $filters['machine_key'])
            );
        }

        return $query;
    }

    /**
     * Apply observability filters to a machine query.
     *
     * @param  Builder<AutoCodingMachine>  $query
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return Builder<AutoCodingMachine>
     */
    protected function applyMachineFilters(Builder $query, array $filters): Builder
    {
        if ($filters['repository_path'] !== null) {
            $query->where('repository_path', $filters['repository_path']);
        }

        if ($filters['machine_key'] !== null) {
            $query->where('machine_key', $filters['machine_key']);
        }

        return $query;
    }

    /**
     * Apply observability filters to an artifact query.
     *
     * @param  Builder<AutoCodingRunArtifact>  $query
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return Builder<AutoCodingRunArtifact>
     */
    protected function applyArtifactFilters(Builder $query, array $filters): Builder
    {
        if ($filters['repository_path'] !== null) {
            $query->whereHas(
                'taskRun.task',
                fn (Builder $taskQuery): Builder => $taskQuery->where('repository_path', $filters['repository_path'])
            );
        }

        if ($filters['machine_key'] !== null) {
            $query->whereHas(
                'taskRun.machine',
                fn (Builder $machineQuery): Builder => $machineQuery->where('machine_key', $filters['machine_key'])
            );
        }

        return $query;
    }

    /**
     * Apply observability filters to a workflow-step query.
     *
     * @param  Builder<AutoCodingTaskRunStep>  $query
     * @param  array{repository_path:string|null,machine_key:string|null}  $filters
     * @return Builder<AutoCodingTaskRunStep>
     */
    protected function applyStepFilters(Builder $query, array $filters): Builder
    {
        if ($filters['repository_path'] !== null) {
            $query->whereHas(
                'run.task',
                fn (Builder $taskQuery): Builder => $taskQuery->where('repository_path', $filters['repository_path'])
            );
        }

        if ($filters['machine_key'] !== null) {
            $query->whereHas(
                'run.machine',
                fn (Builder $machineQuery): Builder => $machineQuery->where('machine_key', $filters['machine_key'])
            );
        }

        return $query;
    }

    /**
     * Resolve one failure category from a task report.
     *
     * @param  array<string, mixed>|null  $report
     * @return string
     */
    protected function resolveFailureCategory(?array $report): string
    {
        $failure = is_array($report['failure'] ?? null) ? $report['failure'] : [];

        foreach (['category', 'type', 'code'] as $key) {
            if (is_string($failure[$key] ?? null) && trim($failure[$key]) !== '') {
                return trim($failure[$key]);
            }
        }

        return 'unknown';
    }

    /**
     * Resolve one failure message from a task report.
     *
     * @param  array<string, mixed>|null  $report
     * @return string|null
     */
    protected function resolveFailureMessage(?array $report): ?string
    {
        $failure = is_array($report['failure'] ?? null) ? $report['failure'] : [];

        foreach (['message', 'summary', 'error'] as $key) {
            if (is_string($failure[$key] ?? null) && trim($failure[$key]) !== '') {
                return trim($failure[$key]);
            }
        }

        return is_string($report['error'] ?? null) && trim($report['error']) !== ''
            ? trim($report['error'])
            : null;
    }

    /**
     * Resolve validation status from stored validation results.
     *
     * @param  array<string, mixed>|null  $validationResults
     * @return string
     */
    protected function resolveValidationStatus(?array $validationResults): string
    {
        if ($validationResults === null) {
            return 'unknown';
        }

        $status = $validationResults['status'] ?? $validationResults['overall_status'] ?? null;

        if (is_string($status) && in_array($status, ['passed', 'failed', 'skipped'], true)) {
            return $status;
        }

        if (($validationResults['passed'] ?? null) === true) {
            return 'passed';
        }

        if (($validationResults['failed'] ?? null) === true || is_array($validationResults['failures'] ?? null)) {
            return 'failed';
        }

        return 'unknown';
    }

    /**
     * Resolve a compact validation failure message.
     *
     * @param  array<string, mixed>|null  $validationResults
     * @return string|null
     */
    protected function resolveValidationMessage(?array $validationResults): ?string
    {
        if ($validationResults === null) {
            return null;
        }

        foreach (['message', 'summary', 'error'] as $key) {
            if (is_string($validationResults[$key] ?? null) && trim($validationResults[$key]) !== '') {
                return trim($validationResults[$key]);
            }
        }

        $failures = is_array($validationResults['failures'] ?? null) ? $validationResults['failures'] : [];
        $firstFailure = $failures[0] ?? null;

        return is_array($firstFailure) && is_string($firstFailure['message'] ?? null)
            ? trim($firstFailure['message'])
            : null;
    }

    /**
     * Normalize one error message for repeated-error aggregation.
     *
     * @param  string|null  $message
     * @return string|null
     */
    protected function normalizeErrorMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $normalizedMessage = preg_replace('/\s+/', ' ', trim($message));

        if (! is_string($normalizedMessage) || $normalizedMessage === '') {
            return null;
        }

        return mb_substr($normalizedMessage, 0, 180);
    }

    /**
     * Resolve current machine status from heartbeat freshness.
     *
     * @param  AutoCodingMachine  $machine
     * @return string
     */
    protected function resolveMachineStatus(AutoCodingMachine $machine): string
    {
        if ($machine->last_seen_at === null) {
            return 'unknown';
        }

        if ($machine->availability_status === 'offline') {
            return 'offline';
        }

        $staleSeconds = config('opas.auto_coding.machine_stale_seconds');
        $threshold = is_numeric($staleSeconds) && (int) $staleSeconds > 0 ? (int) $staleSeconds : 300;

        return $machine->last_seen_at->diffInSeconds(now()) <= $threshold ? 'online' : 'stale';
    }

    /**
     * Extract resource metrics from machine metadata.
     *
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, int|float|string|null>
     */
    protected function extractResourceMetrics(?array $metadata): array
    {
        $metadataValues = $metadata ?? [];
        $resources = is_array($metadataValues['resources'] ?? null) ? $metadataValues['resources'] : $metadataValues;

        return [
            'cpu_percent' => $this->numericMetric($resources['cpu_percent'] ?? $resources['cpu'] ?? null),
            'memory_percent' => $this->numericMetric($resources['memory_percent'] ?? $resources['memory'] ?? null),
            'disk_percent' => $this->numericMetric($resources['disk_percent'] ?? $resources['disk'] ?? null),
            'load_average' => $this->numericMetric($resources['load_average'] ?? $resources['load'] ?? null),
            'process_memory_mb' => $this->numericMetric($resources['process_memory_mb'] ?? null),
            'process_peak_memory_mb' => $this->numericMetric($resources['process_peak_memory_mb'] ?? null),
            'disk_free_mb' => $this->numericMetric($resources['disk_free_mb'] ?? null),
            'disk_total_mb' => $this->numericMetric($resources['disk_total_mb'] ?? null),
            'php_version' => is_string($metadataValues['php_version'] ?? null) ? $metadataValues['php_version'] : null,
        ];
    }

    /**
     * Normalize one optional numeric metric.
     *
     * @param  mixed  $value
     * @return int|float|null
     */
    protected function numericMetric(mixed $value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Format one numeric metric for operator-facing alert messages.
     *
     * @param  int|float  $value
     * @return string
     */
    protected function formatNumericMetric(int|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * Extract normalized token usage from provider output.
     *
     * @param  array<string, mixed>|null  $providerResult
     * @return array{prompt_tokens:int,completion_tokens:int,total_tokens:int}
     */
    protected function extractTokenUsage(?array $providerResult): array
    {
        $usage = is_array($providerResult['usage'] ?? null) ? $providerResult['usage'] : $providerResult ?? [];

        return [
            'prompt_tokens' => $this->intMetric($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null),
            'completion_tokens' => $this->intMetric($usage['completion_tokens'] ?? $usage['output_tokens'] ?? null),
            'total_tokens' => $this->intMetric($usage['total_tokens'] ?? null),
        ];
    }

    /**
     * Normalize one optional integer metric.
     *
     * @param  mixed  $value
     * @return int
     */
    protected function intMetric(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * Calculate a rounded percentage from one numerator and denominator.
     *
     * @param  int  $numerator
     * @param  int  $denominator
     * @return int
     */
    protected function ratePercent(int $numerator, int $denominator): int
    {
        return $denominator > 0
            ? min(100, max(0, (int) round(($numerator / $denominator) * 100)))
            : 0;
    }

    /**
     * Normalize one string metric bucket.
     *
     * @param  mixed  $value
     * @param  string  $fallback
     * @return string
     */
    protected function stringMetric(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : $fallback;
    }

    /**
     * Resolve provider name from provider output.
     *
     * @param  array<string, mixed>|null  $providerResult
     * @return string
     */
    protected function resolveProviderName(?array $providerResult): string
    {
        if ($providerResult === null) {
            return 'unknown';
        }

        foreach (['provider', 'provider_name', 'source'] as $key) {
            if (is_string($providerResult[$key] ?? null) && trim($providerResult[$key]) !== '') {
                return trim($providerResult[$key]);
            }
        }

        return 'unknown';
    }

    /**
     * Resolve model name from provider output.
     *
     * @param  array<string, mixed>|null  $providerResult
     * @return string
     */
    protected function resolveModelName(?array $providerResult): string
    {
        if ($providerResult === null) {
            return 'unknown';
        }

        foreach (['model', 'model_name'] as $key) {
            if (is_string($providerResult[$key] ?? null) && trim($providerResult[$key]) !== '') {
                return trim($providerResult[$key]);
            }
        }

        return 'unknown';
    }

    /**
     * Normalize changed-file payloads from one run.
     *
     * @param  mixed  $changedFiles
     * @return list<array{path:string,status:string}>
     */
    protected function normalizeChangedFiles(mixed $changedFiles): array
    {
        if (! is_array($changedFiles)) {
            return [];
        }

        $files = [];

        foreach ($changedFiles as $file) {
            if (! is_array($file) || ! is_string($file['path'] ?? null)) {
                continue;
            }

            $files[] = [
                'path' => $file['path'],
                'status' => is_string($file['status'] ?? null) ? $file['status'] : 'modified',
            ];
        }

        return $files;
    }

    /**
     * Resolve a compact file extension bucket for changed-file summaries.
     *
     * @param  string  $path
     * @return string
     */
    protected function resolveFileExtension(string $path): string
    {
        $extension = trim(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== ''
            ? strtolower($extension)
            : 'none';
    }

    /**
     * Return a duration in seconds when both timestamps are available.
     *
     * @param  CarbonInterface|null  $startedAt
     * @param  CarbonInterface|null  $completedAt
     * @return int|null
     */
    protected function durationSeconds(?CarbonInterface $startedAt, ?CarbonInterface $completedAt): ?int
    {
        return $startedAt instanceof CarbonInterface && $completedAt instanceof CarbonInterface
            ? max(0, (int) $startedAt->diffInSeconds($completedAt))
            : null;
    }

    /**
     * Format one timestamp for JSON contracts.
     *
     * @param  CarbonInterface|null  $timestamp
     * @return string|null
     */
    protected function formatTimestamp(?CarbonInterface $timestamp): ?string
    {
        return $timestamp instanceof CarbonInterface ? $timestamp->toIso8601String() : null;
    }

    /**
     * Resolve the date bucket for one model timestamp.
     *
     * @param  CarbonInterface|null  $timestamp
     * @return string|null
     */
    protected function bucketDate(?CarbonInterface $timestamp): ?string
    {
        return $timestamp instanceof CarbonInterface ? $timestamp->toDateString() : null;
    }

    /**
     * Return a non-negative age in minutes for queue aging metrics.
     *
     * @param  CarbonInterface|null  $timestamp
     * @return int
     */
    protected function ageMinutes(?CarbonInterface $timestamp): int
    {
        return $timestamp instanceof CarbonInterface
            ? max(0, (int) abs($timestamp->diffInMinutes(now())))
            : 0;
    }

    /**
     * Convert an optional timestamp into a sortable integer.
     *
     * @param  CarbonInterface|null  $timestamp
     * @return int
     */
    protected function sortTimestamp(?CarbonInterface $timestamp): int
    {
        return $timestamp instanceof CarbonInterface ? $timestamp->getTimestamp() : 0;
    }

    /**
     * Convert one ISO timestamp string into a sortable integer.
     *
     * @param  mixed  $timestamp
     * @return int
     */
    protected function sortTimestampFromString(mixed $timestamp): int
    {
        if (! is_string($timestamp) || trim($timestamp) === '') {
            return 0;
        }

        $parsedTimestamp = strtotime($timestamp);

        return $parsedTimestamp === false ? 0 : $parsedTimestamp;
    }
}
