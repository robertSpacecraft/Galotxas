<?php

namespace App\Services;

use App\Enums\OfficialIdentityProjection;

final readonly class OfficialResultIdentitySnapshot
{
    public function __construct(
        public OfficialIdentityProjection $projection,
        public string $displayName,
        public string $publicDisplayName,
    ) {}
}
