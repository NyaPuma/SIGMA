<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backup Configuration
    |--------------------------------------------------------------------------
    |
| Database backup configuration. Uses mysqldump (MySQL), pg_dump (PostgreSQL)
| or sqlite3 (SQLite) directly, without external package dependencies.
    |
    */

    'database' => [

        'connection' => env('DB_BACKUP_CONNECTION', null),

        'destination' => [

            'path' => storage_path('app/backups'),

        ],

        'compression' => (bool) env('DB_BACKUP_COMPRESSION', true),

        'exclude_tables' => [
            'failed_jobs',
            'personal_access_tokens',
        ],

    ],

    'retention' => [

        'days' => (int) env('DB_BACKUP_RETENTION_DAYS', 30),

    ],

    'storage' => [

        'enabled' => (bool) env('BACKUP_STORAGE_ENABLED', true),

        'path' => storage_path('app'),

    ],

    'offsite' => [

        'enabled' => (bool) env('BACKUP_OFFSITE_ENABLED', false),

        'disk' => env('BACKUP_OFFSITE_DISK', 's3'),

        'path' => env('BACKUP_OFFSITE_PATH', 'application-backups'),

    ],

];
