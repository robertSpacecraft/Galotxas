<?php

namespace App\Exceptions;

use DomainException;

class OfficialResultMutationBlockedException extends DomainException
{
    public const MESSAGE = 'No se puede modificar este dato porque la categoría tiene un resultado oficial vigente. Reabre primero el resultado oficial correspondiente.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
