<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthProviderType: string
{
    case Password = 'password';
    case OAuth2 = 'oauth2';
}
