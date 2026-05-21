<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Services\AutoCoding\AutoCodingFollowUpQuestionService;
use Tests\TestCase;

class AutoCodingFollowUpQuestionServiceTest extends TestCase
{
    /**
     * Confirm mixed follow-up question definitions are normalized into renderable contracts.
     *
     * @return void
     */
    public function test_it_normalizes_follow_up_question_contracts(): void
    {
        $service = $this->app->make(AutoCodingFollowUpQuestionService::class);

        $contracts = $service->normalizeQuestionContracts([
            'Which file should be edited first?',
            [
                'id' => 'workspace_confirmation',
                'prompt' => 'Confirm workspace usage.',
                'input_type' => 'confirmation',
                'required' => true,
                'accepted_values' => [' Allow ', 'Continue'],
                'options' => [
                    'Allow',
                    ['label' => 'Continue', 'value' => 'continue'],
                ],
            ],
        ]);

        $prompts = $service->normalizeQuestionPrompts([], $contracts);

        self::assertSame('question_1', $contracts[0]['id'] ?? null);
        self::assertSame('Which file should be edited first?', $contracts[0]['prompt'] ?? null);
        self::assertSame('workspace_confirmation', $contracts[1]['id'] ?? null);
        self::assertSame(['allow', 'continue'], $contracts[1]['accepted_values'] ?? null);
        self::assertSame('Allow', $contracts[1]['options'][0]['label'] ?? null);
        self::assertSame('allow', $contracts[1]['options'][0]['value'] ?? null);
        self::assertSame(
            ['Which file should be edited first?', 'Confirm workspace usage.'],
            $prompts
        );
    }
}
