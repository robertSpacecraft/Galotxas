<?php

namespace App\Services\Media;

/** Internal lifecycle result, not a public API descriptor. */
final readonly class StoredResponsiveSet
{
    public function __construct(
        public string $masterKey,
        public string $manifestKey,
        public ResponsiveManifest $manifest,
    ) {}
}
