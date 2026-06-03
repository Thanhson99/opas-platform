<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AutoCoding;

use App\Enums\AutoCodingExecutionStatus;
use App\Models\AutoCodingTask;
use App\Services\AutoCoding\AutoCodingIssueContextEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCodingIssueContextEnrichmentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Confirm issue-linked payloads can reuse the latest local task summary and GitHub context.
     *
     * @return void
     */
    public function test_it_can_enrich_one_issue_linked_task_payload_from_the_latest_issue_task(): void
    {
        config()->set('opas.auto_coding.default_repository_path', base_path('..'));

        AutoCodingTask::query()->create([
            'summary' => 'Older unrelated issue task',
            'issue_key' => 'OPAS-0998',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0998',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'headline' => 'Unrelated headline.',
                ],
            ],
        ]);

        $latestIssueTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram remote status rendering',
            'issue_key' => 'OPAS-0106',
            'repository_path' => '/tmp/issue-repo',
            'branch_name' => 'feature/opas-0106-telegram-status',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'provider_name' => 'ollama',
                'provider_options' => [
                    'model' => 'qwen2.5:14b',
                ],
                'dirty_workspace_policy' => 'allow',
                'scope_paths' => ['apps/laravel/app', 'docs'],
                'scope_policy' => 'block',
            ],
            'latest_report' => [
                'github' => [
                    'headline' => 'Latest issue work is ready for follow-up.',
                    'issue' => [
                        'key' => 'OPAS-0106',
                    ],
                    'repository_slug' => 'Thanhson99/laravel-n8n-automation',
                    'branch_name' => 'feature/opas-0106-telegram-status',
                    'compare_url' => 'https://github.com/Thanhson99/laravel-n8n-automation/compare/main...feature/opas-0106-telegram-status',
                    'pull_request' => [
                        'status' => 'open',
                        'url' => 'https://github.com/Thanhson99/laravel-n8n-automation/pull/106',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $payload = $service->enrichTaskPayload([
            'summary' => 'Review GitHub issue OPAS-0106 and implement the requested changes.',
            'issue_key' => 'OPAS-0106',
        ]);

        self::assertSame('Fix Telegram remote status rendering', $payload['summary'] ?? null);
        self::assertSame('/tmp/issue-repo', $payload['repository_path'] ?? null);
        self::assertSame('ollama', $payload['provider'] ?? null);
        self::assertSame(['model' => 'qwen2.5:14b'], $payload['provider_options'] ?? null);
        self::assertSame('allow', $payload['dirty_workspace_policy'] ?? null);
        self::assertSame(['apps/laravel/app', 'docs'], $payload['scope_paths'] ?? null);
        self::assertSame('block', $payload['scope_policy'] ?? null);
        self::assertSame(
            ['repository_path', 'provider', 'provider_options', 'dirty_workspace_policy', 'scope_paths', 'scope_policy'],
            $payload['context_metadata']['issue_enrichment']['reused_fields'] ?? null
        );
        self::assertSame($latestIssueTask->id, $payload['context_metadata']['issue_context']['source_task_id'] ?? null);
        self::assertSame(
            'https://github.com/Thanhson99/laravel-n8n-automation/pull/106',
            $payload['context_metadata']['issue_context']['pull_request']['url'] ?? null
        );
    }

    /**
     * Confirm validation-style generic summaries can also reuse the latest issue summary.
     *
     * @return void
     */
    public function test_it_can_replace_one_generic_validation_summary_for_issue_linked_payloads(): void
    {
        AutoCodingTask::query()->create([
            'summary' => 'Validate Telegram issue-linked task enrichment flow',
            'issue_key' => 'OPAS-0109',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0109-validation',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0109',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $payload = $service->enrichTaskPayload([
            'summary' => 'Validation request: Validate the current repository state.',
            'issue_key' => 'OPAS-0109',
            'validate' => true,
        ]);

        self::assertSame('Validate Telegram issue-linked task enrichment flow', $payload['summary'] ?? null);
    }

    /**
     * Confirm explicit execution hints are not overwritten by issue-context inheritance.
     *
     * @return void
     */
    public function test_it_does_not_override_explicit_execution_hints(): void
    {
        AutoCodingTask::query()->create([
            'summary' => 'Existing issue task with reusable hints',
            'issue_key' => 'OPAS-0112',
            'repository_path' => '/tmp/history-repo',
            'branch_name' => 'feature/opas-0112-history',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'provider_name' => 'ollama',
                'provider_options' => [
                    'model' => 'qwen2.5:14b',
                ],
                'dirty_workspace_policy' => 'allow',
                'scope_paths' => ['apps/laravel/app'],
                'scope_policy' => 'block',
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0112',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $payload = $service->enrichTaskPayload([
            'summary' => 'Review GitHub issue OPAS-0112 and implement the requested changes.',
            'issue_key' => 'OPAS-0112',
            'repository_path' => '/tmp/explicit-repo',
            'provider' => 'codex',
            'provider_options' => [
                'model' => 'gpt-5.4',
            ],
            'dirty_workspace_policy' => 'warn',
            'scope_paths' => ['docs'],
            'scope_policy' => 'allow',
        ]);

        self::assertSame('/tmp/explicit-repo', $payload['repository_path'] ?? null);
        self::assertSame('codex', $payload['provider'] ?? null);
        self::assertSame(['model' => 'gpt-5.4'], $payload['provider_options'] ?? null);
        self::assertSame('warn', $payload['dirty_workspace_policy'] ?? null);
        self::assertSame(['docs'], $payload['scope_paths'] ?? null);
        self::assertSame('allow', $payload['scope_policy'] ?? null);
    }

    /**
     * Confirm review tasks only inherit the review-safe subset of execution hints.
     *
     * @return void
     */
    public function test_it_reuses_only_review_safe_hints_for_review_tasks(): void
    {
        AutoCodingTask::query()->create([
            'summary' => 'Existing review issue context',
            'issue_key' => 'OPAS-0113',
            'repository_path' => '/tmp/review-safe-repo',
            'branch_name' => 'feature/opas-0113-review',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'provider_name' => 'ollama',
                'provider_options' => [
                    'model' => 'qwen2.5:14b',
                ],
                'dirty_workspace_policy' => 'allow',
                'scope_paths' => ['apps/laravel/app', 'docs'],
                'scope_policy' => 'block',
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0113',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $payload = $service->enrichTaskPayload([
            'summary' => 'Review request: Review the latest repository changes.',
            'issue_key' => 'OPAS-0113',
            'context_metadata' => [
                'transport_context' => [
                    'command' => 'review',
                ],
            ],
        ]);

        self::assertSame('/tmp/review-safe-repo', $payload['repository_path'] ?? null);
        self::assertArrayNotHasKey('provider', $payload);
        self::assertArrayNotHasKey('provider_options', $payload);
        self::assertArrayNotHasKey('dirty_workspace_policy', $payload);
        self::assertSame(['apps/laravel/app', 'docs'], $payload['scope_paths'] ?? null);
        self::assertSame('block', $payload['scope_policy'] ?? null);
        self::assertSame(
            ['repository_path', 'scope_paths', 'scope_policy'],
            $payload['context_metadata']['issue_enrichment']['reused_fields'] ?? null
        );
    }

    /**
     * Confirm terse issue-linked summaries are treated as placeholders for reuse.
     *
     * @return void
     */
    public function test_it_can_replace_one_terse_issue_linked_summary(): void
    {
        AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram short issue prompt handling',
            'issue_key' => 'OPAS-0114',
            'repository_path' => base_path('..'),
            'branch_name' => 'feature/opas-0114-short-prompt',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0114',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $payload = $service->enrichTaskPayload([
            'summary' => 'fix',
            'issue_key' => 'OPAS-0114',
        ]);

        self::assertSame('Fix Telegram short issue prompt handling', $payload['summary'] ?? null);
    }

    /**
     * Confirm code tasks prefer the latest code-like issue history over newer validate history.
     *
     * @return void
     */
    public function test_it_prefers_same_type_issue_history_when_multiple_tasks_exist(): void
    {
        $codeTask = AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram same-type issue history selection',
            'issue_key' => 'OPAS-0116',
            'repository_path' => '/tmp/opas-0116-code-repo',
            'branch_name' => 'feature/opas-0116-code',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'provider_name' => 'ollama',
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0116',
                    ],
                ],
            ],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Validation request: Validate Telegram same-type issue history selection.',
            'issue_key' => 'OPAS-0116',
            'repository_path' => '/tmp/opas-0116-validate-repo',
            'branch_name' => 'feature/opas-0116-validate',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'validate',
                ],
                'dirty_workspace_policy' => 'allow',
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0116',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $payload = $service->enrichTaskPayload([
            'summary' => 'fix',
            'issue_key' => 'OPAS-0116',
            'context_metadata' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
            ],
        ]);

        self::assertSame('Fix Telegram same-type issue history selection', $payload['summary'] ?? null);
        self::assertSame('/tmp/opas-0116-code-repo', $payload['repository_path'] ?? null);
        self::assertSame($codeTask->id, $payload['context_metadata']['issue_context']['source_task_id'] ?? null);
    }

    /**
     * Confirm conflicting same-type issue histories trigger one clarification instead of silent reuse.
     *
     * @return void
     */
    public function test_it_can_require_clarification_for_conflicting_same_type_issue_histories(): void
    {
        AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue context from app service history',
            'issue_key' => 'OPAS-0117',
            'repository_path' => '/tmp/opas-0117-app',
            'branch_name' => 'feature/opas-0117-app',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'scope_paths' => ['apps/laravel/app'],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0117',
                    ],
                ],
            ],
        ]);

        AutoCodingTask::query()->create([
            'summary' => 'Fix Telegram issue context from docs history',
            'issue_key' => 'OPAS-0117',
            'repository_path' => '/tmp/opas-0117-docs',
            'branch_name' => 'feature/opas-0117-docs',
            'status' => AutoCodingExecutionStatus::Completed,
            'context_payload' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
                'scope_paths' => ['docs'],
            ],
            'latest_report' => [
                'github' => [
                    'issue' => [
                        'key' => 'OPAS-0117',
                    ],
                ],
            ],
        ]);

        $service = $this->app->make(AutoCodingIssueContextEnrichmentService::class);

        $resolution = $service->resolveTaskPayload([
            'summary' => 'fix',
            'issue_key' => 'OPAS-0117',
            'context_metadata' => [
                'transport_context' => [
                    'command' => 'conversation',
                    'intent' => 'code',
                ],
            ],
        ]);

        self::assertArrayHasKey('clarification', $resolution);
        self::assertSame('issue_context', $resolution['clarification']['type'] ?? null);
        self::assertCount(2, $resolution['clarification']['candidates'] ?? []);
    }
}
