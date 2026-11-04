<?php

declare(strict_types=1);

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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => env('MAILGUN_SCHEME', 'https'),
    ],

    'sendgrid' => [
        'username' => env('SENDGRID_USERNAME', 'apikey'),
        'password' => env('SENDGRID_PASSWORD'),
        'host' => env('SENDGRID_HOST', 'smtp.sendgrid.net'),
        'port' => (int) env('SENDGRID_PORT', 587),
        'scheme' => env('SENDGRID_SCHEME', 'tls'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'budget' => [
        'threshold' => (float) env('BUDGET_THRESHOLD', 50.00),
    ],

    'analytics' => [
        'sla_target_minutes' => (int) env('SLA_TARGET_MINUTES', 480),
        'pdf_export_limit' => (int) env('ANALYTICS_PDF_EXPORT_LIMIT', 2000),
    ],

    'stock' => [
        'low_stock_alert_cooldown_hours' => (int) env('LOW_STOCK_ALERT_COOLDOWN_HOURS', 24),
    ],

    'custom' => [
        'auth' => [
            'max_attempts' => (int) env('AUTH_MAX_ATTEMPTS', 5),
            'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
            'token_expiry_days' => (int) env('AUTH_TOKEN_EXPIRY_DAYS', 30),
        ],
        'upload' => [
            'max_photo_size_kb' => (int) env('UPLOAD_MAX_PHOTO_SIZE_KB', 2048),
            'max_photo_width' => (int) env('UPLOAD_MAX_PHOTO_WIDTH', 4096),
            'max_photo_height' => (int) env('UPLOAD_MAX_PHOTO_HEIGHT', 4096),
            'allowed_photo_mimes' => array_filter(
                explode(',', (string) env('UPLOAD_ALLOWED_PHOTO_MIMES', 'jpeg,png,jpg,gif,webp')),
                fn ($v) => trim($v) !== ''
            ),
        ],
        'pagination' => [
            'per_page' => (int) env('PAGINATION_PER_PAGE', 15),
        ],
        'database' => [
            'slow_query_log' => (bool) env('DB_SLOW_QUERY_LOG', false),
            'slow_query_threshold' => (float) env('DB_SLOW_QUERY_THRESHOLD', 2.0),
        ],
    ],

];
