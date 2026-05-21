<?php

return [
    // Brand values shared across shells and generated UI labels.
    'brand' => [
        'name' => 'OPAS',
        'tagline' => 'operations platform',
        'mark' => 'OP',
    ],

    // Authentication and provider defaults for runtime auth flows.
    'auth' => [
        'provider_key_pattern' => (string) env('AUTH_PROVIDER_KEY_PATTERN', '^[a-z][a-z0-9_-]{1,63}$'),
        'provider_key_example' => (string) env('AUTH_PROVIDER_KEY_EXAMPLE', 'google'),
        'email_verification' => [
            'default_mode' => (string) env('AUTH_EMAIL_PROVIDER_DEFAULT_VERIFICATION_MODE', 'required'),
            'expire_minutes' => (int) env('AUTH_EMAIL_VERIFICATION_EXPIRE_MINUTES', 10),
            'code_length' => (int) env('AUTH_EMAIL_VERIFICATION_CODE_LENGTH', 6),
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

    // Workspace and admin navigation groups rendered in the shared shell.
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

    // Local-first auto-coding orchestration, provider, and validation settings.
    'auto_coding' => [
        'default_repository_path' => env('AUTO_CODING_DEFAULT_REPOSITORY_PATH'),
        'machine_key' => env('AUTO_CODING_MACHINE_KEY'),
        'machine_stale_seconds' => (int) env('AUTO_CODING_MACHINE_STALE_SECONDS', 300),
        'provider' => env('AUTO_CODING_PROVIDER', 'null'),
        'github' => [
            'base_branch' => env('AUTO_CODING_GITHUB_BASE_BRANCH', 'main'),
        ],
        'providers' => [
            'ollama' => [
                'base_url' => env('AUTO_CODING_OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
                'model' => env('AUTO_CODING_OLLAMA_MODEL', 'qwen2.5:7b'),
                'timeout_seconds' => (int) env('AUTO_CODING_OLLAMA_TIMEOUT_SECONDS', 30),
                'prompt_path' => env(
                    'AUTO_CODING_PROMPT_PATH',
                    base_path('../../ai-local/agents/laravel-n8n-orchestrator.md')
                ),
            ],
        ],
        'validation_commands' => [
            'lint' => array_filter([
                env('AUTO_CODING_VALIDATE_LINT'),
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''),
            'static_analysis' => array_filter([
                env('AUTO_CODING_VALIDATE_STATIC_ANALYSIS'),
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''),
            'tests' => array_filter([
                env('AUTO_CODING_VALIDATE_TESTS'),
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''),
            'frontend' => array_filter([
                env('AUTO_CODING_VALIDATE_FRONTEND'),
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''),
        ],
        'workflow' => [
            // Retry and validation-group controls for structured workflow execution.
            'validation_retry_limit' => (int) env('AUTO_CODING_WORKFLOW_VALIDATION_RETRY_LIMIT', 2),
            'retryable_validation_groups' => array_values(array_filter(array_map(
                static fn (string $group): string => trim($group),
                explode(',', (string) env('AUTO_CODING_WORKFLOW_RETRYABLE_VALIDATION_GROUPS', 'tests,frontend'))
            ), static fn (string $group): bool => $group !== '')),
        ],
    ],
];
