<?php

namespace App\Exceptions;

use RuntimeException;

class SchoolEnrollmentUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La inscripción no está disponible actualmente.');
    }
}
