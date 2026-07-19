<?php

namespace App\Enums;

enum CmsPagePublicationState: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::SCHEDULED => 'Programada',
            self::PUBLISHED => 'Publicada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'text-bg-secondary',
            self::SCHEDULED => 'text-bg-warning',
            self::PUBLISHED => 'text-bg-success',
        };
    }
}
