<?php

namespace App\Exceptions;

use DomainException;

class OfficialResultHistoryDeletionBlockedException extends DomainException
{
    public const MESSAGE = 'No se puede eliminar este elemento porque conserva histórico de resultados oficiales.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
