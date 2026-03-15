<?php

namespace App\Enums;

enum CategoryGender: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case MIXED = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Masculina',
            self::FEMALE => 'Femenina',
            self::MIXED => 'Mixta',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
