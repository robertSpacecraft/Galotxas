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

    /**
     * NAME_INITIAL also authorizes alias use under the same public-identity
     * authorization lifecycle.
     */
    public function allowsAlias(): bool
    {
        return match ($this) {
            self::ALIAS, self::NAME_INITIAL => true,
            self::ANONYMOUS => false,
        };
    }

    /** Whether this mode authorizes use of name + initial. */
    public function allowsNameInitial(): bool
    {
        return $this === self::NAME_INITIAL;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
