<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;
use App\Models\AutoCodingTaskRun;

/**
 * Normalize and hydrate follow-up input contracts for blocked workflow steps.
 */
class AutoCodingFollowUpContractService
{
    /**
     * Normalize one provider-supplied follow-up contract definition into a predictable shape.
     *
     * @param  mixed  $inputContract
     * @return array<string, mixed>
     */
    public function normalizeDefinition(mixed $inputContract): array
    {
        if (! is_array($inputContract)) {
            return [];
        }

        $normalizedContract = [];

        if (is_string($inputContract['type'] ?? null) && trim($inputContract['type']) !== '') {
            $normalizedContract['type'] = trim($inputContract['type']);
        }

        if (is_string($inputContract['format'] ?? null) && trim($inputContract['format']) !== '') {
            $normalizedContract['format'] = trim($inputContract['format']);
        }

        if (is_string($inputContract['expected_input'] ?? null) && trim($inputContract['expected_input']) !== '') {
            $normalizedContract['expected_input'] = trim($inputContract['expected_input']);
        }

        if (is_array($inputContract['accepted_values'] ?? null)) {
            $normalizedContract['accepted_values'] = array_values(array_filter(
                $inputContract['accepted_values'],
                static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
            ));
        }

        foreach (['safe_to_retry', 'idempotent_while_blocked', 'free_text_allowed'] as $booleanKey) {
            if (array_key_exists($booleanKey, $inputContract)) {
                $normalizedContract[$booleanKey] = (bool) $inputContract[$booleanKey];
            }
        }

        return $normalizedContract;
    }

    /**
     * Build one client-facing input contract for blocked-task follow-up handling.
     *
     * @param  AutoCodingTask  $task
     * @param  AutoCodingTaskRun  $run
     * @param  array<string, mixed>  $followUp
     * @return array<string, mixed>|null
     */
    public function buildResolvedInputContract(
        AutoCodingTask $task,
        AutoCodingTaskRun $run,
        array $followUp,
    ): ?array {
        if (($followUp['required'] ?? false) !== true) {
            return null;
        }

        $definition = $this->normalizeDefinition($followUp['input_contract'] ?? null);
        $reason = is_string($followUp['reason'] ?? null) ? $followUp['reason'] : null;
        $defaultType = in_array($reason, ['dirty_workspace', 'scope_mismatch'], true)
            ? 'confirmation'
            : 'free_text';
        $defaultExpectedInput = $defaultType === 'confirmation'
            ? 'confirm_to_continue'
            : 'provide_clarification';
        $acceptedValues = is_array($definition['accepted_values'] ?? null)
            ? array_values($definition['accepted_values'])
            : ($defaultType === 'confirmation' ? ['allow', 'continue', 'proceed', 'yes'] : []);
        $taskId = $task->id;
        $runId = $run->id;

        return [
            'schema_version' => 1,
            'type' => is_string($definition['type'] ?? null) ? $definition['type'] : $defaultType,
            'format' => is_string($definition['format'] ?? null) ? $definition['format'] : 'single_text',
            'expected_input' => is_string($definition['expected_input'] ?? null)
                ? $definition['expected_input']
                : $defaultExpectedInput,
            'accepted_values' => $acceptedValues,
            'free_text_allowed' => array_key_exists('free_text_allowed', $definition)
                ? (bool) $definition['free_text_allowed']
                : $defaultType !== 'confirmation',
            'validation_mode' => $defaultType === 'confirmation'
                ? 'accepted_values_only'
                : 'any_non_empty_text',
            'response_transport' => [
                'string',
                'payload_object',
                'question_answer_list',
            ],
            'safe_to_retry' => array_key_exists('safe_to_retry', $definition)
                ? (bool) $definition['safe_to_retry']
                : true,
            'idempotent_while_blocked' => array_key_exists('idempotent_while_blocked', $definition)
                ? (bool) $definition['idempotent_while_blocked']
                : true,
            'resume_strategy' => 'restart_from_new_run',
            'resume_target' => [
                'task_id' => $taskId,
                'run_id' => $runId,
            ],
            'resume_token' => sprintf('task:%d:run:%d:blocked', $taskId, $runId),
        ];
    }
}
