<?php

namespace App\Enums;

enum CmsNavigationSlot: string
{
    case CLUB = 'club';

    public function label(): string
    {
        return match ($this) {
            self::CLUB => 'Club',
        };
    }
}
