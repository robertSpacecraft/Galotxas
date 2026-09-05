<?php

namespace App\Services\CompetitionExport;

final readonly class CategoryCompetitionExportMatchRow
{
    public function __construct(
        public string $groupLabel,
        public ?string $date,
        public ?string $time,
        public ?string $venue,
        public string $homeDisplayName,
        public string $awayDisplayName,
        public ?string $resultText,
    ) {}
}
