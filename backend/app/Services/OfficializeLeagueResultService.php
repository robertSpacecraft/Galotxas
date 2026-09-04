<?php

namespace App\Services;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\LeagueAlreadyOfficialException;
use App\Exceptions\LeagueOfficializationNotReadyException;
use App\Exceptions\OfficialResultConcurrencyConflictException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OfficializeLeagueResultService
{
    public function __construct(
        private readonly OfficialResultLockService $locks,
        private readonly EvaluateLeagueOfficializationReadinessService $readiness,
        private readonly OfficialResultActorSnapshotService $actorSnapshots,
        private readonly OfficialResultIdentitySnapshotService $identitySnapshots,
        private readonly OfficialResultSourceDigestService $digests,
    ) {}

    public function officialize(Category|int $category, User $actor): CategoryOfficialResult
    {
        try {
            return DB::transaction(function () use ($category, $actor): CategoryOfficialResult {
                $categoryLock = $this->locks->lockCategoryAndCurrentOfficialResults($category);

                if ($categoryLock->currentOfficialResults->contains(
                    fn (CategoryOfficialResult $result): bool => $result->competition_part === OfficialResultCompetitionPart::LEAGUE
                )) {
                    throw new LeagueAlreadyOfficialException;
                }

                $categoryId = (int) $categoryLock->category->id;
                $structure = $this->locks->lockRoundsAndMatches([$categoryId]);
                $participants = $this->locks->lockEntriesAndTeams([$categoryId]);
                $readiness = $this->readiness->evaluateLocked(
                    $categoryLock,
                    $structure,
                    $participants,
                );

                if (! $readiness->isReady()) {
                    throw new LeagueOfficializationNotReadyException($readiness);
                }

                $source = $readiness->source;
                $identityLock = $this->locks->lockIdentitySources(
                    $source->playerEntrySourceIds(),
                    $actor,
                );
                $actorName = $this->actorSnapshots->snapshot($identityLock->actor);
                $now = CarbonImmutable::now();
                $this->attachIdentitySources($source, $participants, $identityLock);

                $rowPayloads = [];
                $entrySources = collect($source->entries)->keyBy('source_entry_id');
                $entryModels = $source->entryModels->keyBy('id');

                foreach ($source->ranking as $rankingRow) {
                    $entryId = $rankingRow['source_entry_id'];
                    /** @var CategoryEntry|null $entry */
                    $entry = $entryModels->get($entryId);
                    $entrySource = $entrySources->get($entryId);

                    if ($entry === null || $entrySource === null) {
                        throw new OfficialResultSourceIntegrityException;
                    }

                    $identity = $this->identitySnapshots->snapshot($entry, $now);
                    $rowPayloads[] = [
                        'position' => $rankingRow['position'],
                        'source_entry_id' => $entryId,
                        'source_player_id' => $entrySource['source_player_id'],
                        'source_team_id' => $entrySource['source_team_id'],
                        'entry_type' => $entrySource['entry_type'],
                        'identity_projection' => $identity->projection->value,
                        'display_name_snapshot' => $identity->displayName,
                        'public_display_name' => $identity->publicDisplayName,
                        'public_anonymized_at' => null,
                        'played' => $rankingRow['played'],
                        'wins' => $rankingRow['wins'],
                        'losses' => $rankingRow['losses'],
                        'points' => $rankingRow['points'],
                        'games_for' => $rankingRow['games_for'],
                        'games_against' => $rankingRow['games_against'],
                        'games_diff' => $rankingRow['games_diff'],
                    ];
                }

                $digest = $this->digests->leagueDigest($source);
                $nextVersion = $this->nextVersion($categoryId);
                $result = CategoryOfficialResult::query()->create([
                    'category_id' => $categoryId,
                    'competition_part' => OfficialResultCompetitionPart::LEAGUE->value,
                    'version' => $nextVersion,
                    'status' => OfficialResultStatus::OFFICIAL->value,
                    'officialized_at' => $now,
                    'officialized_by_user_id' => $identityLock->actor->id,
                    'officialized_by_name_snapshot' => $actorName,
                    'reopened_at' => null,
                    'reopened_by_user_id' => null,
                    'reopened_by_name_snapshot' => null,
                    'reopen_reason' => null,
                    'source_digest' => $digest,
                ]);

                $result->leagueRows()->createMany($rowPayloads);
                $result->matchSnapshots()->createMany($source->matches);
                $this->assertAggregate($result, $source);

                return $result;
            });
        } catch (QueryException $exception) {
            if ($this->isKnownConcurrencyConstraint($exception)) {
                throw new OfficialResultConcurrencyConflictException;
            }

            throw $exception;
        }
    }

    /**
     * @param  array{entries: mixed, teams: mixed, team_members: mixed}  $participants
     */
    private function attachIdentitySources(
        LeagueOfficializationSource $source,
        array $participants,
        OfficialResultIdentityLock $identityLock,
    ): void {
        $players = $identityLock->players->keyBy('id');
        $teams = $participants['teams']->keyBy('id');

        foreach ($source->entryModels as $entry) {
            if ($entry->entry_type === 'player') {
                $player = $players->get($entry->player_id);
                if ($player === null) {
                    throw new OfficialResultSourceIntegrityException('Falta el jugador fuente de una entrada oficial.');
                }
                $entry->setRelation('player', $player);
            } else {
                $team = $teams->get($entry->team_id);
                if ($team === null) {
                    throw new OfficialResultSourceIntegrityException('Falta el equipo fuente de una entrada oficial.');
                }
                $entry->setRelation('team', $team);
            }
        }
    }

    private function nextVersion(int $categoryId): int
    {
        $history = CategoryOfficialResult::query()
            ->where('category_id', $categoryId)
            ->league()
            ->orderBy('version')
            ->get();

        foreach ($history->values() as $index => $result) {
            $expectedVersion = $index + 1;
            if (
                $result->version !== $expectedVersion
                || $result->status !== OfficialResultStatus::REOPENED
                || $result->officialized_at === null
                || trim((string) $result->officialized_by_name_snapshot) === ''
                || ! preg_match('/^[0-9a-f]{64}$/D', (string) $result->source_digest)
                || $result->reopened_at === null
                || trim((string) $result->reopened_by_name_snapshot) === ''
                || trim((string) $result->reopen_reason) === ''
            ) {
                throw new OfficialResultSourceIntegrityException('El histórico League no forma una secuencia reopened íntegra 1..N.');
            }
        }

        return $history->isEmpty() ? 1 : ((int) $history->last()->version) + 1;
    }

    private function assertAggregate(
        CategoryOfficialResult $result,
        LeagueOfficializationSource $source,
    ): void {
        $result->refresh();
        $result->load([
            'leagueRows' => fn ($query) => $query->orderBy('position'),
            'matchSnapshots' => fn ($query) => $query->orderBy('source_game_match_id'),
        ]);

        $expectedPositions = range(1, count($source->ranking));
        $expectedEntries = collect($source->ranking)->pluck('source_entry_id')->sort()->values()->all();
        $expectedMatches = collect($source->matches)->pluck('source_game_match_id')->sort()->values()->all();

        if (
            $result->competition_part !== OfficialResultCompetitionPart::LEAGUE
            || $result->status !== OfficialResultStatus::OFFICIAL
            || (int) $result->getAttribute('current_slot') !== 1
            || $result->leagueRows->count() !== count($source->ranking)
            || $result->matchSnapshots->count() !== count($source->matches)
            || $result->leagueRows->pluck('position')->all() !== $expectedPositions
            || $result->leagueRows->pluck('source_entry_id')->sort()->values()->all() !== $expectedEntries
            || $result->matchSnapshots->pluck('source_game_match_id')->sort()->values()->all() !== $expectedMatches
        ) {
            throw new OfficialResultSourceIntegrityException('El agregado League persistido no coincide con su fuente.');
        }
    }

    private function isKnownConcurrencyConstraint(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'category_official_results_current_unique')
            || str_contains($message, 'category_official_results_version_unique');
    }
}
