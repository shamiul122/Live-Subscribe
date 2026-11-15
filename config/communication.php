<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Outbound pipeline
    |--------------------------------------------------------------------------
    |
    | Outbound messages are persisted before dispatch and the job claims the
    | queued row atomically. The job never auto-retries ambiguous network
    | failures; retrying is an explicit inbox action.
    |
    */
    'outbound' => [
        'queue' => env('COMMUNICATION_QUEUE', 'default'),
        'timeout' => (int) env('COMMUNICATION_TIMEOUT', 30),
        'idempotency_ttl' => (int) env('COMMUNICATION_IDEMPOTENCY_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'disk' => env('COMMUNICATION_ATTACHMENT_DISK', 'local'),
        'max_size_kb' => (int) env('COMMUNICATION_ATTACHMENT_MAX_KB', 10240),
        'max_per_message' => (int) env('COMMUNICATION_ATTACHMENT_MAX_PER_MESSAGE', 5),
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'audio/mpeg',
            'audio/ogg',
            'video/mp4',
            'application/zip',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'max_payload_bytes' => (int) env('COMMUNICATION_WEBHOOK_MAX_BYTES', 262144),
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'enabled' => (bool) env('COMMUNICATION_NOTIFICATIONS', true),
    ],
];
