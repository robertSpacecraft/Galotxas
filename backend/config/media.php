<?php

return [
    'disk' => env('MEDIA_DISK', 'media_local'),

    // Stored outputs are not HTTP uploads: lossless output can exceed input bytes.
    'stored_master_max_bytes' => 32 * 1024 * 1024,

    'temporary_url_ttl_seconds' => (int) env('MEDIA_TEMPORARY_URL_TTL_SECONDS', 300),
    'private_temporary_url_ttl_seconds' => (int) env('MEDIA_PRIVATE_TEMPORARY_URL_TTL_SECONDS', 60),

    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    // Published versions are immutable: introduce a new version to change widths.
    'variant_policies' => [
        'v1' => [
            'widths' => [
                'avatar' => [128, 256],
                'banner' => [320, 640, 960, 1280],
                'news_cover' => [320, 640, 960, 1280],
                'sponsor_logo' => [160, 320, 640],
                'content' => [320, 640, 960, 1280, 1920],
            ],
        ],
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
        'news_cover' => [
            'input_max_kb' => 8192,
            'max_pixels' => 16_000_000,
            'max_width' => 6000,
            'max_height' => 6000,
            'output_max_width' => 1920,
            'output_max_height' => 1080,
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
