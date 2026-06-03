<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoCodingExecutionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Completed = 'completed';

    /**
     * Return all execution-status values.
     *
     * @return array<int, string>
     */
    public static function allValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }

    /**
     * Return statuses that are considered active in queue/dashboard context.
     *
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [
            self::Pending->value,
            self::Running->value,
            self::Blocked->value,
        ];
    }

    /**
     * Return statuses that represent terminal workflow outcomes.
     *
     * @return array<int, string>
     */
    public static function terminalValues(): array
    {
        return [
            self::Completed->value,
            self::Failed->value,
            self::Cancelled->value,
        ];
    }
}
