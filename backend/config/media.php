<?php

return [
    'disk' => env('MEDIA_DISK', 'media_local'),

    'temporary_url_ttl_seconds' => (int) env('MEDIA_TEMPORARY_URL_TTL_SECONDS', 300),
    'private_temporary_url_ttl_seconds' => (int) env('MEDIA_PRIVATE_TEMPORARY_URL_TTL_SECONDS', 60),

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    'profiles' => [
        'avatar' => [
            'input_max_kb' => 3072,
            'max_pixels' => 12_000_000,
            'max_width' => 4096,
            'max_height' => 4096,
            'output_max_width' => 512,
            'output_max_height' => 512,
            'jpeg_quality' => 85,
            'webp_quality' => 82,
        ],
        'banner' => [
            'input_max_kb' => 8192,
            'max_pixels' => 16_000_000,
            'max_width' => 6000,
            'max_height' => 6000,
            'output_max_width' => 1920,
            'output_max_height' => 1920,
            'jpeg_quality' => 85,
            'webp_quality' => 82,
        ],
        'sponsor_logo' => [
            'input_max_kb' => 8192,
            'max_pixels' => 16_000_000,
            'max_width' => 6000,
            'max_height' => 6000,
            'output_max_width' => 1200,
            'output_max_height' => 600,
            'jpeg_quality' => 85,
            'webp_quality' => 82,
        ],
        'content' => [
            'input_max_kb' => 8192,
            'max_pixels' => 16_000_000,
            'max_width' => 6000,
            'max_height' => 6000,
            'output_max_width' => 2048,
            'output_max_height' => 2048,
            'jpeg_quality' => 85,
            'webp_quality' => 82,
        ],
    ],
];
