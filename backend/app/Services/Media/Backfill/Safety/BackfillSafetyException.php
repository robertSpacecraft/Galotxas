<?php

namespace App\Services\Media\Backfill\Safety;

use RuntimeException;

final class BackfillSafetyException extends RuntimeException
{
    // Deliberately exclude previous exceptions: SDK/SQL messages can contain secrets.
    public function __construct(public readonly SafetyError $reason)
    {
        parent::__construct($reason->value);
    }
}
