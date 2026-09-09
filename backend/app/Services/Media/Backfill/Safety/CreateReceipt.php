<?php

namespace App\Services\Media\Backfill\Safety;

final readonly class CreateReceipt
{
    public function __construct(public CreateState $state, public ?string $etag = null, public ?string $versionId = null) {}
}
