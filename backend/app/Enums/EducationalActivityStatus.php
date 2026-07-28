<?php

namespace App\Enums;

enum EducationalActivityStatus: string
{
    case PLANNED = 'planned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planificada',
            self::COMPLETED => 'Completada',
            self::CANCELLED => 'Cancelada',
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
