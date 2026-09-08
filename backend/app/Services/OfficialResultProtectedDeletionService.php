<?php

namespace App\Services;

use App\Enums\OfficialResultMutationImpact;
use App\Exceptions\OfficialResultHistoryDeletionBlockedException;
use App\Models\Category;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\Season;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OfficialResultProtectedDeletionService
{
    public function __construct(
        private readonly CompetitionImageService $covers,
        private readonly OfficialResultMutationGuard $guard,
        private readonly OfficialResultLockService $locks,
    ) {}

    public function deleteCategory(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $this->guardAndLockForDeletion([$category->id]);
            $locked = Category::query()->lockForUpdate()->findOrFail($category->id);
            $oldKey = $locked->image_path;
            $this->deleteAndCleanup($locked, [$oldKey]);
        });
    }

    public function deleteChampionship(Championship $championship): void
    {
        DB::transaction(function () use ($championship): void {
            $championship = Championship::query()->lockForUpdate()->findOrFail($championship->id);
            $categoryIds = Category::query()
                ->where('championship_id', $championship->id)
                ->orderBy('id')
                ->pluck('id');

            $this->guardAndLockForDeletion($categoryIds);
            $keys = Category::query()->whereKey($categoryIds)->lockForUpdate()->pluck('image_path');
            $keys->push($championship->image_path);
            $this->deleteAndCleanup($championship, $keys);
        });
    }

    public function deleteSeason(Season $season): void
    {
        DB::transaction(function () use ($season): void {
            $season = Season::query()->lockForUpdate()->findOrFail($season->id);
            $championships = $season->championships()->orderBy('id')->lockForUpdate()->get();
            $categoryIds = Category::query()
                ->whereHas('championship', fn ($query) => $query->where('season_id', $season->id))
                ->orderBy('id')
                ->pluck('id');

            $this->guardAndLockForDeletion($categoryIds);
            $keys = Category::query()->whereKey($categoryIds)->lockForUpdate()->pluck('image_path');
            $keys = $keys->concat($championships->pluck('image_path'))->push($season->image_path);
            $this->deleteAndCleanup($season, $keys);
        });
    }

    private function deleteAndCleanup(Season|Championship|Category $entity, iterable $keys): void
    {
        if (! $entity->delete()) {
            throw new RuntimeException('No se pudo eliminar la entidad de competición.');
        }

        foreach (collect($keys)->filter(fn ($key) => $this->covers->isManaged($key))->uniqueStrict() as $key) {
            $this->covers->cleanupAfterCommit($key);
        }
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
