<?php

namespace App\Enums;

enum ContactRequestStatus: string
{
    case NEW = 'new';
    case READ = 'read';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nueva',
            self::READ => 'Leída',
            self::CLOSED => 'Cerrada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NEW => 'text-bg-warning',
            self::READ => 'text-bg-info',
            self::CLOSED => 'text-bg-secondary',
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
