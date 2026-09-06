<?php

namespace App\Services;

use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;

class PublicCategoryOfficialResultsService
{
    /**
     * @return array{
     *     league: CategoryOfficialResult|null,
     *     cup: CategoryOfficialResult|null
     * }
     */
    public function get(Category $category): array
    {
        $results = CategoryOfficialResult::query()
            ->select([
                'id',
                'category_id',
                'competition_part',
                'version',
                'status',
                'officialized_at',
            ])
            ->where('category_id', $category->id)
            ->official()
            ->whereIn('competition_part', [
                OfficialResultCompetitionPart::LEAGUE->value,
                OfficialResultCompetitionPart::CUP->value,
            ])
            ->with([
                'leagueRows' => fn ($query) => $query
                    ->select([
                        'id',
                        'official_result_id',
                        'position',
                        'entry_type',
                        'public_display_name',
                        'public_anonymized_at',
                        'played',
                        'wins',
                        'losses',
                        'points',
                        'games_for',
                        'games_against',
                        'games_diff',
                    ])
                    ->orderBy('position'),
                'cupWinner' => fn ($query) => $query
                    ->select([
                        'id',
                        'official_result_id',
                        'entry_type',
                        'public_display_name',
                        'public_anonymized_at',
                    ]),
            ])
            ->get();

        $league = $results->first(
            fn (CategoryOfficialResult $result): bool => $result->competition_part
                === OfficialResultCompetitionPart::LEAGUE,
        );
        $cup = $results->first(
            fn (CategoryOfficialResult $result): bool => $result->competition_part
                === OfficialResultCompetitionPart::CUP,
        );

        $this->assertComplete($league, $cup);

        return [
            'league' => $league,
            'cup' => $cup,
        ];
    }

    private function assertComplete(
        ?CategoryOfficialResult $league,
        ?CategoryOfficialResult $cup,
    ): void {
        if ($league !== null
            && (
                $league->officialized_at === null
                || $league->leagueRows->isEmpty()
                || $league->cupWinner !== null
            )
        ) {
            throw new OfficialResultSourceIntegrityException(
                'El resultado oficial vigente de Liga contiene evidencia tipada incoherente.',
            );
        }

        if ($cup !== null
            && (
                $cup->officialized_at === null
                || $cup->cupWinner === null
                || $cup->leagueRows->isNotEmpty()
            )
        ) {
            throw new OfficialResultSourceIntegrityException(
                'El resultado oficial vigente de Copa contiene evidencia tipada incoherente.',
            );
        }
    }
}
