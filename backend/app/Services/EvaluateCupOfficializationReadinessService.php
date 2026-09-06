<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Enums\GameMatchStatus;
use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Team;
use App\Services\Ranking\BuildCategoryLeagueTableService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EvaluateCupOfficializationReadinessService
{
    public function __construct(
        private readonly BuildCategoryLeagueTableService $tableService,
        private readonly MatchScoreRulesService $scoreRules,
    ) {}

    public function evaluate(Category|int $category): CupOfficializationReadiness
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;
        $loadedCategory = Category::query()->with('championship')->findOrFail($categoryId);
        $rounds = Round::query()->where('category_id', $categoryId)->orderBy('id')->get();
        $matches = GameMatch::query()
            ->whereIn('round_id', $rounds->modelKeys())
            ->orderBy('round_id')
            ->orderBy('id')
            ->get();
        $entries = CategoryEntry::query()->where('category_id', $categoryId)->orderBy('id')->get();
        $teams = Team::query()->where('category_id', $categoryId)->orderBy('id')->get();
        $teamMembers = $this->teamMembers($teams->modelKeys());
        $currentResults = CategoryOfficialResult::query()
            ->where('category_id', $categoryId)
            ->where('status', OfficialResultStatus::OFFICIAL->value)
            ->orderBy('id')
            ->get();

        return $this->evaluateState(
            $loadedCategory,
            $currentResults,
            $rounds,
            $matches,
            $entries,
            $teams,
            $teamMembers,
        );
    }

    /**
     * @param  array{rounds: EloquentCollection<int, Round>, matches: EloquentCollection<int, GameMatch>}  $structure
     * @param  array{entries: EloquentCollection<int, CategoryEntry>, teams: EloquentCollection<int, Team>, team_members: Collection<int, object>}  $participants
     */
    public function evaluateLocked(
        OfficialResultLock $lock,
        array $structure,
        array $participants,
    ): CupOfficializationReadiness {
        $lock->category->loadMissing('championship');

        return $this->evaluateState(
            $lock->category,
            $lock->currentOfficialResults,
            $structure['rounds'],
            $structure['matches'],
            $participants['entries'],
            $participants['teams'],
            $participants['team_members'],
        );
    }

    /**
     * @param  EloquentCollection<int, CategoryOfficialResult>  $currentResults
     * @param  EloquentCollection<int, Round>  $rounds
     * @param  EloquentCollection<int, GameMatch>  $matches
     * @param  EloquentCollection<int, CategoryEntry>  $allEntries
     * @param  EloquentCollection<int, Team>  $teams
     * @param  Collection<int, object>  $teamMembers
     */
    private function evaluateState(
        Category $category,
        EloquentCollection $currentResults,
        EloquentCollection $rounds,
        EloquentCollection $matches,
        EloquentCollection $allEntries,
        EloquentCollection $teams,
        Collection $teamMembers,
    ): CupOfficializationReadiness {
        $issues = [];
        $add = static function (string $code, array $context = []) use (&$issues): void {
            $issues[] = new CupOfficializationIssue($code, $context);
        };

        if ($currentResults->contains(
            fn (CategoryOfficialResult $result): bool => $result->competition_part === OfficialResultCompetitionPart::CUP
        )) {
            $add('cup_already_official');
        }

        $entries = $allEntries
            ->filter(fn (CategoryEntry $entry): bool => $entry->status === 'approved')
            ->sortBy('id')
            ->values();

        if ($entries->count() < 4) {
            $add('insufficient_entries', ['approved_entries' => $entries->count()]);
        }

        $championshipType = $category->championship->type;
        $teamMap = $teams->keyBy('id');
        $canonicalEntries = [];

        foreach ($entries as $entry) {
            if ($championshipType === ChampionshipType::SINGLES) {
                if ($entry->entry_type !== 'player' || $entry->team_id !== null) {
                    $add('incoherent_entry_type', ['entry_id' => (int) $entry->id]);

                    continue;
                }
                if ($entry->player_id === null) {
                    $add('missing_entry_source', ['entry_id' => (int) $entry->id]);

                    continue;
                }

                $canonicalEntries[] = [
                    'source_entry_id' => (int) $entry->id,
                    'entry_type' => 'player',
                    'source_player_id' => (int) $entry->player_id,
                    'source_team_id' => null,
                    'team_members' => [],
                ];

                continue;
            }

            if ($entry->entry_type !== 'team' || $entry->player_id !== null) {
                $add('incoherent_entry_type', ['entry_id' => (int) $entry->id]);

                continue;
            }
            if ($entry->team_id === null || ! $teamMap->has($entry->team_id)) {
                $add('missing_entry_source', ['entry_id' => (int) $entry->id]);

                continue;
            }

            $team = $teamMap->get($entry->team_id);
            if ((int) $team->category_id !== (int) $category->id) {
                $add('missing_entry_source', ['entry_id' => (int) $entry->id]);

                continue;
            }

            $members = $teamMembers
                ->where('team_id', $team->id)
                ->sortBy(fn ($member): string => sprintf(
                    '%020d|%s',
                    $member->player_id,
                    $member->role_in_team ?? '',
                ))
                ->values();
            $memberIds = $members->pluck('player_id')->map(fn ($id): int => (int) $id);
            $roles = $members->pluck('role_in_team')->sort()->values()->all();

            if (
                $members->count() !== 2
                || $memberIds->unique()->count() !== 2
                || $roles !== ['back', 'front']
            ) {
                $add('invalid_team_composition', ['entry_id' => (int) $entry->id]);

                continue;
            }

            $canonicalEntries[] = [
                'source_entry_id' => (int) $entry->id,
                'entry_type' => 'team',
                'source_player_id' => null,
                'source_team_id' => (int) $entry->team_id,
                'team_members' => $members->map(fn ($member): array => [
                    'source_player_id' => (int) $member->player_id,
                    'role' => (string) $member->role_in_team,
                ])->all(),
            ];
        }

        $canonicalEntryMap = collect($canonicalEntries)->keyBy('source_entry_id');
        $validEntryIdSet = array_fill_keys(
            $canonicalEntryMap->keys()->map(fn ($id): int => (int) $id)->all(),
            true,
        );

        $cupRounds = $rounds->where('type', 'cup')->values();
        $semifinalRounds = new EloquentCollection;
        $finalRounds = new EloquentCollection;
        $thirdPlaceRounds = new EloquentCollection;

        foreach ($cupRounds as $round) {
            if ($round->phase !== 'cup') {
                $add('ambiguous_cup_round', ['round_id' => (int) $round->id]);

                continue;
            }

            if ($round->stage === 'semifinal') {
                $semifinalRounds->push($round);
            } elseif ($round->stage === 'final') {
                $finalRounds->push($round);
            } elseif ($round->stage === 'third_place') {
                $thirdPlaceRounds->push($round);
            } else {
                $add('ambiguous_cup_round', ['round_id' => (int) $round->id]);
            }
        }

        $this->assertRoundCardinality(
            $semifinalRounds,
            'missing_semifinal_round',
            'duplicate_semifinal_round',
            $add,
        );
        $this->assertRoundCardinality(
            $finalRounds,
            'missing_final_round',
            'duplicate_final_round',
            $add,
        );
        if ($thirdPlaceRounds->count() > 1) {
            $add('duplicate_third_place_round', [
                'round_ids' => $thirdPlaceRounds->modelKeys(),
            ]);
        }

        $semifinalMatches = $semifinalRounds->count() === 1
            ? $matches->where('round_id', $semifinalRounds->first()->id)->sortBy('id')->values()
            : new EloquentCollection;
        $finalMatches = $finalRounds->count() === 1
            ? $matches->where('round_id', $finalRounds->first()->id)->sortBy('id')->values()
            : new EloquentCollection;

        if ($semifinalRounds->count() === 1 && $semifinalMatches->count() !== 2) {
            $add('invalid_semifinal_match_count', [
                'actual' => $semifinalMatches->count(),
            ]);
        }
        if ($finalRounds->count() === 1 && $finalMatches->count() !== 1) {
            $add('invalid_final_match_count', [
                'actual' => $finalMatches->count(),
            ]);
        }

        $canonicalSemifinals = [];
        foreach ($semifinalMatches as $match) {
            $canonicalSemifinals[] = $this->validateDecisiveMatch(
                $match,
                'semifinal',
                $championshipType,
                $validEntryIdSet,
                $add,
            );
        }

        $canonicalFinals = [];
        foreach ($finalMatches as $match) {
            $canonicalFinals[] = $this->validateDecisiveMatch(
                $match,
                'final',
                $championshipType,
                $validEntryIdSet,
                $add,
            );
        }

        if ($semifinalMatches->count() === 2) {
            $appearances = $semifinalMatches
                ->flatMap(fn (GameMatch $match): array => [
                    (int) $match->home_entry_id,
                    (int) $match->away_entry_id,
                ])
                ->countBy();
            $repeated = $appearances
                ->filter(fn (int $count): bool => $count > 1)
                ->keys()
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values();

            if ($appearances->count() !== 4 || $repeated->isNotEmpty()) {
                $add('duplicate_semifinal_participant', [
                    'entry_ids' => $repeated->all(),
                ]);
            }
        }

        $ranking = null;
        $seed = [];
        if ($entries->count() >= 4 && count($canonicalEntries) === $entries->count()) {
            $leagueRoundIds = $rounds->where('type', 'league')->modelKeys();
            $rankingMatches = $matches
                ->whereIn('round_id', $leagueRoundIds)
                ->filter(fn (GameMatch $match): bool => $match->status === GameMatchStatus::VALIDATED
                    && $match->home_score !== null
                    && $match->away_score !== null)
                ->sortBy('id')
                ->values();

            $rankingSourceValid = true;
            foreach ($rankingMatches as $match) {
                if (! $this->isValidRankingContributor(
                    $match,
                    $championshipType,
                    $validEntryIdSet,
                )) {
                    $rankingSourceValid = false;
                    $add('source_integrity_error', ['match_id' => (int) $match->id]);
                }
            }

            if ($rankingSourceValid) {
                $ranking = $this->tableService->build($entries, $rankingMatches);

                foreach ($ranking->unsupportedTieGroups as $entryGroup) {
                    $positions = $ranking->rows
                        ->whereIn('entry_id', $entryGroup)
                        ->pluck('position')
                        ->map(fn ($position): int => (int) $position);

                    if ($positions->contains(fn (int $position): bool => $position <= 4)) {
                        $add('unsupported_cup_seed_tie', [
                            'entry_ids' => collect($entryGroup)->sort()->values()->all(),
                        ]);
                    }
                }

                $seed = $ranking->rows->take(4)->map(function (array $row) use ($canonicalEntryMap): array {
                    $entry = $canonicalEntryMap->get($row['entry_id']);

                    return array_merge(
                        ['position' => (int) $row['position']],
                        $entry,
                    );
                })->all();
            }
        }

        if (count($seed) === 4 && $semifinalMatches->count() === 2) {
            $seedByPosition = collect($seed)->keyBy('position');
            $expectedPairings = collect([
                $seedByPosition[1]['source_entry_id'].'|'.$seedByPosition[4]['source_entry_id'],
                $seedByPosition[2]['source_entry_id'].'|'.$seedByPosition[3]['source_entry_id'],
            ])->sort()->values()->all();
            $actualPairings = $semifinalMatches->map(
                fn (GameMatch $match): string => (int) $match->home_entry_id.'|'.(int) $match->away_entry_id
            )->sort()->values()->all();

            if ($actualPairings !== $expectedPairings) {
                $add('invalid_cup_seed');
            }
        }

        if (count($canonicalSemifinals) === 2 && count($canonicalFinals) === 1) {
            $semifinalWinners = collect($canonicalSemifinals)
                ->pluck('winner_entry_id')
                ->filter(fn ($id): bool => $id !== null)
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $final = $canonicalFinals[0];
            $finalists = collect([
                $final['home_entry_id'],
                $final['away_entry_id'],
            ])->sort()->values()->all();

            if (count($semifinalWinners) === 2 && $finalists !== $semifinalWinners) {
                $add('inconsistent_finalists');
            }
        }

        if ($issues !== []) {
            return new CupOfficializationReadiness($issues);
        }

        $sourceMatches = collect(array_merge($canonicalSemifinals, $canonicalFinals))
            ->sortBy(fn (array $match): string => sprintf(
                '%d|%020d',
                $match['stage'] === 'semifinal' ? 1 : 2,
                $match['source_game_match_id'],
            ))
            ->values()
            ->all();
        $final = $canonicalFinals[0];
        $seedEntryIds = collect($seed)->pluck('source_entry_id')->all();
        $seedEntryModels = new EloquentCollection(
            $entries->whereIn('id', $seedEntryIds)->values()->all(),
        );

        return new CupOfficializationReadiness([], new CupOfficializationSource(
            $category,
            $championshipType->value,
            $this->scoreRules->targetScore($championshipType),
            $seedEntryModels,
            $seed,
            $sourceMatches,
            [
                'source_entry_id' => (int) $final['winner_entry_id'],
                'source_final_match_id' => (int) $final['source_game_match_id'],
            ],
        ));
    }

    /**
     * @param  EloquentCollection<int, Round>  $rounds
     * @param  callable(string, array): void  $add
     */
    private function assertRoundCardinality(
        EloquentCollection $rounds,
        string $missingCode,
        string $duplicateCode,
        callable $add,
    ): void {
        if ($rounds->isEmpty()) {
            $add($missingCode);
        } elseif ($rounds->count() > 1) {
            $add($duplicateCode, ['round_ids' => $rounds->modelKeys()]);
        }
    }

    /**
     * @param  array<int, true>  $validEntryIdSet
     * @param  callable(string, array): void  $add
     * @return array<string, int|string|null>
     */
    private function validateDecisiveMatch(
        GameMatch $match,
        string $stage,
        ChampionshipType $championshipType,
        array $validEntryIdSet,
        callable $add,
    ): array {
        $homeId = (int) $match->home_entry_id;
        $awayId = (int) $match->away_entry_id;

        if ($homeId === $awayId) {
            $add('self_pairing', ['match_id' => (int) $match->id]);
        }
        if (! isset($validEntryIdSet[$homeId]) || ! isset($validEntryIdSet[$awayId])) {
            $add('foreign_entry', ['match_id' => (int) $match->id]);
        }
        if ($match->status !== GameMatchStatus::VALIDATED) {
            $add('match_not_validated', ['match_id' => (int) $match->id]);
        }

        if ($match->home_score === null || $match->away_score === null) {
            $add('missing_score', ['match_id' => (int) $match->id]);
        } elseif ($match->home_score === $match->away_score) {
            $add('tied_match', ['match_id' => (int) $match->id]);
        } else {
            try {
                $this->scoreRules->validate(
                    $championshipType,
                    $match->home_score,
                    $match->away_score,
                );
            } catch (InvalidArgumentException) {
                $add('invalid_score', ['match_id' => (int) $match->id]);
            }
        }

        if ($match->winner_entry_id === null) {
            $add('missing_winner', ['match_id' => (int) $match->id]);
        } elseif (
            ! in_array((int) $match->winner_entry_id, [$homeId, $awayId], true)
            || ($match->home_score !== null
                && $match->away_score !== null
                && $match->home_score !== $match->away_score
                && (int) $match->winner_entry_id !== ($match->home_score > $match->away_score ? $homeId : $awayId))
        ) {
            $add('inconsistent_winner', ['match_id' => (int) $match->id]);
        }

        return [
            'source_game_match_id' => (int) $match->id,
            'source_round_id' => (int) $match->round_id,
            'stage' => $stage,
            'home_entry_id' => $homeId,
            'away_entry_id' => $awayId,
            'home_score' => $match->home_score,
            'away_score' => $match->away_score,
            'winner_entry_id' => $match->winner_entry_id === null
                ? null
                : (int) $match->winner_entry_id,
        ];
    }

    /** @param array<int, true> $validEntryIdSet */
    private function isValidRankingContributor(
        GameMatch $match,
        ChampionshipType $championshipType,
        array $validEntryIdSet,
    ): bool {
        $homeId = (int) $match->home_entry_id;
        $awayId = (int) $match->away_entry_id;

        if (
            $homeId === $awayId
            || ! isset($validEntryIdSet[$homeId])
            || ! isset($validEntryIdSet[$awayId])
            || $match->winner_entry_id === null
            || ! in_array((int) $match->winner_entry_id, [$homeId, $awayId], true)
        ) {
            return false;
        }

        try {
            $this->scoreRules->validate(
                $championshipType,
                $match->home_score,
                $match->away_score,
            );
        } catch (InvalidArgumentException) {
            return false;
        }

        return (int) $match->winner_entry_id === (
            $match->home_score > $match->away_score ? $homeId : $awayId
        );
    }

    /** @return Collection<int, object> */
    private function teamMembers(array $teamIds): Collection
    {
        if ($teamIds === []) {
            return collect();
        }

        return DB::table('team_members')
            ->whereIn('team_id', $teamIds)
            ->orderBy('team_id')
            ->orderBy('player_id')
            ->orderBy('id')
            ->get();
    }
}
