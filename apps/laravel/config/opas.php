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
        'container_repository_path' => env('AUTO_CODING_CONTAINER_REPOSITORY_PATH', '/workspace/repo'),
        'prompt_path' => env('AUTO_CODING_PROMPT_PATH'),
        'host_database' => [
            'host' => env('AUTO_CODING_HOST_DB_HOST', '127.0.0.1'),
            'port' => env('AUTO_CODING_HOST_DB_PORT', env('DB_PORT', 5432)),
        ],
        'local_worker' => [
            'repository_path' => env('AUTO_CODING_LOCAL_WORKER_REPOSITORY_PATH'),
            'prompt_path' => env('AUTO_CODING_LOCAL_WORKER_PROMPT_PATH'),
            'capabilities' => array_values(array_filter(array_map(
                static fn (string $capability): string => trim($capability),
                explode(',', (string) env('AUTO_CODING_LOCAL_WORKER_CAPABILITIES', 'codex,php,composer,node'))
            ), static fn (string $capability): bool => $capability !== '')),
            'workspace_bindings' => array_values(array_filter(array_map(
                static fn (string $binding): string => trim($binding),
                explode(';', (string) env('AUTO_CODING_LOCAL_WORKER_WORKSPACE_BINDINGS', ''))
            ), static fn (string $binding): bool => $binding !== '')),
            'max_parallel_tasks' => (int) env('AUTO_CODING_LOCAL_WORKER_MAX_PARALLEL_TASKS', 1),
        ],
        'machine_key' => env('AUTO_CODING_MACHINE_KEY'),
        'machine_stale_seconds' => (int) env('AUTO_CODING_MACHINE_STALE_SECONDS', 300),
        'provider' => env('AUTO_CODING_PROVIDER', 'null'),
        'github' => [
            'base_branch' => env('AUTO_CODING_GITHUB_BASE_BRANCH', 'main'),
        ],
        'telegram' => [
            'default_key' => env('AUTO_CODING_TELEGRAM_DEFAULT_KEY', 'default'),
            'default_display_name' => env('AUTO_CODING_TELEGRAM_DEFAULT_DISPLAY_NAME', 'OPAS Telegram Remote Control'),
            'default_purpose' => env('AUTO_CODING_TELEGRAM_DEFAULT_PURPOSE', 'remote_control'),
            'default_environment' => env('AUTO_CODING_TELEGRAM_DEFAULT_ENVIRONMENT', 'local'),
            'default_machine_group' => env('AUTO_CODING_TELEGRAM_DEFAULT_MACHINE_GROUP'),
            'default_locale' => env('AUTO_CODING_TELEGRAM_DEFAULT_LOCALE', 'en'),
            'default_api_base_url' => env('AUTO_CODING_TELEGRAM_DEFAULT_API_BASE_URL', 'https://api.telegram.org'),
            'default_chat_history_limit' => (int) env('AUTO_CODING_TELEGRAM_DEFAULT_CHAT_HISTORY_LIMIT', 30),
            'default_chat_session_timeline_limit' => (int) env('AUTO_CODING_TELEGRAM_DEFAULT_CHAT_SESSION_TIMELINE_LIMIT', 6),
            'pending_interaction_ttl_minutes' => (int) env('AUTO_CODING_TELEGRAM_PENDING_INTERACTION_TTL_MINUTES', 30),
            'bootstrap_bot_token' => env('AUTO_CODING_TELEGRAM_BOT_TOKEN'),
            'bootstrap_webhook_secret' => env('AUTO_CODING_TELEGRAM_WEBHOOK_SECRET'),
            'bootstrap_allowed_chat_ids' => array_values(array_filter(array_map(
                static fn (string $chatId): string => trim($chatId),
                explode(',', (string) env('AUTO_CODING_TELEGRAM_ALLOWED_CHAT_IDS', ''))
            ), static fn (string $chatId): bool => $chatId !== '')),
            'bootstrap_allowed_user_ids' => array_values(array_filter(array_map(
                static fn (string $userId): string => trim($userId),
                explode(',', (string) env('AUTO_CODING_TELEGRAM_ALLOWED_USER_IDS', ''))
            ), static fn (string $userId): bool => $userId !== '')),
            'bootstrap_allowed_actions' => array_values(array_filter(array_map(
                static fn (string $action): string => trim($action),
                explode(',', (string) env(
                    'AUTO_CODING_TELEGRAM_ALLOWED_ACTIONS',
                    'help,menu,conversation,clarify_intent,clarify_issue_context,confirm_pending,chat_start,chat_ping,chat_status,chat_stop,chat_reset,create_task,queue,changes,summary,status,cancel_task,cancel_tasks,delete_task,delete_tasks,reset,resume'
                ))
            ), static fn (string $action): bool => $action !== '')),
            'default_allowed_updates' => array_values(array_filter(array_map(
                static fn (string $updateType): string => trim($updateType),
                explode(',', (string) env('AUTO_CODING_TELEGRAM_DEFAULT_ALLOWED_UPDATES', 'message,callback_query'))
            ), static fn (string $updateType): bool => $updateType !== '')),
        ],
        'providers' => [
            'ollama' => [
                'base_url' => env('AUTO_CODING_OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
                'model' => env('AUTO_CODING_OLLAMA_MODEL', 'qwen2.5:7b'),
                'timeout_seconds' => (int) env('AUTO_CODING_OLLAMA_TIMEOUT_SECONDS', 30),
                'prompt_path' => env('AUTO_CODING_PROMPT_PATH'),
            ],
            'codex' => [
                'executable' => env('AUTO_CODING_CODEX_EXECUTABLE', 'codex'),
                'model' => env('AUTO_CODING_CODEX_MODEL'),
                'approval_mode' => env('AUTO_CODING_CODEX_APPROVAL_MODE', 'auto-edit'),
                'sandbox' => env('AUTO_CODING_CODEX_SANDBOX', 'workspace-write'),
                'timeout_seconds' => (int) env('AUTO_CODING_CODEX_TIMEOUT_SECONDS', 900),
                'exec_args' => array_values(array_filter(array_map(
                    static fn (string $argument): string => trim($argument),
                    explode(' ', (string) env(
                        'AUTO_CODING_CODEX_EXEC_ARGS',
                        '--color never --skip-git-repo-check'
                    ))
                ), static fn (string $argument): bool => $argument !== '')),
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
