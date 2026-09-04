<?php

namespace App\Exceptions;

use DomainException;

class OfficialResultConcurrencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Otra operación modificó concurrentemente el resultado oficial de la categoría.');
    }
}
