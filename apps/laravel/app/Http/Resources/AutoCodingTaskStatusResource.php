<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoCodingTaskStatusResource extends JsonResource
{
    /**
     * Transform one local auto-coding task into a compact polling contract.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AutoCodingTask $task */
        $task = $this->resource;
        /** @var AutoCodingTaskRun|null $latestRun */
        $latestRun = $task->runs->sortByDesc('id')->first();
        /** @var array<string, mixed>|null $latestReport */
        $latestReport = is_array($task->latest_report) ? $task->latest_report : null;
        /** @var array<string, mixed>|null $summary */
        $summary = is_array($latestReport['summary'] ?? null) ? $latestReport['summary'] : null;
        /** @var array<string, mixed>|null $validation */
        $validation = is_array($latestReport['validation'] ?? null) ? $latestReport['validation'] : null;
        /** @var array<string, mixed>|null $provider */
        $provider = is_array($latestReport['provider_result'] ?? null) ? $latestReport['provider_result'] : null;
        $artifactCount = $summary !== null && is_numeric($summary['artifact_count'] ?? null)
            ? (int) $summary['artifact_count']
            : 0;
        $workflow = is_array($latestReport['workflow'] ?? null) ? $latestReport['workflow'] : null;
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : null;
        $preflight = is_array($latestReport['preflight'] ?? null) ? $latestReport['preflight'] : null;
        $retry = is_array($latestReport['retry'] ?? null) ? $latestReport['retry'] : null;
        $failure = is_array($latestReport['failure'] ?? null) ? $latestReport['failure'] : null;
        $recommendation = is_array($latestReport['recommended_action'] ?? null) ? $latestReport['recommended_action'] : null;
        $retryValidation = is_array($retry['validation'] ?? null) ? $retry['validation'] : null;
        $followUpInputContract = is_array($followUp['input_contract'] ?? null)
            ? $followUp['input_contract']
            : null;

        return [
            'id' => $task->id,
            'summary' => $task->summary,
            'issue_key' => $task->issue_key,
            'status' => $task->status->value,
            'branch_name' => $task->branch_name,
            'repository_path' => $task->repository_path,
            'assigned_machine_id' => $task->assigned_machine_id,
            'claimed_at' => $task->claimed_at instanceof DateTimeInterface
                ? $task->claimed_at->format(DateTimeInterface::ATOM)
                : null,
            'completed_at' => $task->completed_at instanceof DateTimeInterface
                ? $task->completed_at->format(DateTimeInterface::ATOM)
                : null,
            'latest_run' => $latestRun instanceof AutoCodingTaskRun ? [
                'id' => $latestRun->id,
                'machine_id' => $latestRun->machine_id,
                'status' => $latestRun->status->value,
                'started_at' => $latestRun->started_at instanceof DateTimeInterface
                    ? $latestRun->started_at->format(DateTimeInterface::ATOM)
                    : null,
                'completed_at' => $latestRun->completed_at instanceof DateTimeInterface
                    ? $latestRun->completed_at->format(DateTimeInterface::ATOM)
                    : null,
                'artifact_count' => $latestRun->artifacts->count(),
            ] : null,
            'machine' => $latestRun?->machine === null ? null : [
                'id' => $latestRun->machine->id,
                'machine_key' => $latestRun->machine->machine_key,
                'hostname' => $latestRun->machine->hostname,
                'operating_system' => $latestRun->machine->operating_system,
                'last_seen_at' => $latestRun->machine->last_seen_at instanceof DateTimeInterface
                    ? $latestRun->machine->last_seen_at->format(DateTimeInterface::ATOM)
                    : null,
            ],
            'assigned_machine' => $task->assignedMachine === null ? null : [
                'id' => $task->assignedMachine->id,
                'machine_key' => $task->assignedMachine->machine_key,
                'availability_status' => $task->assignedMachine->availability_status,
            ],
            'progress' => [
                'artifact_count' => $artifactCount,
                'validation_status' => is_string($validation['overall_status'] ?? null)
                    ? $validation['overall_status']
                    : 'not_run',
                'provider_status' => is_string($provider['status'] ?? null)
                    ? $provider['status']
                    : 'unknown',
                'preflight_status' => is_string($preflight['overall_status'] ?? null)
                    ? $preflight['overall_status']
                    : 'unknown',
                'validation_attempts_used' => is_numeric($retryValidation['attempts_used'] ?? null)
                    ? (int) $retryValidation['attempts_used']
                    : 0,
                'validation_retry_remaining' => is_numeric($retryValidation['remaining_attempts'] ?? null)
                    ? (int) $retryValidation['remaining_attempts']
                    : 0,
                'failure_category' => is_string($failure['category'] ?? null)
                    ? $failure['category']
                    : 'unknown',
                'recommended_action' => is_string($recommendation['action'] ?? null)
                    ? $recommendation['action']
                    : 'unknown',
                'current_step' => is_string($workflow['current_step'] ?? null)
                    ? $workflow['current_step']
                    : null,
                'last_failed_step' => is_string($workflow['last_failed_step'] ?? null)
                    ? $workflow['last_failed_step']
                    : null,
                'last_blocked_step' => is_string($workflow['last_blocked_step'] ?? null)
                    ? $workflow['last_blocked_step']
                    : null,
                'last_retryable_step' => is_string($workflow['last_retryable_step'] ?? null)
                    ? $workflow['last_retryable_step']
                    : null,
                'follow_up_required' => (bool) ($followUp['required'] ?? false),
                'follow_up_type' => is_string($followUpInputContract['type'] ?? null)
                    ? $followUpInputContract['type']
                    : null,
            ],
            'preflight' => [
                'overall_status' => is_string($preflight['overall_status'] ?? null)
                    ? $preflight['overall_status']
                    : 'unknown',
                'blocking_reason' => is_string($preflight['blocking_reason'] ?? null)
                    ? $preflight['blocking_reason']
                    : null,
                'warnings' => is_array($preflight['warnings'] ?? null)
                    ? array_values(array_filter(
                        $preflight['warnings'],
                        static fn (mixed $warning): bool => is_string($warning) && trim($warning) !== ''
                    ))
                    : [],
                'checks' => is_array($preflight['checks'] ?? null)
                    ? array_values($preflight['checks'])
                    : [],
            ],
            'retry' => [
                'overall_retryable' => (bool) ($retry['overall_retryable'] ?? false),
                'validation' => [
                    'attempts_used' => is_numeric($retryValidation['attempts_used'] ?? null)
                        ? (int) $retryValidation['attempts_used']
                        : 0,
                    'max_attempts' => is_numeric($retryValidation['max_attempts'] ?? null)
                        ? (int) $retryValidation['max_attempts']
                        : 0,
                    'remaining_attempts' => is_numeric($retryValidation['remaining_attempts'] ?? null)
                        ? (int) $retryValidation['remaining_attempts']
                        : 0,
                    'exhausted' => (bool) ($retryValidation['exhausted'] ?? false),
                ],
                'retryable_steps' => is_array($retry['retryable_steps'] ?? null)
                    ? array_values($retry['retryable_steps'])
                    : [],
            ],
            'failure' => [
                'category' => is_string($failure['category'] ?? null)
                    ? $failure['category']
                    : 'unknown',
                'source' => is_string($failure['source'] ?? null)
                    ? $failure['source']
                    : null,
                'retryable' => (bool) ($failure['retryable'] ?? false),
                'message' => is_string($failure['message'] ?? null)
                    ? $failure['message']
                    : null,
            ],
            'recommended_action' => [
                'action' => is_string($recommendation['action'] ?? null)
                    ? $recommendation['action']
                    : 'unknown',
                'reason' => is_string($recommendation['reason'] ?? null)
                    ? $recommendation['reason']
                    : null,
                'message' => is_string($recommendation['message'] ?? null)
                    ? $recommendation['message']
                    : null,
            ],
            'workflow' => [
                'current_step' => is_string($workflow['current_step'] ?? null)
                    ? $workflow['current_step']
                    : null,
                'last_failed_step' => is_string($workflow['last_failed_step'] ?? null)
                    ? $workflow['last_failed_step']
                    : null,
                'last_blocked_step' => is_string($workflow['last_blocked_step'] ?? null)
                    ? $workflow['last_blocked_step']
                    : null,
                'last_retryable_step' => is_string($workflow['last_retryable_step'] ?? null)
                    ? $workflow['last_retryable_step']
                    : null,
                'current_decision_point' => is_array($workflow['current_decision_point'] ?? null)
                    ? $workflow['current_decision_point']
                    : null,
                'steps' => is_array($workflow['steps'] ?? null)
                    ? array_values($workflow['steps'])
                    : [],
            ],
            'follow_up' => [
                'required' => (bool) ($followUp['required'] ?? false),
                'reason' => is_string($followUp['reason'] ?? null)
                    ? $followUp['reason']
                    : null,
                'message' => is_string($followUp['message'] ?? null)
                    ? $followUp['message']
                    : null,
                'questions' => is_array($followUp['questions'] ?? null)
                    ? array_values(array_filter(
                        $followUp['questions'],
                        static fn (mixed $question): bool => is_string($question) && trim($question) !== ''
                    ))
                    : [],
                'question_contracts' => is_array($followUp['question_contracts'] ?? null)
                    ? array_values($followUp['question_contracts'])
                    : [],
                'answered' => (bool) ($followUp['answered'] ?? false),
                'answer_count' => is_numeric($followUp['answer_count'] ?? null)
                    ? (int) $followUp['answer_count']
                    : 0,
                'last_answered_at' => is_string($followUp['last_answered_at'] ?? null)
                    ? $followUp['last_answered_at']
                    : null,
                'last_answer' => is_array($followUp['last_answer'] ?? null)
                    ? $followUp['last_answer']
                    : null,
                'input_contract' => $followUpInputContract,
            ],
        ];
    }
}
