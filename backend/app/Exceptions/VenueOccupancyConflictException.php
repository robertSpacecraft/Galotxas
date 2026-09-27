<?php

namespace App\Exceptions;

use InvalidArgumentException;

class VenueOccupancyConflictException extends InvalidArgumentException
{
    public const MESSAGE = 'La pista seleccionada ya está ocupada en esa fecha y hora por otro partido.';

    public function __construct()
    {
        parent::__construct(self::MESSAGE);
    }
}
