<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use App\Models\AutoCodingTask;

/**
 * Resolve provider follow-up payloads and resume-policy transitions for blocked workflows.
 */
class AutoCodingFollowUpWorkflowService
{
    public function __construct(
        private readonly AutoCodingExecutionContextService $executionContextService,
        private readonly AutoCodingFollowUpRequestService $followUpRequestService,
    ) {}

    /**
     * Extract one normalized follow-up request from the provider output.
     *
     * @param  array<string, mixed>  $providerResult
     * @return array<string, mixed>
     */
    public function extractFollowUpRequest(array $providerResult): array
    {
        $followUp = $providerResult['follow_up'] ?? null;
        if (is_array($followUp) && ($followUp['required'] ?? false) === true) {
            return $this->followUpRequestService->buildRequest(
                true,
                is_string($followUp['message'] ?? null) ? $followUp['message'] : null,
                $followUp['questions'] ?? [],
                is_string($followUp['reason'] ?? null) ? $followUp['reason'] : null,
                $followUp['input_contract'] ?? null,
            );
        }

        $status = is_string($providerResult['status'] ?? null) ? $providerResult['status'] : null;
        if (in_array($status, ['needs_follow_up', 'needs_input', 'blocked'], true)) {
            return $this->followUpRequestService->buildRequest(
                true,
                is_string($providerResult['message'] ?? null) ? $providerResult['message'] : null,
                $providerResult['questions'] ?? [],
                is_string($providerResult['reason'] ?? null) ? $providerResult['reason'] : null,
                $providerResult['input_contract'] ?? null,
            );
        }

        $content = $providerResult['content'] ?? null;
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $decodedFollowUp = is_array($decoded['follow_up'] ?? null) ? $decoded['follow_up'] : [];

                if (($decodedFollowUp['required'] ?? false) === true || ($decoded['status'] ?? null) === 'needs_follow_up') {
                    $decodedMessage = $decodedFollowUp['message'] ?? $decoded['message'] ?? null;

                    if (! is_string($decodedMessage)) {
                        $decodedMessage = null;
                    }

                    $decodedReason = $decodedFollowUp['reason'] ?? $decoded['reason'] ?? null;

                    return $this->followUpRequestService->buildRequest(
                        true,
                        $decodedMessage,
                        $decodedFollowUp['questions'] ?? $decoded['questions'] ?? [],
                        is_string($decodedReason) ? $decodedReason : null,
                        $decodedFollowUp['input_contract'] ?? $decoded['input_contract'] ?? null,
                    );
                }
            }
        }

        return $this->followUpRequestService->buildRequest(false, null, [], null, null);
    }

    /**
     * Resolve the effective dirty-workspace policy after one resume response.
     *
     * @param  AutoCodingTask  $task
     * @param  string  $response
     * @return string
     */
    public function resolveDirtyWorkspacePolicyFromResume(AutoCodingTask $task, string $response): string
    {
        $context = is_array($task->context_payload) ? $task->context_payload : [];
        $policy = $this->executionContextService->normalizeDirtyWorkspacePolicy($context['dirty_workspace_policy'] ?? null);
        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];

        if (($followUp['reason'] ?? null) !== 'dirty_workspace') {
            return $policy;
        }

        return $this->responseConfirmsContinuation($response) ? 'allow' : $policy;
    }

    /**
     * Resolve the effective scope policy after one resume response.
     *
     * @param  AutoCodingTask  $task
     * @param  string  $response
     * @return string
     */
    public function resolveScopePolicyFromResume(AutoCodingTask $task, string $response): string
    {
        $context = is_array($task->context_payload) ? $task->context_payload : [];
        $policy = $this->executionContextService->normalizeScopePolicy($context['scope_policy'] ?? null);
        $latestReport = is_array($task->latest_report) ? $task->latest_report : [];
        $followUp = is_array($latestReport['follow_up'] ?? null) ? $latestReport['follow_up'] : [];

        if (($followUp['reason'] ?? null) !== 'scope_mismatch') {
            return $policy;
        }

        return $this->responseConfirmsContinuation($response) ? 'allow' : $policy;
    }

    /**
     * Determine whether one resume response explicitly confirms continuation.
     *
     * @param  string  $response
     * @return bool
     */
    protected function responseConfirmsContinuation(string $response): bool
    {
        $normalizedResponse = $this->normalizeResumeResponseValue($response);
        $affirmativeTokens = ['yes', 'y', 'ok', 'allow', 'proceed', 'continue', 'dong y', 'đồng ý'];

        foreach ($affirmativeTokens as $token) {
            if ($normalizedResponse === $token || str_contains($normalizedResponse, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize one resume-response token for follow-up workflow matching.
     *
     * @param  string  $response
     * @return string
     */
    protected function normalizeResumeResponseValue(string $response): string
    {
        return mb_strtolower(trim($response));
    }
}
