<?php

namespace App\Exceptions;

use DomainException;

class LeagueAlreadyOfficialException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La categoría ya tiene un resultado oficial de Liga vigente.');
    }
}
