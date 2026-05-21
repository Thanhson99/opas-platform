<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

use Illuminate\Validation\ValidationException;

/**
 * Normalize and validate blocked-task follow-up response payloads.
 */
class AutoCodingFollowUpResponseService
{
    /**
     * Normalize one structured follow-up answer payload before validation and persistence.
     *
     * @param  array<string, mixed>|null  $responsePayload
     * @param  string  $fallbackResponse
     * @return array{
     *   type: string,
     *   value: string,
     *   metadata: array<string, mixed>,
     *   answers: array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>
     * }
     */
    public function normalizePayload(?array $responsePayload, string $fallbackResponse): array
    {
        if (! is_array($responsePayload)) {
            return [
                'type' => 'text',
                'value' => trim($fallbackResponse),
                'metadata' => [],
                'answers' => [],
            ];
        }

        $value = is_string($responsePayload['value'] ?? null) && trim($responsePayload['value']) !== ''
            ? trim($responsePayload['value'])
            : trim($fallbackResponse);
        $type = is_string($responsePayload['type'] ?? null) && trim($responsePayload['type']) !== ''
            ? trim($responsePayload['type'])
            : 'text';
        $metadata = [];

        if (is_array($responsePayload['metadata'] ?? null)) {
            foreach ($responsePayload['metadata'] as $key => $metadataValue) {
                if (! is_string($key) || trim($key) === '') {
                    continue;
                }

                $metadata[trim($key)] = $metadataValue;
            }
        }

        return [
            'type' => $type,
            'value' => $value,
            'metadata' => $metadata,
            'answers' => $this->normalizeStructuredQuestionAnswers($responsePayload['answers'] ?? []),
        ];
    }

    /**
     * Validate one normalized response payload against active follow-up contracts.
     *
     * @param  array<string, mixed>  $followUp
     * @param  array{
     *   type: string,
     *   value: string,
     *   metadata: array<string, mixed>,
     *   answers: array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>
     * }  $responsePayload
     * @return void
     */
    public function assertMatchesFollowUpContract(array $followUp, array $responsePayload): void
    {
        $inputContract = is_array($followUp['input_contract'] ?? null)
            ? $followUp['input_contract']
            : [];
        $contractType = is_string($inputContract['type'] ?? null)
            ? $inputContract['type']
            : null;
        $acceptedValues = is_array($inputContract['accepted_values'] ?? null)
            ? $this->normalizeAcceptedResponseValues($inputContract['accepted_values'])
            : [];
        $freeTextAllowed = (bool) ($inputContract['free_text_allowed'] ?? true);
        $payloadType = $responsePayload['type'];
        /** @var array<int, array<string, mixed>> $questionContracts */
        $questionContracts = is_array($followUp['question_contracts'] ?? null)
            ? $followUp['question_contracts']
            : [];
        $structuredAnswers = $responsePayload['answers'];

        if ($structuredAnswers !== []) {
            $this->assertStructuredQuestionAnswersMatchContracts($questionContracts, $structuredAnswers);
        }

        if ($contractType !== 'confirmation') {
            return;
        }

        if ($payloadType !== 'confirmation' && $payloadType !== 'text') {
            throw ValidationException::withMessages([
                'response_payload.type' => 'This blocked task only accepts confirmation-style follow-up answers.',
            ]);
        }

        if (in_array($this->normalizeResponseValue($responsePayload['value']), $acceptedValues, true)) {
            return;
        }

        if ($freeTextAllowed) {
            return;
        }

        throw ValidationException::withMessages([
            'response' => sprintf(
                'This blocked task expects an explicit confirmation response. Allowed values: %s.',
                implode(', ', $acceptedValues)
            ),
        ]);
    }

