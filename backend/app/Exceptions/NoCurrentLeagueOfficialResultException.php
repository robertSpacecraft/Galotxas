<?php

namespace App\Exceptions;

use DomainException;

class NoCurrentLeagueOfficialResultException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La categoría no tiene un resultado oficial de Liga vigente que reabrir.');
    }
}
