<?php

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))
)));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-CSRF-TOKEN',
        'X-Galotxas-Auth-Mode',
        'X-Requested-With',
    ],
    'exposed_headers' => [],
    'max_age' => 600,
    // Sólo la sesión SPA (SPA_SESSION_AUTH_ENABLED) necesita credenciales CORS.
    'supports_credentials' => (bool) env('SPA_SESSION_AUTH_ENABLED', false),
];
