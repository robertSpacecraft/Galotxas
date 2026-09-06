<?php

namespace App\Services;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Exceptions\NoCurrentCupOfficialResultException;
use App\Exceptions\OfficialResultConcurrencyConflictException;
use App\Exceptions\OfficialResultSourceIntegrityException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReopenCupResultService
{
    public function __construct(
        private readonly OfficialResultLockService $locks,
        private readonly OfficialResultActorSnapshotService $actorSnapshots,
        private readonly OfficialResultReopenReasonService $reasons,
        private readonly CupOfficialResultAggregateValidator $aggregateValidator,
    ) {}

    public function reopen(
        Category|int $category,
        User $actor,
        string $reason,
        CategoryOfficialResult|int|null $expectedResult = null,
    ): CategoryOfficialResult {
        $expectedResultId = $expectedResult instanceof CategoryOfficialResult
            ? (int) $expectedResult->id
            : $expectedResult;

        return DB::transaction(function () use ($category, $actor, $reason, $expectedResultId): CategoryOfficialResult {
            $categoryLock = $this->locks->lockCategoryAndCurrentOfficialResults($category);
            $cupResults = $categoryLock->currentOfficialResults
                ->filter(fn (CategoryOfficialResult $result): bool => $result->competition_part === OfficialResultCompetitionPart::CUP)
                ->values();

            if ($cupResults->count() !== 1) {
                if ($cupResults->isEmpty()) {
                    throw new NoCurrentCupOfficialResultException;
                }

                throw new OfficialResultSourceIntegrityException('Existe más de un resultado Cup vigente.');
            }

            /** @var CategoryOfficialResult $result */
            $result = $cupResults->first();
            if ($expectedResultId !== null && (int) $result->id !== $expectedResultId) {
                throw new OfficialResultConcurrencyConflictException;
            }

            $identityLock = $this->locks->lockIdentitySources([], $actor);
            $actorName = $this->actorSnapshots->snapshot($identityLock->actor);
            $normalizedReason = $this->reasons->normalize($reason);
            $now = CarbonImmutable::now();
            $this->aggregateValidator->validate($result);

            $original = [
                'officialized_at' => $result->officialized_at?->format('Y-m-d H:i:s.u'),
                'officialized_by_user_id' => $result->officialized_by_user_id,
                'officialized_by_name_snapshot' => $result->officialized_by_name_snapshot,
                'source_digest' => $result->source_digest,
                'cup_winner' => $result->cupWinner->getAttributes(),
                'match_snapshots' => $result->matchSnapshots->map->getAttributes()->all(),
            ];

            $result->forceFill([
                'status' => OfficialResultStatus::REOPENED,
                'reopened_at' => $now,
                'reopened_by_user_id' => $identityLock->actor->id,
                'reopened_by_name_snapshot' => $actorName,
                'reopen_reason' => $normalizedReason,
            ])->save();

            $result->refresh();
            $this->aggregateValidator->validate($result);

            if (
                $result->status !== OfficialResultStatus::REOPENED
                || $result->getAttribute('current_slot') !== null
                || $result->officialized_at?->format('Y-m-d H:i:s.u') !== $original['officialized_at']
                || $result->officialized_by_user_id !== $original['officialized_by_user_id']
                || $result->officialized_by_name_snapshot !== $original['officialized_by_name_snapshot']
                || $result->source_digest !== $original['source_digest']
                || $result->cupWinner->getAttributes() !== $original['cup_winner']
                || $result->matchSnapshots->map->getAttributes()->all() !== $original['match_snapshots']
            ) {
                throw new OfficialResultSourceIntegrityException(
                    'La reapertura Cup alteró evidencia histórica inmutable.'
                );
            }

            return $result;
        });
    }
}
