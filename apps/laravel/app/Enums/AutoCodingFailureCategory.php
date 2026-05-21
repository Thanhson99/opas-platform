<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enumerate the normalized failure classes exposed by the auto-coding workflow report.
 */
enum AutoCodingFailureCategory: string
{
    case None = 'none';
    case PreflightBlock = 'preflight_block';
    case ProviderFollowUp = 'provider_follow_up';
    case ProviderFailure = 'provider_failure';
    case ValidationFailure = 'validation_failure';
    case ExecutionException = 'execution_exception';
}
