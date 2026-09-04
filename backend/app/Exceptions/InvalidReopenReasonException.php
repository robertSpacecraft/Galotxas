<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidReopenReasonException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('El motivo de reapertura debe contener entre 1 y 2.000 caracteres.');
    }
}
