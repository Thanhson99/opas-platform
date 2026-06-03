<?php

declare(strict_types=1);

namespace App\Enums;

enum AutoCodingTaskPurgeScope: string
{
    case Terminal = 'terminal';
    case All = 'all';

    /**
     * Normalize one incoming purge scope into a supported enum value.
     *
     * @param  mixed  $scope
     * @return self
     */
    public static function fromMixed(mixed $scope): self
    {
        if (! is_string($scope)) {
            return self::Terminal;
        }

        return match (trim(strtolower($scope))) {
            self::All->value, 'force', '--force' => self::All,
            default => self::Terminal,
        };
    }
}
