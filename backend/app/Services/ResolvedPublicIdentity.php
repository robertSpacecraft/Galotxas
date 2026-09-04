<?php

namespace App\Services;

use App\Enums\OfficialIdentityProjection;

final readonly class ResolvedPublicIdentity
{
    public function __construct(
        public OfficialIdentityProjection $projection,
        public string $displayName,
    ) {}
}