    /**
     * Build one normalized persisted follow-up answer record from a resume submission.
     *
     * @param  string  $response
     * @param  array{
     *   type: string,
     *   value: string,
     *   metadata: array<string, mixed>,
     *   answers: array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>
     * }  $responsePayload
     * @return array<string, mixed>
     */
    public function buildAnswerRecord(string $response, array $responsePayload): array
    {
        return [
            'response' => $response,
            'response_type' => $responsePayload['type'],
            'response_payload' => $responsePayload,
            'submitted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Normalize accepted response values from one input contract.
     *
     * @param  array<int|string, mixed>  $acceptedValues
     * @return array<int, string>
     */
    public function normalizeAcceptedResponseValues(array $acceptedValues): array
    {
        $normalizedValues = [];

        foreach ($acceptedValues as $acceptedValue) {
            if (! is_string($acceptedValue) || trim($acceptedValue) === '') {
                continue;
            }

            $normalizedValues[] = $this->normalizeResponseValue($acceptedValue);
        }

        return array_values(array_unique($normalizedValues));
    }

    /**
     * Normalize one response token for contract matching.
     *
     * @param  string  $response
     * @return string
     */
    public function normalizeResponseValue(string $response): string
    {
        return mb_strtolower(trim($response));
    }

    /**
     * Normalize one mixed structured question-answer list from a resume payload.
     *
     * @param  mixed  $answers
     * @return array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>
     */
    protected function normalizeStructuredQuestionAnswers(mixed $answers): array
    {
        if (! is_array($answers)) {
            return [];
        }

        $normalizedAnswers = [];

        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $questionId = is_string($answer['question_id'] ?? null) ? trim($answer['question_id']) : '';
            $value = is_string($answer['value'] ?? null) ? trim($answer['value']) : '';
            $type = is_string($answer['type'] ?? null) && trim($answer['type']) !== ''
                ? trim($answer['type'])
                : 'text';

            if ($questionId === '' || $value === '') {
                continue;
            }

            $metadata = [];

            if (is_array($answer['metadata'] ?? null)) {
                foreach ($answer['metadata'] as $key => $metadataValue) {
                    if (! is_string($key) || trim($key) === '') {
                        continue;
                    }

                    $metadata[trim($key)] = $metadataValue;
                }
            }

            $normalizedAnswers[] = [
                'question_id' => $questionId,
                'type' => $type,
                'value' => $value,
                'metadata' => $metadata,
            ];
        }

        return $normalizedAnswers;
    }

    /**
     * Validate one structured question-answer payload against the currently exposed question contracts.
     *
     * @param  array<int, array<string, mixed>>  $questionContracts
     * @param  array<int, array{question_id: string, type: string, value: string, metadata: array<string, mixed>}>  $structuredAnswers
     * @return void
     */
    protected function assertStructuredQuestionAnswersMatchContracts(
        array $questionContracts,
        array $structuredAnswers,
    ): void {
        if ($questionContracts === []) {
            throw ValidationException::withMessages([
                'response_payload.answers' => 'Structured question answers are not supported for this blocked task.',
            ]);
        }

        $contractsById = [];

        foreach ($questionContracts as $questionContract) {
            $questionId = is_string($questionContract['id'] ?? null)
                ? trim($questionContract['id'])
                : '';

            if ($questionId === '') {
                continue;
            }

            $contractsById[$questionId] = $questionContract;
        }

        $providedIds = [];

        foreach ($structuredAnswers as $structuredAnswer) {
            $questionId = $structuredAnswer['question_id'];
            $providedIds[] = $questionId;

            if (! array_key_exists($questionId, $contractsById)) {
                throw ValidationException::withMessages([
                    'response_payload.answers' => sprintf(
                        'Question answer "%s" does not match any active follow-up question.',
                        $questionId
                    ),
                ]);
            }

            $questionContract = $contractsById[$questionId];
            $acceptedValues = is_array($questionContract['accepted_values'] ?? null)
                ? $this->normalizeAcceptedResponseValues($questionContract['accepted_values'])
                : [];
            $inputType = is_string($questionContract['input_type'] ?? null)
                ? $questionContract['input_type']
                : 'text';

            if (
                $inputType === 'confirmation'
                && $acceptedValues !== []
                && ! in_array($this->normalizeResponseValue($structuredAnswer['value']), $acceptedValues, true)
            ) {
                throw ValidationException::withMessages([
                    'response_payload.answers' => sprintf(
                        'Question "%s" only accepts: %s.',
                        $questionId,
                        implode(', ', $acceptedValues)
                    ),
                ]);
            }
        }

        foreach ($contractsById as $questionId => $questionContract) {
            $isRequired = (bool) ($questionContract['required'] ?? true);

            if ($isRequired && ! in_array($questionId, $providedIds, true)) {
                throw ValidationException::withMessages([
                    'response_payload.answers' => sprintf(
                        'Missing required follow-up answer for question "%s".',
                        $questionId
                    ),
                ]);
            }
        }
    }
}
