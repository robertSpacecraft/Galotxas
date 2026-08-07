<?php

namespace App\Enums;

enum ContactRequestEventType: string
{
    case RECEIVED = 'received';
    case MARKED_AS_READ = 'marked_as_read';
    case CLOSED = 'closed';
    case NOTIFICATION_SENT = 'notification_sent';
    case NOTIFICATION_FAILED = 'notification_failed';
    case NOTIFICATION_DISABLED = 'notification_disabled';
    case NOTIFICATION_RETRIED = 'notification_retried';
    case RETENTION_HOLD_PLACED = 'retention_hold_placed';
    case RETENTION_HOLD_RELEASED = 'retention_hold_released';
    case ANONYMIZED = 'anonymized';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED => 'Solicitud recibida',
            self::MARKED_AS_READ => 'Marcada como leída',
            self::CLOSED => 'Solicitud cerrada',
            self::NOTIFICATION_SENT => 'Notificación enviada',
            self::NOTIFICATION_FAILED => 'Notificación fallida',
            self::NOTIFICATION_DISABLED => 'Notificación desactivada',
            self::NOTIFICATION_RETRIED => 'Reintento solicitado',
            self::RETENTION_HOLD_PLACED => 'Conservación suspendida',
            self::RETENTION_HOLD_RELEASED => 'Suspensión liberada',
            self::ANONYMIZED => 'Datos anonimizados',
        };
    }
}
