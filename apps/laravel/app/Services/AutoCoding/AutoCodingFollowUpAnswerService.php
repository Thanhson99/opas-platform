<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

/**
 * Normalize and summarize persisted follow-up answer history for workflow consumers.
 */
class AutoCodingFollowUpAnswerService
{
    /**
     * Normalize one mixed follow-up answer payload into indexed answer records.
     *
     * @param  array<int|string, mixed>  $followUpAnswers
     * @return array<int, array<string, mixed>>
     */
    public function normalizeAnswers(array $followUpAnswers): array
    {
        $normalizedAnswers = [];

        foreach ($followUpAnswers as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $normalizedAnswer = [];

            if (is_string($answer['response'] ?? null) && trim($answer['response']) !== '') {
                $normalizedAnswer['response'] = trim($answer['response']);
            }

            if (is_string($answer['response_type'] ?? null) && trim($answer['response_type']) !== '') {
                $normalizedAnswer['response_type'] = trim($answer['response_type']);
            }

            if (is_array($answer['response_payload'] ?? null)) {
                $normalizedAnswer['response_payload'] = $answer['response_payload'];
            }

            if (is_string($answer['submitted_at'] ?? null) && trim($answer['submitted_at']) !== '') {
                $normalizedAnswer['submitted_at'] = trim($answer['submitted_at']);
            }

            if ($normalizedAnswer === []) {
                continue;
            }

            $normalizedAnswers[] = $normalizedAnswer;
        }

        return $normalizedAnswers;
    }

    /**
     * Build one provider-facing summary map from persisted follow-up answer history.
     *
     * @param  array<int, array<string, mixed>>  $followUpAnswers
     * @return array<string, mixed>
     */
    public function buildSummary(array $followUpAnswers): array
    {
        $latestAnswer = $followUpAnswers === [] ? null : $followUpAnswers[array_key_last($followUpAnswers)];
        $latestAnswersByQuestionId = [];
        $latestFreeText = null;
        $latestConfirmation = null;

        foreach ($followUpAnswers as $answer) {
            $response = is_string($answer['response'] ?? null) ? $answer['response'] : null;
            $responseType = is_string($answer['response_type'] ?? null) ? $answer['response_type'] : null;
            $responsePayload = is_array($answer['response_payload'] ?? null) ? $answer['response_payload'] : null;

            if ($responseType === 'free_text' || $responseType === 'text') {
                $latestFreeText = $response;
            }

            if ($responseType === 'confirmation') {
                $latestConfirmation = $response;
            }

            if (! is_array($responsePayload['answers'] ?? null)) {
                continue;
            }

            foreach ($responsePayload['answers'] as $structuredAnswer) {
                if (
                    ! is_array($structuredAnswer)
                    || ! is_string($structuredAnswer['question_id'] ?? null)
                    || trim($structuredAnswer['question_id']) === ''
                ) {
                    continue;
                }

                $latestAnswersByQuestionId[trim($structuredAnswer['question_id'])] = $structuredAnswer;
            }
        }

        return [
            'answer_count' => count($followUpAnswers),
            'latest_answer' => is_array($latestAnswer) ? $latestAnswer : null,
            'latest_free_text' => $latestFreeText,
            'latest_confirmation' => $latestConfirmation,
            'latest_answers_by_question_id' => $latestAnswersByQuestionId,
        ];
    }
}
