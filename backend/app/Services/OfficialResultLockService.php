<?php

namespace App\Services;

use App\Enums\OfficialResultStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\Round;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class OfficialResultLockService
{
    /**
     * @return Collection<int, OfficialResultLock>
     */
    public function lockCategoriesAndCurrentOfficialResults(iterable $categoryIds): Collection
    {
        $this->assertInsideTransaction();

        $ids = collect($categoryIds)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return new Collection;
        }

        /** @var EloquentCollection<int, Category> $categories */
        $categories = Category::query()
            ->whereKey($ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var EloquentCollection<int, CategoryOfficialResult> $currentResults */
        $currentResults = CategoryOfficialResult::query()
            ->whereIn('category_id', $categories->modelKeys())
            ->where('status', OfficialResultStatus::OFFICIAL->value)
            ->orderBy('category_id')
            ->orderByRaw("case competition_part when 'league' then 1 when 'cup' then 2 else 3 end")
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $resultsByCategory = $currentResults->groupBy('category_id');

        return $categories
            ->map(fn (Category $category): OfficialResultLock => new OfficialResultLock(
                $category,
                new EloquentCollection($resultsByCategory->get($category->id, collect())->all()),
            ));
    }

    public function lockCategoryAndCurrentOfficialResults(Category|int $category): OfficialResultLock
    {
        $categoryId = $category instanceof Category ? $category->getKey() : $category;

        /** @var OfficialResultLock $lock */
        $lock = $this->lockCategoriesAndCurrentOfficialResults([$categoryId])->firstOrFail();

        return $lock;
    }

    /**
     * Lock the mutable competition rows after categories and current official results.
     *
     * @return array{rounds: EloquentCollection<int, Round>, matches: EloquentCollection<int, GameMatch>}
     */
    public function lockRoundsAndMatches(iterable $categoryIds): array
    {
        $this->assertInsideTransaction();

        $ids = collect($categoryIds)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return ['rounds' => new EloquentCollection, 'matches' => new EloquentCollection];
        }

        /** @var EloquentCollection<int, Round> $rounds */
        $rounds = Round::query()
            ->whereIn('category_id', $ids)
            ->orderBy('category_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var EloquentCollection<int, GameMatch> $matches */
        $matches = $rounds->isEmpty()
            ? new EloquentCollection
            : GameMatch::query()
                ->whereIn('round_id', $rounds->modelKeys())
                ->orderBy('round_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        return ['rounds' => $rounds, 'matches' => $matches];
    }

    /**
     * Lock participant composition only after rounds and matches have been locked.
     *
     * @return array{entries: EloquentCollection<int, CategoryEntry>, teams: EloquentCollection<int, Team>}
     */
    public function lockEntriesAndTeams(iterable $categoryIds): array
    {
        $this->assertInsideTransaction();

        $ids = collect($categoryIds)
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($ids === []) {
            return ['entries' => new EloquentCollection, 'teams' => new EloquentCollection];
        }

        /** @var EloquentCollection<int, CategoryEntry> $entries */
        $entries = CategoryEntry::query()
            ->whereIn('category_id', $ids)
            ->orderBy('category_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var EloquentCollection<int, Team> $teams */
        $teams = Team::query()
            ->whereIn('category_id', $ids)
            ->orderBy('category_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        return ['entries' => $entries, 'teams' => $teams];
    }

    public function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Los locks de resultados oficiales requieren una transacción activa.');
        }
    }
}
