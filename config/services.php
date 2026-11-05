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

    'analytics' => [
        'sla_target_minutes' => (int) env('SLA_TARGET_MINUTES', 480),
        'pdf_export_limit' => (int) env('ANALYTICS_PDF_EXPORT_LIMIT', 2000),
    ],

    'stock' => [
        'low_stock_alert_cooldown_hours' => (int) env('LOW_STOCK_ALERT_COOLDOWN_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application settings live in config/services.custom.php
    |--------------------------------------------------------------------------
    |
    | Laravel loads config files sorted by name and writes each one with
    | array_set(), so this file is loaded first and config/services.custom.php
    | then replaces the whole `services.custom` tree. A `custom` block added
    | here would be silently discarded, which is exactly what happened to
    | AUTH_TOKEN_EXPIRY_DAYS and the UPLOAD_* keys.
    |
    */

];
