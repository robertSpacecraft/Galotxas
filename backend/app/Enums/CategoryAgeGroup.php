<?php

namespace App\Enums;

enum CategoryAgeGroup: string
{
    case OPEN = 'open';
    case YOUTH = 'youth';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierta',
            self::YOUTH => 'Juvenil',
        };
    }
}
