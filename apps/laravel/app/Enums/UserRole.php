<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Plus = 'plus';
    case Vip = 'vip';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Plus => 'Plus',
            self::Vip => 'VIP',
            self::Admin => 'Admin',
        };
    }
}
