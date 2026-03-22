<?php

namespace App\Enums;

enum ChampionshipRegistrationStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abiertas',
            self::CLOSED => 'Cerradas',
        };
    }
}
