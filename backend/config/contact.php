<?php

return [
    'form_enabled' => (bool) env('CONTACT_FORM_ENABLED', false),

    'notification' => [
        'enabled' => (bool) env('CONTACT_NOTIFICATION_ENABLED', false),
        'to' => env('CONTACT_NOTIFICATION_TO'),
    ],
];
