<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enumerate the lifecycle states tracked for each persisted workflow step attempt.
 */
enum AutoCodingWorkflowStepStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Blocked = 'blocked';
    case Skipped = 'skipped';
}
