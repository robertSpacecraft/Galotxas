<?php

namespace App\Services\CompetitionExport;

use Carbon\CarbonImmutable;

final readonly class CategoryCompetitionExportDocument
{
    /**
     * @param  list<string>  $participants
     * @param  list<CategoryCompetitionExportMatchRow>  $leagueMatches
     * @param  list<CategoryCompetitionExportMatchRow>  $cupMatches
     */
    public function __construct(
        public CarbonImmutable $exportedAt,
        public ?string $seasonName,
        public string $championshipName,
        public string $categoryName,
        public string $modalityLabel,
        public int $participantCount,
        public array $participants,
        public array $leagueMatches,
        public array $cupMatches,
    ) {}
}
