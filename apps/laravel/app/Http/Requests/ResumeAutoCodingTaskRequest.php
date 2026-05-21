<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResumeAutoCodingTaskRequest extends FormRequest
{
    /**
     * Normalize legacy and structured resume payloads before validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $responsePayload = $this->input('response_payload');
        $response = $this->input('response');

        if (is_array($responsePayload) && ! is_string($response) && is_string($responsePayload['value'] ?? null)) {
            $this->merge([
                'response' => $responsePayload['value'],
            ]);

            return;
        }

        if (! is_array($responsePayload) || is_string($response)) {
            return;
        }

        $answers = $responsePayload['answers'] ?? null;
        if (! is_array($answers) || $answers === []) {
            return;
        }

        $firstAnswer = $answers[array_key_first($answers)] ?? null;
        if (is_array($firstAnswer) && is_string($firstAnswer['value'] ?? null)) {
            $this->merge([
                'response' => $firstAnswer['value'],
            ]);
        }
    }

    /**
     * Restrict blocked-task resume actions to authenticated admins.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    /**
     * Return validation rules for one blocked auto-coding task resume request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'max:5000'],
            'response_payload' => ['nullable', 'array'],
            'response_payload.value' => ['nullable', 'string', 'max:5000'],
            'response_payload.type' => ['nullable', 'string', 'max:100'],
            'response_payload.metadata' => ['nullable', 'array'],
            'response_payload.answers' => ['nullable', 'array'],
            'response_payload.answers.*.question_id' => ['required_with:response_payload.answers', 'string', 'max:100'],
            'response_payload.answers.*.value' => ['required_with:response_payload.answers', 'string', 'max:5000'],
            'response_payload.answers.*.type' => ['nullable', 'string', 'max:100'],
            'response_payload.answers.*.metadata' => ['nullable', 'array'],
            'resume_token' => ['required', 'string', 'max:255'],
        ];
    }
}
