<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enumerate the persisted workflow steps used by the local auto-coding engine.
 */
enum AutoCodingWorkflowStep: string
{
    case InspectRepository = 'inspect_repository';
    case PreparePrompt = 'prepare_prompt';
    case ProviderPlan = 'provider_plan';
    case CollectGithubContext = 'collect_github_context';
    case RunValidation = 'run_validation';
    case CompletionCheck = 'completion_check';
}
