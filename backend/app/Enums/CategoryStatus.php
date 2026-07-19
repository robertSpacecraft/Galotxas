<?php

namespace App\Enums;

enum CategoryStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::ACTIVE => 'Activa',
        };
    }
}
