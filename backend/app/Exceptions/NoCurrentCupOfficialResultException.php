<?php

namespace App\Exceptions;

use DomainException;

class NoCurrentCupOfficialResultException extends DomainException
{
    public function __construct()
    {
        parent::__construct('La categoría no tiene un resultado oficial de Copa vigente que reabrir.');
    }
}
