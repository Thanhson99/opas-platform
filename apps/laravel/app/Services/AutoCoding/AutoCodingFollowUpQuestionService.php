<?php

declare(strict_types=1);

namespace App\Services\AutoCoding;

/**
 * Normalize follow-up question prompts into stable renderable question contracts.
 */
class AutoCodingFollowUpQuestionService
{
    /**
     * Build one normalized question-contract list from mixed provider or workflow question definitions.
     *
     * @param  mixed  $questions
     * @return array<int, array<string, mixed>>
     */
    public function normalizeQuestionContracts(mixed $questions): array
    {
        if (! is_array($questions)) {
            return [];
        }

        $normalizedContracts = [];

        foreach ($questions as $index => $question) {
            if (is_string($question) && trim($question) !== '') {
                $normalizedContracts[] = $this->buildDefaultQuestionContract(trim($question), (int) $index);

                continue;
            }

            if (! is_array($question)) {
                continue;
            }

            $rawPrompt = $question['prompt'] ?? $question['text'] ?? null;
            $prompt = is_string($rawPrompt) ? trim($rawPrompt) : null;

            if ($prompt === null || $prompt === '') {
                continue;
            }

            $normalizedContracts[] = [
                'id' => is_string($question['id'] ?? null) && trim($question['id']) !== ''
                    ? trim($question['id'])
                    : 'question_'.($index + 1),
                'prompt' => $prompt,
                'input_type' => is_string($question['input_type'] ?? null) && trim($question['input_type']) !== ''
                    ? trim($question['input_type'])
                    : 'text',
                'required' => array_key_exists('required', $question)
                    ? (bool) $question['required']
                    : true,
                'placeholder' => is_string($question['placeholder'] ?? null) && trim($question['placeholder']) !== ''
                    ? trim($question['placeholder'])
                    : null,
                'help_text' => is_string($question['help_text'] ?? null) && trim($question['help_text']) !== ''
                    ? trim($question['help_text'])
                    : null,
                'accepted_values' => $this->normalizeAcceptedValues(
                    is_array($question['accepted_values'] ?? null) ? $question['accepted_values'] : []
                ),
                'options' => $this->normalizeQuestionOptions($question['options'] ?? []),
            ];
        }

        return $normalizedContracts;
    }

    /**
     * Resolve plain-text prompts from one normalized question-contract list.
     *
     * @param  mixed  $questions
     * @param  array<int, array<string, mixed>>|null  $questionContracts
     * @return array<int, string>
     */
    public function normalizeQuestionPrompts(mixed $questions, ?array $questionContracts = null): array
    {
        if (is_array($questionContracts) && $questionContracts !== []) {
            return array_values(array_map(
                static fn (array $contract): string => (string) $contract['prompt'],
                array_filter(
                    $questionContracts,
                    static fn (array $contract): bool => is_string($contract['prompt'] ?? null)
                        && trim((string) $contract['prompt']) !== ''
                )
            ));
        }

        if (! is_array($questions)) {
            return [];
        }

        $normalizedQuestions = [];

        foreach ($questions as $question) {
            if (! is_string($question) || trim($question) === '') {
                continue;
            }

            $normalizedQuestions[] = trim($question);
        }

        return $normalizedQuestions;
    }

    /**
     * Build one default question contract from a plain-text prompt.
     *
     * @param  string  $prompt
     * @param  int  $index
     * @return array<string, mixed>
     */
    protected function buildDefaultQuestionContract(string $prompt, int $index): array
    {
        return [
            'id' => 'question_'.($index + 1),
            'prompt' => $prompt,
            'input_type' => 'text',
            'required' => true,
            'placeholder' => null,
            'help_text' => null,
            'accepted_values' => [],
            'options' => [],
        ];
    }

    /**
     * Normalize accepted values for one question-level contract.
     *
     * @param  array<int|string, mixed>  $acceptedValues
     * @return array<int, string>
     */
    protected function normalizeAcceptedValues(array $acceptedValues): array
    {
        $normalizedValues = [];

        foreach ($acceptedValues as $acceptedValue) {
            if (! is_string($acceptedValue) || trim($acceptedValue) === '') {
                continue;
            }

            $normalizedValues[] = mb_strtolower(trim($acceptedValue));
        }

        return array_values(array_unique($normalizedValues));
    }

    /**
     * Normalize one mixed option list for a question contract.
     *
     * @param  mixed  $options
     * @return array<int, array<string, string>>
     */
    protected function normalizeQuestionOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalizedOptions = [];

        foreach ($options as $option) {
            if (is_string($option) && trim($option) !== '') {
                $normalizedOptions[] = [
                    'label' => trim($option),
                    'value' => mb_strtolower(trim($option)),
                ];

                continue;
            }

            if (! is_array($option)) {
                continue;
            }

            $label = is_string($option['label'] ?? null) ? trim($option['label']) : '';
            $value = is_string($option['value'] ?? null) ? trim($option['value']) : '';

            if ($label === '' || $value === '') {
                continue;
            }

            $normalizedOptions[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $normalizedOptions;
    }
}
