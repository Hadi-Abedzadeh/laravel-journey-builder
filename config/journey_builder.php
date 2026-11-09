<?php

return [
    'connection' => env('JOURNEY_BUILDER_DB_CONNECTION', env('DB_CONNECTION', 'sqlsrv')),
    'source_table' => env('JOURNEY_BUILDER_SOURCE_TABLE', 'member_data'),
    'route_prefix' => env('JOURNEY_BUILDER_ROUTE_PREFIX', 'journeys'),
    // auth requires an authenticated Laravel user. local permits only loopback in APP_ENV=local.
    'dashboard_access' => env('JOURNEY_BUILDER_DASHBOARD_ACCESS', 'gate'),
    'dashboard_gate' => env('JOURNEY_BUILDER_DASHBOARD_GATE', 'manage-journeys'),
    'auto_schedule' => env('JOURNEY_BUILDER_AUTO_SCHEDULE', false),
    // Enable only AFTER applying v3.1 migration and configuring a permanent hash key.
    'retention' => [
        'purge_on_exit' => (bool) env('JOURNEY_BUILDER_PURGE_ON_EXIT', false),
        'enrollment_hash_key' => env('JOURNEY_BUILDER_ENROLLMENT_HASH_KEY', ''),
    ],
    'monitoring' => [
        // Heartbeat rows can grow quickly (three commands per minute).
        'command_log_days' => (int) env('JOURNEY_BUILDER_COMMAND_LOG_DAYS', 14),
    ],
    'max_delivery_attempts' => (int) env('JOURNEY_BUILDER_MAX_DELIVERY_ATTEMPTS', 3),
    'timezone' => env('JOURNEY_BUILDER_TIMEZONE', 'Asia/Tehran'),
    'member_timezone' => env('JOURNEY_BUILDER_MEMBER_TIMEZONE', config('app.timezone', 'UTC')),
    'sms' => [
        // Explicit operator enable; a new installation MUST NOT silently send live SMS.
        'managed_enabled' => (bool) env('JOURNEY_BUILDER_MANAGED_SMS_ENABLED', false),
        // The documented Kavenegar per-request limit is 200.
        'chunk_size' => (int) env('JOURNEY_BUILDER_SMS_CHUNK_SIZE', 200),
        'url' => env('JOURNEY_BUILDER_SMS_URL', env('JOURNEY_SMS_URL')),
        'token' => env('JOURNEY_BUILDER_SMS_TOKEN', env('JOURNEY_SMS_TOKEN')),
        'enabled' => env('JOURNEY_BUILDER_SMS_ENABLED', false),
        'include_phone' => env('JOURNEY_BUILDER_SMS_INCLUDE_PHONE', false),
        'timeout' => 15,
    ],
    'channels' => [
        // Must be explicitly enabled after configuring and testing accounts.
        'email' => ['enabled'=>(bool)env('JOURNEY_BUILDER_EMAIL_ENABLED',false)],
        'webpush' => ['enabled'=>(bool)env('JOURNEY_BUILDER_WEBPUSH_ENABLED',false)],
    ],
    'api' => [
        'enabled' => env('JOURNEY_BUILDER_API_ENABLED', false),
        'timeout' => 15,
    ],
];
