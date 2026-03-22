<?php

namespace App\Enums;

enum ChampionshipRegistrationPaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case NOT_REQUIRED = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PAID => 'Pagado',
            self::FAILED => 'Fallido',
            self::REFUNDED => 'Reembolsado',
            self::NOT_REQUIRED => 'No requerido',
        };
    }
}
