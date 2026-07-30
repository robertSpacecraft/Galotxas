<?php

namespace App\Enums;

enum SchoolEnrollmentStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::ACTIVE => 'Activa',
            self::REJECTED => 'Rechazada',
            self::WITHDRAWN => 'Baja',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
