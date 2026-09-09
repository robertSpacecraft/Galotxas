<?php

namespace App\Services\Media\Exceptions;

use App\Services\Media\Backfill\InspectionReason;
use Throwable;

final class InvalidStoredMaster extends InvalidMediaImage
{
    public function __construct(public readonly InspectionReason $reason, ?Throwable $previous = null)
    {
        parent::__construct('La master almacenada no se puede procesar.', previous: $previous);
    }
}
