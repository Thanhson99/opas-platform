<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Postmark
    |--------------------------------------------------------------------------
    |
    | Store Postmark API token for sending emails.
    | Usage: config('services.postmark.token')
    |
    */
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resend
    |--------------------------------------------------------------------------
    |
    | Store Resend API key for email notifications.
    | Usage: config('services.resend.key')
    |
    */
    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS SES
    |--------------------------------------------------------------------------
    |
    | Store AWS credentials for SES email sending.
    | Usage: config('services.ses.key'), config('services.ses.secret')
    |
    */
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slack
    |--------------------------------------------------------------------------
    |
    | Store Slack bot token and default channel for notifications.
    | Usage: config('services.slack.notifications.bot_user_oauth_token')
    |
    */
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Binance
    |--------------------------------------------------------------------------
    |
    | Store base URL for Binance API calls.
    | Usage: config('services.binance.base_url')
    |
    */
    'binance' => [
        'base_url' => env('BINANCE_BASE_URL', 'https://api.binance.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Microservices
    |--------------------------------------------------------------------------
    |
    | Base URL for internal Python FastAPI microservices.
    | Laravel services call this URL to access endpoints like:
    | /douyin, /caption, /trending,....
    | Usage: config('services.python.base_url')
    |
    */
    'python' => [
        'base_url' => env('PYTHON_BASE_URL', 'https://python-services.sonvi.vn'),
        'douyin_path' => env('PYTHON_DOUYIN_PATH', '/douyin'),
        'caption_path' => env('PYTHON_CAPTION_PATH', '/caption'),
        'trending_path' => env('PYTHON_TRENDING_PATH', '/trending'),
    ],
];
