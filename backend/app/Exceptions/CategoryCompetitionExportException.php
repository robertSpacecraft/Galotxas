<?php

namespace App\Exceptions;

use RuntimeException;

class CategoryCompetitionExportException extends RuntimeException
{
    public const AMBIGUOUS_STRUCTURE = 'No se puede exportar la categoría porque su estructura de rondas no es reconocible de forma segura.';

    public const INVALID_PARTICIPANTS = 'No se puede exportar la categoría porque contiene un partido sin participantes válidos.';

    public const INVALID_RESULT = 'No se puede exportar la categoría porque contiene un resultado validado incoherente.';

    public const NO_MATCHES = 'No se puede exportar la categoría porque no contiene ningún partido de Liga o Copa.';

    public const OVERFLOW = 'No se puede exportar: el contenido completo de la categoría excede una única página A4 con el mínimo de legibilidad permitido. No se ha omitido ningún partido.';
}
