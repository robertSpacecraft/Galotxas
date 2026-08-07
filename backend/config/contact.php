<?php

return [
    'form_enabled' => (bool) env('CONTACT_FORM_ENABLED', false),

    'notification' => [
        'enabled' => (bool) env('CONTACT_NOTIFICATION_ENABLED', false),
        'to' => env('CONTACT_NOTIFICATION_TO'),
        'from' => env('CONTACT_NOTIFICATION_FROM'),
        'reply_to_mode' => env('CONTACT_NOTIFICATION_REPLY_TO_MODE', 'requester'),
        'mailer' => env('CONTACT_NOTIFICATION_MAILER'),
        'max_attempts' => 3,
    ],

    'retention_months' => (int) env('CONTACT_RETENTION_MONTHS', 12),
    'abuse_hash_retention_days' => (int) env('CONTACT_ABUSE_HASH_RETENTION_DAYS', 30),
];
