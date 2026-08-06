<?php

namespace App\Enums;

enum PublicIdentityAuthorizationMode: string
{
    case ALIAS = 'alias';
    case NAME_INITIAL = 'name_initial';
    case ANONYMOUS = 'anonymous';

    public function label(): string
    {
        return match ($this) {
            self::ALIAS => 'Alias deportivo',
            self::NAME_INITIAL => 'Nombre e inicial',
            self::ANONYMOUS => 'Identidad anónima',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
