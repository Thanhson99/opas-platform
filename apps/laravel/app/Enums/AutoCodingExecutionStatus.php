<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoCodingExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Blocked = 'blocked';
    case Failed = 'failed';
    case Completed = 'completed';
}
