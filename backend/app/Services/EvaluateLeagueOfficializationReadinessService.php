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

class EvaluateLeagueOfficializationReadinessService
{
    public function __construct(
        private readonly BuildCategoryLeagueTableService $tableService,
        private readonly MatchScoreRulesService $scoreRules,
    ) {}

    public function evaluate(Category|int $category): LeagueOfficializationReadiness
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
    ): LeagueOfficializationReadiness {
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
    ): LeagueOfficializationReadiness {
        $issues = [];
        $add = static function (string $code, array $context = []) use (&$issues): void {
            $issues[] = new LeagueOfficializationIssue($code, $context);
        };

        if ($currentResults->contains(
            fn (CategoryOfficialResult $result): bool => $result->competition_part === OfficialResultCompetitionPart::LEAGUE
        )) {
            $add('league_already_official');
        }

        $entries = $allEntries
            ->filter(fn (CategoryEntry $entry): bool => $entry->status === 'approved')
            ->sortBy('id')
            ->values();
        $entryIds = $entries->modelKeys();
        $entryIdSet = array_fill_keys($entryIds, true);

        if ($entries->count() < 3) {
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
                $add('incoherent_entry_type', ['entry_id' => (int) $entry->id]);

                continue;
            }

            $members = $teamMembers
                ->where('team_id', $team->id)
                ->sortBy(fn ($member): string => sprintf('%020d|%s', $member->player_id, $member->role_in_team ?? ''))
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

        $leagueRounds = new EloquentCollection;
        foreach ($rounds as $round) {
            $legacyLeague = $round->type === 'league' && $round->phase === null && $round->stage === null;
            $normalizedLeague = $round->type === 'league'
                && $round->phase === 'league'
                && $round->stage === 'matchday';
            $recognizedCup = $round->type === 'cup'
                && $round->phase === 'cup'
                && in_array($round->stage, ['semifinal', 'final', 'third_place'], true);

            if ($legacyLeague || $normalizedLeague) {
                $leagueRounds->push($round);
            } elseif (! $recognizedCup) {
                $add('ambiguous_round', ['round_id' => (int) $round->id]);
            }
        }

        if ($leagueRounds->isEmpty()) {
            $add('missing_league_round');
        }

        $expectedRoundCount = $entries->count() % 2 === 0
            ? max(0, $entries->count() - 1)
            : $entries->count();
        if ($leagueRounds->count() !== $expectedRoundCount) {
            $add('invalid_round_count', [
                'expected' => $expectedRoundCount,
                'actual' => $leagueRounds->count(),
            ]);
        }

        $leagueRoundIds = $leagueRounds->modelKeys();
        $leagueMatches = $matches
            ->whereIn('round_id', $leagueRoundIds)
            ->sortBy('id')
            ->values();
        $expectedMatchCount = intdiv($entries->count() * max(0, $entries->count() - 1), 2);

        foreach ($leagueRounds as $round) {
            $roundMatches = $leagueMatches->where('round_id', $round->id);
            if ($roundMatches->isEmpty()) {
                $add('empty_league_round', ['round_id' => (int) $round->id]);
            }

            $appearances = $roundMatches
                ->flatMap(fn (GameMatch $match): array => [(int) $match->home_entry_id, (int) $match->away_entry_id])
                ->countBy();
            $repeated = $appearances->filter(fn (int $count): bool => $count > 1)->keys();
            if ($repeated->isNotEmpty()) {
                $add('entry_repeated_in_round', [
                    'round_id' => (int) $round->id,
                    'entry_ids' => $repeated->map(fn ($id): int => (int) $id)->sort()->values()->all(),
                ]);
            }
        }

        $seenPairs = [];
        $canonicalMatches = [];
        foreach ($leagueMatches as $match) {
            $homeId = (int) $match->home_entry_id;
            $awayId = (int) $match->away_entry_id;

            if ($homeId === $awayId) {
                $add('self_pairing', ['match_id' => (int) $match->id]);
            }
            if (! isset($entryIdSet[$homeId]) || ! isset($entryIdSet[$awayId])) {
                $add('foreign_entry', ['match_id' => (int) $match->id]);
            }

            $pair = min($homeId, $awayId).'|'.max($homeId, $awayId);
            if (isset($seenPairs[$pair])) {
                $add('duplicate_pairing', ['match_id' => (int) $match->id]);
            }
            $seenPairs[$pair] = true;

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
                    && (int) $match->winner_entry_id !== ($match->home_score > $match->away_score ? $homeId : $awayId))
            ) {
                $add('inconsistent_winner', ['match_id' => (int) $match->id]);
            }

            $canonicalMatches[] = [
                'source_game_match_id' => (int) $match->id,
                'source_round_id' => (int) $match->round_id,
                'stage' => 'matchday',
                'home_entry_id' => $homeId,
                'away_entry_id' => $awayId,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'winner_entry_id' => $match->winner_entry_id === null ? null : (int) $match->winner_entry_id,
            ];
        }

        if ($leagueMatches->count() > $expectedMatchCount) {
            $add('extra_league_match', [
                'expected' => $expectedMatchCount,
                'actual' => $leagueMatches->count(),
            ]);
        }

        $expectedPairs = [];
        for ($i = 0; $i < count($entryIds); $i++) {
            for ($j = $i + 1; $j < count($entryIds); $j++) {
                $expectedPairs[$entryIds[$i].'|'.$entryIds[$j]] = true;
            }
        }
        $missingPairs = array_diff_key($expectedPairs, $seenPairs);
        if ($leagueMatches->count() < $expectedMatchCount || $missingPairs !== []) {
            $add('incomplete_round_robin', ['missing_pairs' => count($missingPairs)]);
        }

        if ($issues !== []) {
            return new LeagueOfficializationReadiness($issues);
        }

        $table = $this->tableService->build($entries, $leagueMatches);
        if (! $table->isSportinglyResolved()) {
            foreach ($table->unsupportedTieGroups as $entryGroup) {
                $add('unsupported_ranking_tie', ['entry_ids' => $entryGroup]);
            }

            return new LeagueOfficializationReadiness($issues);
        }

        $ranking = $table->rows->map(fn (array $row): array => [
            'position' => (int) $row['position'],
            'source_entry_id' => (int) $row['entry_id'],
            'played' => (int) $row['played'],
            'wins' => (int) $row['wins'],
            'losses' => (int) $row['losses'],
            'points' => (int) $row['points'],
            'games_for' => (int) $row['games_for'],
            'games_against' => (int) $row['games_against'],
            'games_diff' => (int) $row['games_diff'],
        ])->all();

        return new LeagueOfficializationReadiness([], new LeagueOfficializationSource(
            $category,
            $championshipType->value,
            $this->scoreRules->targetScore($championshipType),
            new EloquentCollection($entries->all()),
            $canonicalEntries,
            $canonicalMatches,
            $ranking,
        ));
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
