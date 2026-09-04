<?php

namespace App\Services;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\NoCurrentLeagueOfficialResultException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReopenLeagueResultService
{
    public function __construct(
        private readonly OfficialResultLockService $locks,
        private readonly OfficialResultActorSnapshotService $actorSnapshots,
        private readonly OfficialResultReopenReasonService $reasons,
    ) {}

    public function reopen(
        Category|int $category,
        User $actor,
        string $reason,
    ): CategoryOfficialResult {
        return DB::transaction(function () use ($category, $actor, $reason): CategoryOfficialResult {
            $categoryLock = $this->locks->lockCategoryAndCurrentOfficialResults($category);
            $leagueResults = $categoryLock->currentOfficialResults
                ->filter(fn (CategoryOfficialResult $result): bool => $result->competition_part === OfficialResultCompetitionPart::LEAGUE)
                ->values();

            if ($leagueResults->count() !== 1) {
                if ($leagueResults->isEmpty()) {
                    throw new NoCurrentLeagueOfficialResultException;
                }

                throw new OfficialResultSourceIntegrityException('Existe más de un resultado League vigente.');
            }

            /** @var CategoryOfficialResult $result */
            $result = $leagueResults->first();
            $identityLock = $this->locks->lockIdentitySources([], $actor);
            $actorName = $this->actorSnapshots->snapshot($identityLock->actor);
            $normalizedReason = $this->reasons->normalize($reason);
            $now = CarbonImmutable::now();

            $original = [
                'officialized_at' => $result->officialized_at?->format('Y-m-d H:i:s.u'),
                'officialized_by_user_id' => $result->officialized_by_user_id,
                'officialized_by_name_snapshot' => $result->officialized_by_name_snapshot,
                'source_digest' => $result->source_digest,
                'league_rows' => $result->leagueRows()->orderBy('id')->get()->map->getAttributes()->all(),
                'match_snapshots' => $result->matchSnapshots()->orderBy('id')->get()->map->getAttributes()->all(),
            ];

            $result->forceFill([
                'status' => OfficialResultStatus::REOPENED,
                'reopened_at' => $now,
                'reopened_by_user_id' => $identityLock->actor->id,
                'reopened_by_name_snapshot' => $actorName,
                'reopen_reason' => $normalizedReason,
            ])->save();

            $result->refresh();
            $result->load([
                'leagueRows' => fn ($query) => $query->orderBy('id'),
                'matchSnapshots' => fn ($query) => $query->orderBy('id'),
            ]);

            if (
                $result->status !== OfficialResultStatus::REOPENED
                || $result->getAttribute('current_slot') !== null
                || $result->officialized_at?->format('Y-m-d H:i:s.u') !== $original['officialized_at']
                || $result->officialized_by_user_id !== $original['officialized_by_user_id']
                || $result->officialized_by_name_snapshot !== $original['officialized_by_name_snapshot']
                || $result->source_digest !== $original['source_digest']
                || $result->leagueRows->map->getAttributes()->all() !== $original['league_rows']
                || $result->matchSnapshots->map->getAttributes()->all() !== $original['match_snapshots']
            ) {
                throw new OfficialResultSourceIntegrityException('La reapertura alteró evidencia histórica inmutable.');
            }

            return $result;
        });
    }
}
