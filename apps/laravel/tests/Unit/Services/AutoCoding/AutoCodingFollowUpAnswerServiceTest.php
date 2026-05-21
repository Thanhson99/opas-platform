<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\AutoCodingFollowUpAnswerService;
use Tests\TestCase;

class AutoCodingFollowUpAnswerServiceTest extends TestCase
{
    /**
     * Confirm follow-up answer history is normalized and summarized for provider consumption.
     *
     * @return void
     */
    public function test_it_normalizes_and_summarizes_follow_up_answer_history(): void
    {
        $service = $this->app->make(AutoCodingFollowUpAnswerService::class);

        $normalizedAnswers = $service->normalizeAnswers([
            [
                'response' => ' Focus on workflow ',
                'response_type' => ' free_text ',
                'response_payload' => [
                    'type' => 'free_text',
                    'metadata' => [
                        'source' => 'admin-ui',
                    ],
                    'answers' => [
                        [
                            'question_id' => 'target_file',
                            'type' => 'text',
                            'value' => 'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
                            'metadata' => [],
                        ],
                    ],
                ],
                'submitted_at' => ' 2026-05-21T10:00:00+07:00 ',
            ],
            [
                'response' => 'allow',
                'response_type' => 'confirmation',
                'submitted_at' => '2026-05-21T10:05:00+07:00',
            ],
        ]);

        $summary = $service->buildSummary($normalizedAnswers);

        self::assertSame('Focus on workflow', $normalizedAnswers[0]['response'] ?? null);
        self::assertSame('free_text', $normalizedAnswers[0]['response_type'] ?? null);
        self::assertSame('2026-05-21T10:00:00+07:00', $normalizedAnswers[0]['submitted_at'] ?? null);
        self::assertSame(2, $summary['answer_count'] ?? null);
        self::assertSame('allow', $summary['latest_confirmation'] ?? null);
        self::assertSame('Focus on workflow', $summary['latest_free_text'] ?? null);
        self::assertSame(
            'apps/laravel/app/Services/AutoCoding/LocalAutoCodingTaskService.php',
            $summary['latest_answers_by_question_id']['target_file']['value'] ?? null
        );
    }
}
