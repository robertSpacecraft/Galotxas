<?php

namespace App\Enums;

enum PublicIdentityAuthorizationEventType: string
{
    case REQUESTED = 'requested';
    case ANONYMOUS_SELECTED = 'anonymous_selected';
    case NOTIFICATION_SENT = 'notification_sent';
    case NOTIFICATION_FAILED = 'notification_failed';
    case GUARDIAN_CONFIRMED = 'guardian_confirmed';
    case GUARDIAN_DENIED = 'guardian_denied';
    case PLAYER_LINKED = 'player_linked';
    case PLAYER_LINK_CHANGED = 'player_link_changed';
    case MINOR_ASSENT_RECORDED = 'minor_assent_recorded';
    case APPROVED = 'approved';
    case DENIED = 'denied';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';
    case RESENT = 'resent';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Solicitud registrada',
            self::ANONYMOUS_SELECTED => 'Anonimato elegido',
            self::NOTIFICATION_SENT => 'Notificación enviada',
            self::NOTIFICATION_FAILED => 'Notificación fallida',
            self::GUARDIAN_CONFIRMED => 'Representante confirmó',
            self::GUARDIAN_DENIED => 'Representante rechazó',
            self::PLAYER_LINKED => 'Jugador vinculado',
            self::PLAYER_LINK_CHANGED => 'Jugador vinculado corregido',
            self::MINOR_ASSENT_RECORDED => 'Conformidad del menor registrada',
            self::APPROVED => 'Autorización aprobada',
            self::DENIED => 'Autorización denegada',
            self::REVOKED => 'Autorización revocada',
            self::EXPIRED => 'Autorización caducada',
            self::RESENT => 'Confirmación reenviada',
        };
    }
}
