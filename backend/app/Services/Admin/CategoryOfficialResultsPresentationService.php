<?php

namespace App\Services\Admin;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Services\EvaluateCupOfficializationReadinessService;
use App\Services\EvaluateLeagueOfficializationReadinessService;
use Illuminate\Database\Eloquent\Collection;

class CategoryOfficialResultsPresentationService
{
    public function __construct(
        private readonly EvaluateLeagueOfficializationReadinessService $leagueReadiness,
        private readonly EvaluateCupOfficializationReadinessService $cupReadiness,
        private readonly OfficialResultReadinessIssueFormatter $issues,
    ) {}

    /**
     * @return array{
     *     parts: array<string, array{
     *         label: string,
     *         current: CategoryOfficialResult|null,
     *         ready: bool,
     *         issues: list<string>
     *     }>,
     *     history: Collection<int, CategoryOfficialResult>
     * }
     */
    public function prepare(Category $category): array
    {
        $history = CategoryOfficialResult::query()
            ->where('category_id', $category->id)
            ->orderByRaw("case competition_part when 'league' then 1 when 'cup' then 2 else 3 end")
            ->orderByDesc('version')
            ->get();

        $leagueCurrent = $this->current($history, OfficialResultCompetitionPart::LEAGUE);
        $cupCurrent = $this->current($history, OfficialResultCompetitionPart::CUP);

        return [
            'parts' => [
                OfficialResultCompetitionPart::LEAGUE->value => $this->leagueState(
                    $category,
                    $leagueCurrent,
                ),
                OfficialResultCompetitionPart::CUP->value => $this->cupState(
                    $category,
                    $cupCurrent,
                ),
            ],
            'history' => $history,
        ];
    }

    /** @param Collection<int, CategoryOfficialResult> $history */
    private function current(
        Collection $history,
        OfficialResultCompetitionPart $part,
    ): ?CategoryOfficialResult {
        return $history->first(
            fn (CategoryOfficialResult $result): bool => $result->competition_part === $part
                && $result->status === OfficialResultStatus::OFFICIAL,
        );
    }

    /**
     * @return array{label: string, current: CategoryOfficialResult|null, ready: bool, issues: list<string>}
     */
    private function leagueState(
        Category $category,
        ?CategoryOfficialResult $current,
    ): array {
        if ($current !== null) {
            return $this->officialState('Liga', $current);
        }

        $readiness = $this->leagueReadiness->evaluate($category);

        return [
            'label' => 'Liga',
            'current' => null,
            'ready' => $readiness->isReady(),
            'issues' => $this->issues->format(
                OfficialResultCompetitionPart::LEAGUE,
                $readiness->safeIssues(),
            ),
        ];
    }

    /**
     * @return array{label: string, current: CategoryOfficialResult|null, ready: bool, issues: list<string>}
     */
    private function cupState(
        Category $category,
        ?CategoryOfficialResult $current,
    ): array {
        if ($current !== null) {
            return $this->officialState('Copa', $current);
        }

        $readiness = $this->cupReadiness->evaluate($category);

        return [
            'label' => 'Copa',
            'current' => null,
            'ready' => $readiness->isReady(),
            'issues' => $this->issues->format(
                OfficialResultCompetitionPart::CUP,
                $readiness->safeIssues(),
            ),
        ];
    }

    /**
     * @return array{label: string, current: CategoryOfficialResult, ready: false, issues: array{}}
     */
    private function officialState(
        string $label,
        CategoryOfficialResult $current,
    ): array {
        return [
            'label' => $label,
            'current' => $current,
            'ready' => false,
            'issues' => [],
        ];
    }
}
