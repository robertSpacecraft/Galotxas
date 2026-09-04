<?php

namespace App\Services;

use App\Enums\OfficialResultStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PublicIdentityAuthorization;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
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
     * @return array{entries: EloquentCollection<int, CategoryEntry>, teams: EloquentCollection<int, Team>, team_members: Collection<int, object>}
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
            return [
                'entries' => new EloquentCollection,
                'teams' => new EloquentCollection,
                'team_members' => new Collection,
            ];
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

        $teamMembers = $teams->isEmpty()
            ? new Collection
            : DB::table('team_members')
                ->whereIn('team_id', $teams->modelKeys())
                ->orderBy('team_id')
                ->orderBy('player_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        return ['entries' => $entries, 'teams' => $teams, 'team_members' => $teamMembers];
    }

    /**
     * Lock only identity sources, after the competition rows and teams.
     *
     * @param  iterable<int>  $playerIds
     */
    public function lockIdentitySources(
        iterable $playerIds,
        User|int $actor,
    ): OfficialResultIdentityLock {
        $this->assertInsideTransaction();

        $ids = collect($playerIds)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();
        $actorId = (int) ($actor instanceof User ? $actor->getKey() : $actor);

        /** @var EloquentCollection<int, PublicIdentityAuthorization> $authorizations */
        $authorizations = $ids->isEmpty()
            ? new EloquentCollection
            : PublicIdentityAuthorization::query()
                ->whereIn('player_id', $ids->all())
                ->orderBy('player_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        /** @var EloquentCollection<int, Player> $players */
        $players = $ids->isEmpty()
            ? new EloquentCollection
            : Player::query()
                ->whereKey($ids->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $userIds = $players->pluck('user_id')
            ->push($actorId)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();

        /** @var EloquentCollection<int, User> $users */
        $users = $userIds->isEmpty()
            ? new EloquentCollection
            : User::query()
                ->whereKey($userIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

        $usersById = $users->keyBy('id');
        foreach ($players as $player) {
            $player->setRelation('user', $usersById->get($player->user_id));
            $player->setRelation(
                'publicIdentityAuthorizations',
                new EloquentCollection(
                    $authorizations->where('player_id', $player->id)->values()->all()
                ),
            );
        }

        return new OfficialResultIdentityLock(
            $players,
            $authorizations,
            $users,
            $usersById->get($actorId),
        );
    }

    public function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Los locks de resultados oficiales requieren una transacción activa.');
        }
    }
}
