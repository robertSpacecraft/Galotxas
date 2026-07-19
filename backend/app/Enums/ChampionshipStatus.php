<?php

namespace App\Enums;

enum ChampionshipStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::ACTIVE => 'Activo',
            self::FINISHED => 'Finalizado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
