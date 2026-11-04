<?php

declare(strict_types=1);

return [
    'auth' => [
        'max_attempts' => (int) env('AUTH_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
        'token_expiry_days' => (int) env('AUTH_TOKEN_EXPIRY_DAYS', 30),
    ],
    'budget' => [
        'threshold' => (float) env('BUDGET_THRESHOLD', 50.00),
    ],
    'analytics' => [
        'system_availability' => (float) env('SYSTEM_AVAILABILITY', 99.9),
    ],
    'ai' => [
        // `.env.example` ships AI_MODEL empty, and an empty value would reach the OpenAI
        // client as a blank model name, so the default is applied to "" as well as to unset.
        'model' => (string) (env('AI_MODEL') ?: 'gpt-4o-mini'),
        'temperature' => (float) env('AI_TEMPERATURE', 0.1),
    ],
    'upload' => [
        'max_photo_size_kb' => (int) env('UPLOAD_MAX_PHOTO_SIZE_KB', 2048),
        'max_photo_width' => (int) env('UPLOAD_MAX_PHOTO_WIDTH', 4096),
        'max_photo_height' => (int) env('UPLOAD_MAX_PHOTO_HEIGHT', 4096),
        'allowed_photo_mimes' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('UPLOAD_ALLOWED_PHOTO_MIMES', 'jpeg,png,jpg,gif,webp'))),
            static fn (string $mime): bool => $mime !== ''
        )),
    ],
    'pagination' => [
        'default_per_page' => (int) env('PAGINATION_PER_PAGE', 15),
        'admin_per_page' => (int) env('ADMIN_PAGINATION_PER_PAGE', 50),
    ],
    'tokens' => [
        'length' => (int) env('API_TOKEN_LENGTH', 60),
    ],
    'notification' => [
        'mailer' => env('NOTIFICATION_MAILER', 'mailgun_fallback'),
    ],
];
