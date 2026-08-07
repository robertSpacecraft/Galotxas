<?php

namespace App\Enums;

enum ContactNotificationStatus: string
{
    case NOT_REQUESTED = 'not_requested';
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUESTED => 'No solicitada',
            self::PENDING => 'Pendiente',
            self::SENT => 'Enviada',
            self::FAILED => 'Fallida',
            self::DISABLED => 'Desactivada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NOT_REQUESTED => 'bg-secondary',
            self::PENDING => 'bg-warning text-dark',
            self::SENT => 'bg-success',
            self::FAILED => 'bg-danger',
            self::DISABLED => 'bg-secondary',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
