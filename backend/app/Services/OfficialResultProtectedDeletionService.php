<?php

namespace App\Services;

use App\Enums\OfficialResultMutationImpact;
use App\Exceptions\OfficialResultHistoryDeletionBlockedException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

class OfficialResultProtectedDeletionService
{
    public function __construct(
        private readonly OfficialResultMutationGuard $guard,
        private readonly OfficialResultLockService $locks,
    ) {}

    public function deleteCategory(Category $category): void
    {
        $this->deleteCategories([$category->id], fn () => $category->delete());
    }

    public function deleteChampionship(Championship $championship): void
    {
        DB::transaction(function () use ($championship): void {
            $categoryIds = Category::query()
                ->where('championship_id', $championship->id)
                ->orderBy('id')
                ->pluck('id');

            $this->guardAndLockForDeletion($categoryIds);
            $championship->delete();
        });
    }

    public function deleteSeason(Season $season): void
    {
        DB::transaction(function () use ($season): void {
            $categoryIds = Category::query()
                ->whereHas('championship', fn ($query) => $query->where('season_id', $season->id))
                ->orderBy('id')
                ->pluck('id');

            $this->guardAndLockForDeletion($categoryIds);
            $season->delete();
        });
    }

    private function deleteCategories(iterable $categoryIds, callable $delete): void
    {
        DB::transaction(function () use ($categoryIds, $delete): void {
            $this->guardAndLockForDeletion($categoryIds);
            $delete();
        });
    }

    private function guardAndLockForDeletion(iterable $categoryIds): void
    {
        $ids = collect($categoryIds)->map(static fn ($id): int => (int) $id)->all();

        $this->guard->lockAndGuardCategories(
            $ids,
            OfficialResultMutationImpact::CATEGORY_DELETE
        );

        if (CategoryOfficialResult::query()->whereIn('category_id', $ids)->exists()) {
            throw new OfficialResultHistoryDeletionBlockedException;
        }

        $this->locks->lockRoundsAndMatches($ids);
        $this->locks->lockEntriesAndTeams($ids);
    }
}
