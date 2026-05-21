<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\AutoCodingFollowUpResponseService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutoCodingFollowUpResponseServiceTest extends TestCase
{
    /**
     * Confirm structured follow-up payloads are normalized into a stable contract.
     *
     * @return void
     */
    public function test_it_normalizes_structured_follow_up_payloads(): void
    {
        $service = $this->app->make(AutoCodingFollowUpResponseService::class);

        $payload = $service->normalizePayload([
            'type' => ' question_answer_list ',
            'metadata' => [
                ' source ' => 'admin-ui',
                1 => 'ignored',
            ],
            'answers' => [
                [
                    'question_id' => ' target_file ',
                    'type' => ' text ',
                    'value' => ' apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php ',
                    'metadata' => [
                        ' source ' => 'button',
                    ],
                ],
            ],
        ], 'fallback');

        self::assertSame('question_answer_list', $payload['type']);
        self::assertSame('fallback', $payload['value']);
        self::assertSame('admin-ui', $payload['metadata']['source'] ?? null);
        self::assertSame('target_file', $payload['answers'][0]['question_id'] ?? null);
        self::assertSame(
            'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
            $payload['answers'][0]['value'] ?? null
        );
    }

    /**
     * Confirm confirmation-only follow-up contracts reject invalid structured answers.
     *
     * @return void
     */
    public function test_it_rejects_invalid_structured_follow_up_answers(): void
    {
        $service = $this->app->make(AutoCodingFollowUpResponseService::class);

        $payload = $service->normalizePayload([
            'type' => 'question_answer_list',
            'value' => 'maybe',
            'answers' => [
                [
                    'question_id' => 'workspace_confirmation',
                    'type' => 'confirmation',
                    'value' => 'maybe',
                    'metadata' => [],
                ],
            ],
        ], 'maybe');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Question "workspace_confirmation" only accepts');

        $service->assertMatchesFollowUpContract([
            'input_contract' => [
                'type' => 'confirmation',
                'accepted_values' => ['allow', 'continue'],
                'free_text_allowed' => false,
            ],
            'question_contracts' => [
                [
                    'id' => 'workspace_confirmation',
                    'input_type' => 'confirmation',
                    'required' => true,
                    'accepted_values' => ['allow', 'continue'],
                ],
            ],
        ], $payload);
    }
}
