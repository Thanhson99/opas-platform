<?php

return [
    'brand' => [
        'name' => 'OPAS',
        'tagline' => 'operations platform',
        'mark' => 'OP',
    ],

    'auth' => [
        'provider_key_pattern' => (string) env('AUTH_PROVIDER_KEY_PATTERN', '^[a-z][a-z0-9_-]{1,63}$'),
        'provider_key_example' => (string) env('AUTH_PROVIDER_KEY_EXAMPLE', 'google'),
        'email_verification' => [
            'default_mode' => (string) env('AUTH_EMAIL_PROVIDER_DEFAULT_VERIFICATION_MODE', 'required'),
            'expire_minutes' => (int) env('AUTH_EMAIL_VERIFICATION_EXPIRE_MINUTES', 60),
        ],
        'oauth' => [
            'http_timeout_seconds' => (int) env('AUTH_OAUTH_HTTP_TIMEOUT_SECONDS', 15),
            'providers' => [
                'google' => [
                    'authorization_endpoint' => env('AUTH_GOOGLE_AUTHORIZATION_ENDPOINT', 'https://accounts.google.com/o/oauth2/v2/auth'),
                    'token_endpoint' => env('AUTH_GOOGLE_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token'),
                    'user_info_endpoint' => env('AUTH_GOOGLE_USER_INFO_ENDPOINT', 'https://openidconnect.googleapis.com/v1/userinfo'),
                ],
                'facebook' => [
                    'authorization_endpoint' => env('AUTH_FACEBOOK_AUTHORIZATION_ENDPOINT', 'https://www.facebook.com/v23.0/dialog/oauth'),
                    'token_endpoint' => env('AUTH_FACEBOOK_TOKEN_ENDPOINT', 'https://graph.facebook.com/v23.0/oauth/access_token'),
                    'user_info_endpoint' => env('AUTH_FACEBOOK_USER_INFO_ENDPOINT', 'https://graph.facebook.com/me?fields=id,name,email'),
                ],
                'github' => [
                    'authorization_endpoint' => env('AUTH_GITHUB_AUTHORIZATION_ENDPOINT', 'https://github.com/login/oauth/authorize'),
                    'token_endpoint' => env('AUTH_GITHUB_TOKEN_ENDPOINT', 'https://github.com/login/oauth/access_token'),
                    'user_info_endpoint' => env('AUTH_GITHUB_USER_INFO_ENDPOINT', 'https://api.github.com/user'),
                    'user_emails_endpoint' => env('AUTH_GITHUB_USER_EMAILS_ENDPOINT', 'https://api.github.com/user/emails'),
                ],
            ],
        ],
    ],

    'navigation' => [
        [
            'label' => 'Workspace',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'route' => 'dashboard',
                    'icon' => 'fas fa-compass',
                ],
            ],
        ],
        [
            'label' => 'Coin Manager',
            'items' => [
                [
                    'label' => 'Coins',
                    'route' => 'coins.index',
                    'icon' => 'fas fa-coins',
                ],
                [
                    'label' => 'Price Alerts',
                    'route' => 'coins.price-alert-settings.index',
                    'icon' => 'fas fa-bell',
                ],
                [
                    'label' => 'Content Keywords',
                    'route' => 'coins.feed-keywords.index',
                    'icon' => 'fas fa-tags',
                ],
            ],
        ],
        [
            'label' => 'Stock Manager',
            'items' => [
                [
                    'label' => 'Stocks',
                    'route' => 'stocks.index',
                    'icon' => 'fas fa-chart-line',
                ],
            ],
        ],
        [
            'label' => 'Video Automation',
            'items' => [
                [
                    'label' => 'Trending Videos',
                    'route' => 'video-automation.trending.index',
                    'icon' => 'fas fa-video',
                ],
            ],
        ],
    ],
];
