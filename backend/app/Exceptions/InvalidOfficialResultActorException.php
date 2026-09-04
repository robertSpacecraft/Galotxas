<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidOfficialResultActorException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('La operación requiere un administrador activo y un nombre de actor válido.');
    }
}
