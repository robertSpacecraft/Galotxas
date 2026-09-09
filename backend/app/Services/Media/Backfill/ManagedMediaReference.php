<?php

namespace App\Services\Media\Backfill;

/** Snapshot only. Publication must re-read the row in a future implementation. */
final readonly class ManagedMediaReference
{
    public function __construct(
        public ManagedMediaDomain $domain,
        public int $id,
        public mixed $masterKey,
        public bool $deleted = false,
        public mixed $width = null,
        public mixed $height = null,
    ) {}
}
