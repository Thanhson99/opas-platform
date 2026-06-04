<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Api;

use App\Enums\AutoCodingExecutionStatus;
use App\Enums\AutoCodingWorkflowStep;
use App\Enums\AutoCodingWorkflowStepStatus;
use App\Models\AutoCodingMachine;
use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminAutoCodingObservabilityApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admins can inspect centralized auto-coding operational visibility.
     *
     * @return void
     */
    public function test_admin_can_show_auto_coding_observability_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-04 10:00:00'));
        $this->beforeApplicationDestroyed(static function (): void {
            Carbon::setTestNow();
        });

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $machine = AutoCodingMachine::query()->create([
            'machine_key' => 'mac-studio-1',
            'hostname' => 'mac-studio',
            'operating_system' => 'Darwin',
            'availability_status' => 'idle',
            'repository_path' => '/work/opas',
            'capabilities' => ['codex' => true],
            'workspace_bindings' => [
                ['repository_path' => '/work/opas', 'workspace_path' => '/work/opas'],
            ],
            'max_parallel_tasks' => 2,
            'metadata' => [
                'php_version' => '8.3.0',
                'resources' => [
                    'cpu_percent' => 12.5,
                    'memory_percent' => 44,
                    'disk_percent' => 91,
                    'process_memory_mb' => 128.5,
                    'process_peak_memory_mb' => 256.25,
                    'disk_free_mb' => 2048,
                    'disk_total_mb' => 32768,
                ],
            ],
            'last_seen_at' => now(),
        ]);
        $task = AutoCodingTask::query()->create([
            'summary' => 'Build operational visibility',
            'issue_key' => 'OPAS-0074',
            'repository_path' => '/work/opas',
            'branch_name' => 'feature/observability',
            'assigned_machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [],
            'completed_at' => now(),
        ]);
        $pendingTask = AutoCodingTask::query()->create([
            'summary' => 'Waiting for a free machine',
            'issue_key' => 'OPAS-PENDING',
            'repository_path' => '/work/opas',
            'assigned_machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Pending,
            'context_payload' => [],
            'latest_report' => [],
        ]);
        $pendingTask->forceFill([
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ])->save();
        $run = AutoCodingTaskRun::query()->create([
            'task_id' => $task->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Completed,
            'repository_snapshot' => ['branch' => 'feature/observability'],
            'changed_files' => [
                ['path' => 'app/Services/AutoCoding/AutoCodingObservabilityService.php', 'status' => 'modified'],
            ],
            'provider_result' => [
                'provider' => 'codex',
                'model' => 'gpt-5',
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 40,
                    'total_tokens' => 140,
                ],
            ],
            'validation_results' => ['status' => 'passed'],
            'final_report' => ['summary' => ['artifact_count' => 1]],
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
        $run->artifacts()->create([
            'type' => 'final_report',
            'label' => 'Final report',
            'payload' => ['status' => 'passed'],
        ]);
        $run->steps()->create([
            'step_key' => AutoCodingWorkflowStep::ProviderPlan,
            'sequence' => 1,
            'attempt' => 1,
            'status' => AutoCodingWorkflowStepStatus::Completed,
            'is_retryable' => false,
            'input_payload' => [],
            'output_payload' => [],
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
        ]);
        $failedTask = AutoCodingTask::query()->create([
            'summary' => 'Fix failing validation',
            'issue_key' => 'OPAS-FAIL',
            'repository_path' => '/work/opas',
            'assigned_machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Failed,
            'context_payload' => [],
            'latest_report' => [
                'failure' => [
                    'category' => 'validation_failed',
                    'message' => 'PHPStan found one error.',
                ],
            ],
            'completed_at' => now(),
        ]);
        $failedRun = AutoCodingTaskRun::query()->create([
            'task_id' => $failedTask->id,
            'machine_id' => $machine->id,
            'status' => AutoCodingExecutionStatus::Failed,
            'repository_snapshot' => ['branch' => 'feature/observability'],
            'changed_files' => [],
            'provider_result' => ['provider' => 'codex', 'model' => 'gpt-5'],
            'validation_results' => [
                'status' => 'failed',
                'message' => 'PHPStan failed.',
            ],
            'final_report' => [],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        $failedRun->steps()->create([
            'step_key' => AutoCodingWorkflowStep::RunValidation,
            'sequence' => 2,
            'attempt' => 2,
            'status' => AutoCodingWorkflowStepStatus::Failed,
            'is_retryable' => true,
            'input_payload' => [],
            'output_payload' => [],
            'error_message' => 'PHPStan failed.',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/auto-coding/observability?days=7');

        $response->assertOk()
            ->assertJsonPath('data.summary.tasks_total', 3)
            ->assertJsonPath('data.filters.repository_path', null)
            ->assertJsonPath('data.filters.machine_key', null)
            ->assertJsonPath('data.filter_options.repository_paths.0', '/work/opas')
            ->assertJsonPath('data.filter_options.machines.0.machine_key', 'mac-studio-1')
            ->assertJsonPath('data.filter_options.machines.0.derived_status', 'online')
            ->assertJsonPath('data.summary.online_machines', 1)
            ->assertJsonPath('data.operational_summary.health', 'critical')
            ->assertJsonPath('data.operational_summary.warning_notifications', 1)
            ->assertJsonPath('data.operational_summary.failed_repositories', 1)
            ->assertJsonPath('data.operational_summary.validation_failures', 1)
            ->assertJsonPath('data.review_actions.0.type', 'failed_tasks')
            ->assertJsonPath('data.review_actions.0.priority', 'critical')
            ->assertJsonPath('data.ai_usage.providers.codex', 2)
            ->assertJsonPath('data.ai_usage.models.gpt-5', 2)
            ->assertJsonPath('data.ai_usage.tokens.total_tokens', 140)
            ->assertJsonPath('data.ai_usage.tokens.prompt_tokens', 100)
            ->assertJsonPath('data.ai_usage.tokens.completion_tokens', 40)
            ->assertJsonCount(7, 'data.daily_activity')
            ->assertJsonPath('data.daily_activity.6.tasks_created', 3)
            ->assertJsonPath('data.daily_activity.6.runs_created', 2)
            ->assertJsonPath('data.daily_activity.6.failed_runs', 1)
            ->assertJsonPath('data.repository_summary.0.repository_path', '/work/opas')
            ->assertJsonPath('data.repository_summary.0.task_count', 3)
            ->assertJsonPath('data.repository_summary.0.active_task_count', 1)
            ->assertJsonPath('data.repository_summary.0.failed_task_count', 1)
            ->assertJsonPath('data.repository_summary.0.run_count', 2)
            ->assertJsonPath('data.repository_summary.0.failed_run_count', 1)
            ->assertJsonPath('data.queue_health.active_count', 1)
            ->assertJsonPath('data.queue_health.status_counts.pending', 1)
            ->assertJsonPath('data.queue_health.oldest_age_minutes', 45)
            ->assertJsonPath('data.queue_health.average_age_minutes', 45)
            ->assertJsonPath('data.queue_health.oldest_tasks.0.summary', 'Waiting for a free machine')
            ->assertJsonPath('data.machine_health.0.resources.cpu_percent', 12.5)
            ->assertJsonPath('data.machine_health.0.resources.process_memory_mb', 128.5)
            ->assertJsonPath('data.machine_health.0.resources.disk_free_mb', 2048)
            ->assertJsonPath('data.resource_summary.reported_machines', 1)
            ->assertJsonPath('data.resource_summary.metrics.cpu_percent.max', 12.5)
            ->assertJsonPath('data.resource_summary.metrics.memory_percent.average', 44)
            ->assertJsonPath('data.resource_summary.metrics.disk_percent.max', 91)
            ->assertJsonPath('data.resource_summary.highest_pressure.0.metric', 'disk_percent')
            ->assertJsonPath('data.resource_summary.highest_pressure.0.machine_key', 'mac-studio-1')
            ->assertJsonPath('data.machine_health.0.capacity.max_parallel', 2)
            ->assertJsonPath('data.machine_health.0.capacity.available_slots', 2)
            ->assertJsonPath('data.machine_capacity.machine_count', 1)
            ->assertJsonPath('data.machine_capacity.available_slots', 2)
            ->assertJsonPath('data.machine_capacity.utilization_percent', 0)
            ->assertJsonPath('data.machine_fleet.machine_count', 1)
            ->assertJsonPath('data.machine_fleet.derived_status_counts.online', 1)
            ->assertJsonPath('data.machine_fleet.availability_counts.idle', 1)
            ->assertJsonPath('data.machine_fleet.operating_system_counts.Darwin', 1)
            ->assertJsonPath('data.machine_capabilities.machines_with_capabilities', 1)
            ->assertJsonPath('data.machine_capabilities.capabilities.codex.enabled', 1)
            ->assertJsonPath('data.machine_health.0.workspace_bindings.0.repository_path', '/work/opas')
            ->assertJsonPath('data.workspace_bindings.total_bindings', 1)
            ->assertJsonPath('data.workspace_bindings.repository_count', 1)
            ->assertJsonPath('data.workspace_bindings.repositories.0.repository_path', '/work/opas')
            ->assertJsonPath('data.workspace_bindings.repositories.0.machine_count', 1)
            ->assertJsonPath('data.changed_files.total', 1)
            ->assertJsonPath('data.changed_files.status_counts.modified', 1)
            ->assertJsonPath('data.changed_files.extension_counts.php', 1)
            ->assertJsonPath('data.changed_files.files.0.path', 'app/Services/AutoCoding/AutoCodingObservabilityService.php')
            ->assertJsonPath('data.artifacts.total', 1)
            ->assertJsonPath('data.artifacts.recent.0.type', 'final_report')
            ->assertJsonPath('data.review_packages.0.run_id', $run->id)
            ->assertJsonPath('data.review_packages.0.changed_file_count', 1)
            ->assertJsonPath('data.review_packages.0.artifact_count', 1)
            ->assertJsonPath('data.review_packages.0.changed_file_status_counts.modified', 1)
            ->assertJsonPath('data.review_packages.0.artifact_type_counts.final_report', 1)
            ->assertJsonPath('data.failure_summary.categories.validation_failed', 1)
            ->assertJsonPath('data.validation_summary.statuses.failed', 1)
            ->assertJsonPath('data.error_summary.total', 2)
            ->assertJsonFragment([
                'source' => 'validation',
                'message' => 'PHPStan failed.',
                'count' => 1,
            ])
            ->assertJsonFragment([
                'source' => AutoCodingWorkflowStep::RunValidation->value,
                'message' => 'PHPStan failed.',
                'count' => 1,
            ])
            ->assertJsonPath('data.notifications.0.type', 'task_failed')
            ->assertJsonPath('data.notifications.0.severity', 'critical')
            ->assertJsonPath('data.notification_summary.total', 2)
            ->assertJsonPath('data.notification_summary.severity_counts.critical', 1)
            ->assertJsonPath('data.notification_summary.severity_counts.warning', 1)
            ->assertJsonPath('data.notification_summary.type_counts.task_failed', 1)
            ->assertJsonPath('data.notification_summary.latest_critical.type', 'task_failed')
            ->assertJsonFragment(['type' => 'artifact'])
            ->assertJsonFragment(['type' => 'run'])
            ->assertJsonFragment(['type' => 'machine_resource_disk_percent'])
            ->assertJsonPath('data.execution_summary.total_steps', 2)
            ->assertJsonPath('data.execution_summary.retryable_steps', 1)
            ->assertJsonPath('data.execution_summary.max_attempt', 2)
            ->assertJsonPath('data.execution_summary.status_counts.failed', 1)
            ->assertJsonPath('data.execution_summary.failed_or_blocked_steps.run_validation', 1)
            ->assertJsonPath('data.execution_logs.0.step_key', AutoCodingWorkflowStep::RunValidation->value)
            ->assertJsonPath('data.recent_runs.0.provider', 'codex')
            ->assertJsonPath('data.run_performance.run_count', 2)
            ->assertJsonPath('data.run_performance.average_duration_seconds', 90)
            ->assertJsonPath('data.run_performance.max_duration_seconds', 120)
            ->assertJsonPath('data.run_performance.slowest_runs.0.id', $run->id)
            ->assertJsonPath('data.reliability_summary.run_count', 2)
            ->assertJsonPath('data.reliability_summary.status_counts.completed', 1)
            ->assertJsonPath('data.reliability_summary.status_counts.failed', 1)
            ->assertJsonPath('data.reliability_summary.success_rate_percent', 50)
            ->assertJsonPath('data.reliability_summary.failure_rate_percent', 50)
            ->assertJsonPath('data.reliability_summary.machines.0.name', 'mac-studio-1')
            ->assertJsonPath('data.reliability_summary.machines.0.failed', 1)
            ->assertJsonPath('data.reliability_summary.providers.0.name', 'codex')
            ->assertJsonPath('data.reliability_summary.providers.0.failed', 1)
            ->assertJsonPath('data.task_statuses.pending', 1)
            ->assertJsonPath('data.task_statuses.failed', 1);

        $filteredResponse = $this->actingAs($admin)->getJson(
            '/api/admin/auto-coding/observability?days=7&machine_key=mac-studio-1&repository_path=/work/opas'
        );

        $filteredResponse->assertOk()
            ->assertJsonPath('data.filters.repository_path', '/work/opas')
            ->assertJsonPath('data.filters.machine_key', 'mac-studio-1')
            ->assertJsonPath('data.summary.tasks_total', 3)
            ->assertJsonPath('data.queue_health.active_count', 1)
            ->assertJsonPath('data.ai_usage.providers.codex', 2);

        $emptyFilterResponse = $this->actingAs($admin)->getJson(
            '/api/admin/auto-coding/observability?machine_key=other-machine'
        );

        $emptyFilterResponse->assertOk()
            ->assertJsonPath('data.summary.tasks_total', 0)
            ->assertJsonPath('data.operational_summary.health', 'healthy')
            ->assertJsonPath('data.review_actions', [])
            ->assertJsonPath('data.queue_health.active_count', 0)
            ->assertJsonPath('data.ai_usage.run_count', 0)
            ->assertJsonPath('data.repository_summary', [])
            ->assertJsonPath('data.machine_health', []);
    }

    /**
     * Non-admin users cannot read operational auto-coding reports.
     *
     * @return void
     */
    public function test_non_admin_cannot_show_auto_coding_observability_report(): void
    {
        $user = User::factory()->create([
            'role' => 'member',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/admin/auto-coding/observability')
            ->assertForbidden();
    }
}
