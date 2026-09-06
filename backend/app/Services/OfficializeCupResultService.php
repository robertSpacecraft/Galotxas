<?php

namespace App\Services;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\CupAlreadyOfficialException;
use App\Exceptions\CupOfficializationNotReadyException;
use App\Exceptions\OfficialResultConcurrencyConflictException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OfficializeCupResultService
{
    public function __construct(
        private readonly OfficialResultLockService $locks,
        private readonly EvaluateCupOfficializationReadinessService $readiness,
        private readonly OfficialResultActorSnapshotService $actorSnapshots,
        private readonly OfficialResultIdentitySnapshotService $identitySnapshots,
        private readonly OfficialResultSourceDigestService $digests,
        private readonly CupOfficialResultAggregateValidator $aggregateValidator,
    ) {}

    public function officialize(Category|int $category, User $actor): CategoryOfficialResult
    {
        try {
            return DB::transaction(function () use ($category, $actor): CategoryOfficialResult {
                $categoryLock = $this->locks->lockCategoryAndCurrentOfficialResults($category);

                if ($categoryLock->currentOfficialResults->contains(
                    fn (CategoryOfficialResult $result): bool => $result->competition_part === OfficialResultCompetitionPart::CUP
                )) {
                    throw new CupAlreadyOfficialException;
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
                    throw new CupOfficializationNotReadyException($readiness);
                }

                $source = $readiness->source;
                $identityLock = $this->locks->lockIdentitySources(
                    $source->championPlayerSourceIds(),
                    $actor,
                );
                $actorName = $this->actorSnapshots->snapshot($identityLock->actor);
                $now = CarbonImmutable::now();
                $championEntry = $this->attachChampionIdentitySource(
                    $source,
                    $participants,
                    $identityLock,
                );
                $identity = $this->identitySnapshots->snapshot($championEntry, $now);
                $championSource = collect($source->seed)->firstWhere(
                    'source_entry_id',
                    $source->champion['source_entry_id'],
                );

                if ($championSource === null) {
                    throw new OfficialResultSourceIntegrityException;
                }

                $digest = $this->digests->cupDigest($source);
                $nextVersion = $this->nextVersion($categoryId);
                $result = CategoryOfficialResult::query()->create([
                    'category_id' => $categoryId,
                    'competition_part' => OfficialResultCompetitionPart::CUP->value,
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

                $result->cupWinner()->create([
                    'source_entry_id' => $source->champion['source_entry_id'],
                    'source_player_id' => $championSource['source_player_id'],
                    'source_team_id' => $championSource['source_team_id'],
                    'entry_type' => $championSource['entry_type'],
                    'source_final_match_id' => $source->champion['source_final_match_id'],
                    'identity_projection' => $identity->projection->value,
                    'display_name_snapshot' => $identity->displayName,
                    'public_display_name' => $identity->publicDisplayName,
                    'public_anonymized_at' => null,
                ]);
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
    private function attachChampionIdentitySource(
        CupOfficializationSource $source,
        array $participants,
        OfficialResultIdentityLock $identityLock,
    ): CategoryEntry {
        $entry = $source->championEntryModel();
        if ($entry === null) {
            throw new OfficialResultSourceIntegrityException('Falta la entrada fuente del campeón Cup.');
        }

        if ($entry->entry_type === 'player') {
            $player = $identityLock->players->firstWhere('id', $entry->player_id);
            if ($player === null) {
                throw new OfficialResultSourceIntegrityException('Falta el jugador fuente del campeón Cup.');
            }
            $entry->setRelation('player', $player);
        } else {
            $team = $participants['teams']->firstWhere('id', $entry->team_id);
            if ($team === null) {
                throw new OfficialResultSourceIntegrityException('Falta el equipo fuente del campeón Cup.');
            }
            $entry->setRelation('team', $team);
        }

        return $entry;
    }

    private function nextVersion(int $categoryId): int
    {
        $history = CategoryOfficialResult::query()
            ->where('category_id', $categoryId)
            ->cup()
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
                throw new OfficialResultSourceIntegrityException(
                    'El histórico Cup no forma una secuencia reopened íntegra 1..N.'
                );
            }

            $this->aggregateValidator->validate($result);
        }

        return $history->isEmpty() ? 1 : ((int) $history->last()->version) + 1;
    }

    private function assertAggregate(
        CategoryOfficialResult $result,
        CupOfficializationSource $source,
    ): void {
        $result->refresh();
        $this->aggregateValidator->validate($result);
        $expectedMatches = collect($source->matches)
            ->pluck('source_game_match_id')
            ->sort()
            ->values()
            ->all();

        if (
            $result->status !== OfficialResultStatus::OFFICIAL
            || (int) $result->getAttribute('current_slot') !== 1
            || (int) $result->cupWinner->source_entry_id !== $source->champion['source_entry_id']
            || (int) $result->cupWinner->source_final_match_id !== $source->champion['source_final_match_id']
            || $result->matchSnapshots->pluck('source_game_match_id')->sort()->values()->all() !== $expectedMatches
        ) {
            throw new OfficialResultSourceIntegrityException(
                'El agregado Cup persistido no coincide con su fuente.'
            );
        }
    }

    private function isKnownConcurrencyConstraint(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'category_official_results_current_unique')
            || str_contains($message, 'category_official_results_version_unique');
    }
}
