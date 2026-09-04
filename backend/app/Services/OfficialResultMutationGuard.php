<?php

namespace App\Services;

use App\Enums\OfficialResultMutationImpact;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Round;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OfficialResultMutationGuard
{
    public function __construct(
        private readonly OfficialResultLockService $locks,
    ) {}

    public function lockAndGuard(
        Category|int $category,
        OfficialResultMutationImpact $impact,
    ): OfficialResultLock {
        $lock = $this->locks->lockCategoryAndCurrentOfficialResults($category);
        $this->assertAllowed($lock, $impact);

        return $lock;
    }

    /**
     * @return Collection<int, OfficialResultLock>
     */
    public function lockAndGuardCategories(
        iterable $categoryIds,
        OfficialResultMutationImpact $impact,
    ): Collection {
        $locks = $this->locks->lockCategoriesAndCurrentOfficialResults($categoryIds);

        $locks->each(fn (OfficialResultLock $lock) => $this->assertAllowed($lock, $impact));

        return $locks;
    }

    public function lockAndGuardMatch(GameMatch|int $match): GameMatch
    {
        $this->locks->assertInsideTransaction();

        $matchId = $match instanceof GameMatch ? $match->getKey() : $match;
        $coordinates = DB::table('game_matches')
            ->join('rounds', 'rounds.id', '=', 'game_matches.round_id')
            ->where('game_matches.id', $matchId)
            ->select('game_matches.round_id', 'rounds.category_id')
            ->first();

        if ($coordinates === null) {
            throw (new ModelNotFoundException)->setModel(GameMatch::class, [$matchId]);
        }

        $categoryLock = $this->locks->lockCategoryAndCurrentOfficialResults(
            (int) $coordinates->category_id
        );

        /** @var Round $lockedRound */
        $lockedRound = Round::query()
            ->whereKey((int) $coordinates->round_id)
            ->where('category_id', $categoryLock->category->id)
            ->lockForUpdate()
            ->firstOrFail();

        /** @var GameMatch $lockedMatch */
        $lockedMatch = GameMatch::query()
            ->whereKey($matchId)
            ->where('round_id', $lockedRound->id)
            ->lockForUpdate()
            ->firstOrFail();

        $impact = $this->classifyMatchImpact($lockedRound);

        if ($impact !== null) {
            $this->assertAllowed($categoryLock, $impact);
        }

        $lockedRound->setRelation('category', $categoryLock->category);
        $lockedMatch->setRelation('round', $lockedRound);

        return $lockedMatch;
    }

    public function assertAllowed(
        OfficialResultLock $lock,
        OfficialResultMutationImpact $impact,
    ): void {
        $blockingParts = $impact->blockingParts();

        $blocked = $lock->currentOfficialResults->contains(
            static fn ($result): bool => in_array($result->competition_part, $blockingParts, true)
        );

        if ($blocked) {
            throw new OfficialResultMutationBlockedException;
        }
    }

    private function classifyMatchImpact(Round $round): ?OfficialResultMutationImpact
    {
        if ($round->type === 'league') {
            return OfficialResultMutationImpact::LEAGUE_RESULT;
        }

        if ($round->type === 'cup' && $round->phase === 'cup') {
            if (in_array($round->stage, ['semifinal', 'final'], true)) {
                return OfficialResultMutationImpact::CUP_DECISIVE;
            }

            if ($round->stage === 'third_place') {
                return null;
            }
        }

        return OfficialResultMutationImpact::AMBIGUOUS_MATCH;
    }
}
