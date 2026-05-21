<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enumerate the next-step recommendations derived from workflow outcome reports.
 */
enum AutoCodingRecommendedAction: string
{
    case TaskComplete = 'task_complete';
    case ResumeWithConfirmation = 'resume_with_confirmation';
    case ProvideFollowUp = 'provide_follow_up';
    case RerunValidation = 'rerun_validation';
    case FixProviderConfig = 'fix_provider_config';
    case InspectExecutionError = 'inspect_execution_error';
}
