<?php

namespace App\Enums;

enum PlayerGender: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Masculino',
            self::FEMALE => 'Femenino',
            self::OTHER => 'Otro',
        };
    }

    public static function options(): array
    {
        return [
            self::MALE->value => self::MALE->label(),
            self::FEMALE->value => self::FEMALE->label(),
            self::OTHER->value => self::OTHER->label(),
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
