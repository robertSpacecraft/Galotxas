<?php

namespace App\Enums;

enum SponsorEffectiveState: string
{
    case INACTIVE = 'inactive';
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::INACTIVE => 'Inactivo',
            self::SCHEDULED => 'Programado',
            self::ACTIVE => 'Activo',
            self::EXPIRED => 'Expirado',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'text-bg-success',
            self::SCHEDULED => 'text-bg-info',
            self::EXPIRED => 'text-bg-warning',
            self::INACTIVE => 'text-bg-secondary',
        };
    }
}
