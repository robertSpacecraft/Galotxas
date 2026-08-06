<?php

return [
    'authorization_enabled' => (bool) env('PUBLIC_IDENTITY_AUTHORIZATION_ENABLED', false),
    'notification_enabled' => (bool) env('PUBLIC_IDENTITY_NOTIFICATION_ENABLED', false),
    'confirmation_ttl_hours' => (int) env('PUBLIC_IDENTITY_CONFIRMATION_TTL_HOURS', 48),
    'privacy_notice_version' => '1.1.0',
];
