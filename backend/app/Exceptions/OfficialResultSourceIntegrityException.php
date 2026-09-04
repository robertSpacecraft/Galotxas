<?php

namespace App\Exceptions;

use DomainException;

class OfficialResultSourceIntegrityException extends DomainException
{
    public function __construct(string $message = 'La fuente o el histórico de resultados oficiales es incoherente.')
    {
        parent::__construct($message);
    }
}
