<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Enums\OfficialResultMutationImpact;
use App\Exceptions\CategoryEntryIntegrityException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single enforcement point for the competitive identity of a CategoryEntry.
 *
 * A CategoryEntry is exactly one player (singles) or one team (doubles), at most
 * once per category regardless of status. Every mutation requires the lock
 * returned by lockForParticipantMutation(), which applies the official-result
 * guard and the canonical lock order, so writers cannot skip either.
 */
class CategoryEntryService
{
    public const APPROVED = 'approved';

    public const PLAYER_UNIQUE_INDEX = 'category_entries_category_player_unique';

    public const TEAM_UNIQUE_INDEX = 'category_entries_category_team_unique';

    public const IDENTITY_CHECK = 'category_entries_identity_check';

    public const REGISTRATION_UNIQUE_INDEX = 'category_registrations_unique_player_per_category';

    public function __construct(
        private readonly OfficialResultMutationGuard $guard,
        private readonly OfficialResultLockService $locks,
    ) {}

    /**
     * Category, current official results, rounds, matches, entries, teams and
     * team members, in that order. Must run inside a transaction and before any
     * consistent read of the mutation, so later reads observe committed data.
     */
    public function lockForParticipantMutation(Category $category): OfficialResultLock
    {
        $lock = $this->guard->lockAndGuard($category, OfficialResultMutationImpact::PARTICIPANTS);
        $this->locks->lockRoundsAndMatches([$lock->category->id]);
        $this->locks->lockEntriesAndTeams([$lock->category->id]);

        return $lock;
    }

    /**
     * Modality read under the category lock: a championship type change needs
     * every category lock, so it cannot commit while a participant writer holds one.
     */
    public function modality(OfficialResultLock $lock): ChampionshipType
    {
        $this->locks->assertInsideTransaction();

        return Championship::query()
            ->select(['id', 'type'])
            ->findOrFail($lock->category->championship_id)
            ->type;
    }

    public function create(
        OfficialResultLock $lock,
        string $entryType,
        ?int $playerId,
        ?int $teamId,
    ): CategoryEntry {
        $this->locks->assertInsideTransaction();

        $this->assertIdentityShape($entryType, $playerId, $teamId);
        $this->assertModality($this->modality($lock), $entryType);

        if ($entryType === 'player') {
            $this->assertPlayerCanEnter($lock->category, (int) $playerId);
        } else {
            $this->assertTeamCanEnter($lock->category, (int) $teamId);
        }

        try {
            return CategoryEntry::query()->create([
                'category_id' => $lock->category->id,
                'entry_type' => $entryType,
                'player_id' => $playerId,
                'team_id' => $teamId,
                'status' => self::APPROVED,
            ]);
        } catch (QueryException $exception) {
            throw $this->integrityViolationFrom($exception) ?? $exception;
        }
    }

    public function createForPlayer(OfficialResultLock $lock, int $playerId): CategoryEntry
    {
        return $this->create($lock, 'player', $playerId, null);
    }

    public function createForTeam(OfficialResultLock $lock, int $teamId): CategoryEntry
    {
        return $this->create($lock, 'team', null, $teamId);
    }

    /**
     * Registration flow. Creates the approved player entry when the category has
     * none for the player, and is idempotent only for an entry that is already
     * exactly coherent. Any other existing row fails closed: it is neither
     * promoted, replaced nor duplicated, so the caller's transaction rolls back.
     */
    public function ensureForPlayer(OfficialResultLock $lock, int $playerId): CategoryEntry
    {
        $this->locks->assertInsideTransaction();

        $existing = CategoryEntry::query()
            ->where('category_id', $lock->category->id)
            ->where('player_id', $playerId)
            ->orderBy('id')
            ->get();

        if ($existing->isEmpty()) {
            return $this->createForPlayer($lock, $playerId);
        }

        if ($existing->count() > 1) {
            throw CategoryEntryIntegrityException::existingEntryIncompatible();
        }

        $entry = $existing->first();
        $this->assertReusablePlayerEntry($entry, $playerId);

        return $entry;
    }

    /**
     * The only existing entry the registration flow accepts as its own: a player
     * entry for exactly this player, without a team, already approved.
     */
    public function assertReusablePlayerEntry(CategoryEntry $entry, int $playerId): void
    {
        $coherent = $entry->entry_type === 'player'
            && (int) $entry->player_id === $playerId
            && $entry->team_id === null
            && $entry->status === self::APPROVED;

        if (! $coherent) {
            throw CategoryEntryIntegrityException::existingEntryIncompatible();
        }
    }

    public function deleteForPlayer(OfficialResultLock $lock, int $playerId): int
    {
        $this->locks->assertInsideTransaction();

        return CategoryEntry::query()
            ->where('category_id', $lock->category->id)
            ->where('entry_type', 'player')
            ->where('player_id', $playerId)
            ->delete();
    }

    public function deleteForTeam(OfficialResultLock $lock, int $teamId): int
    {
        $this->locks->assertInsideTransaction();

        return CategoryEntry::query()
            ->where('category_id', $lock->category->id)
            ->where('entry_type', 'team')
            ->where('team_id', $teamId)
            ->delete();
    }

    /**
     * @param  list<int>  $playerIds
     */
    public function assertPlayersRegistered(OfficialResultLock $lock, array $playerIds): void
    {
        $this->assertTeamPlayersRegistered($lock->category, collect($playerIds));
    }

    /**
     * @return Collection<int, int>
     */
    public function assignedTeamPlayerIds(OfficialResultLock $lock): Collection
    {
        return DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('teams.category_id', $lock->category->id)
            ->pluck('team_members.player_id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * Turns a DB integrity failure raised by a race or a direct write into the
     * same controlled exception the application-level checks throw.
     */
    public function integrityViolationFrom(QueryException $exception): ?CategoryEntryIntegrityException
    {
        $code = $exception->errorInfo[1] ?? null;
        $message = $exception->getMessage();

        if ($code === 1062 && str_contains($message, self::PLAYER_UNIQUE_INDEX)) {
            return CategoryEntryIntegrityException::duplicatePlayer();
        }

        if ($code === 1062 && str_contains($message, self::TEAM_UNIQUE_INDEX)) {
            return CategoryEntryIntegrityException::duplicateTeam();
        }

        if ($code === 4025 && str_contains($message, self::IDENTITY_CHECK)) {
            return CategoryEntryIntegrityException::invalidIdentity();
        }

        return null;
    }

    public function isDuplicateRegistration(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), self::REGISTRATION_UNIQUE_INDEX);
    }

    private function assertIdentityShape(string $entryType, ?int $playerId, ?int $teamId): void
    {
        if (! in_array($entryType, ['player', 'team'], true)) {
            throw CategoryEntryIntegrityException::invalidIdentity();
        }

        if (($playerId === null) === ($teamId === null)) {
            throw CategoryEntryIntegrityException::invalidIdentity();
        }

        if (($entryType === 'player') !== ($playerId !== null)) {
            throw CategoryEntryIntegrityException::typeMismatch();
        }
    }

    private function assertModality(ChampionshipType $modality, string $entryType): void
    {
        if ($modality === ChampionshipType::SINGLES && $entryType !== 'player') {
            throw CategoryEntryIntegrityException::playerModalityRequired();
        }

        if ($modality === ChampionshipType::DOUBLES && $entryType !== 'team') {
            throw CategoryEntryIntegrityException::teamModalityRequired();
        }
    }

    private function assertPlayerCanEnter(Category $category, int $playerId): void
    {
        if (! Player::query()->whereKey($playerId)->exists()) {
            throw CategoryEntryIntegrityException::playerNotFound();
        }

        if (CategoryEntry::query()
            ->where('category_id', $category->id)
            ->where('player_id', $playerId)
            ->exists()) {
            throw CategoryEntryIntegrityException::duplicatePlayer();
        }

        $registered = CategoryRegistration::query()
            ->where('category_id', $category->id)
            ->where('player_id', $playerId)
            ->where('status', 'approved')
            ->exists();

        if (! $registered) {
            throw CategoryEntryIntegrityException::playerNotRegistered();
        }
    }

    private function assertTeamCanEnter(Category $category, int $teamId): void
    {
        /** @var Team|null $team */
        $team = Team::query()->find($teamId);

        if ($team === null) {
            throw CategoryEntryIntegrityException::teamNotFound();
        }

        if ((int) $team->category_id !== (int) $category->id) {
            throw CategoryEntryIntegrityException::teamOutsideCategory();
        }

        if (CategoryEntry::query()
            ->where('category_id', $category->id)
            ->where('team_id', $teamId)
            ->exists()) {
            throw CategoryEntryIntegrityException::duplicateTeam();
        }

        $members = DB::table('team_members')
            ->where('team_id', $teamId)
            ->get(['player_id', 'role_in_team']);
        $memberIds = $members->pluck('player_id')->map(fn ($id): int => (int) $id);

        if (
            $members->count() !== 2
            || $memberIds->unique()->count() !== 2
            || $members->pluck('role_in_team')->sort()->values()->all() !== ['back', 'front']
        ) {
            throw CategoryEntryIntegrityException::invalidTeamComposition();
        }

        $this->assertTeamPlayersRegistered($category, $memberIds);
    }

    /**
     * @param  Collection<int, int|string>  $playerIds
     */
    private function assertTeamPlayersRegistered(Category $category, Collection $playerIds): void
    {
        $expected = $playerIds->map(fn ($id): int => (int) $id)->unique();

        $registered = CategoryRegistration::query()
            ->where('category_id', $category->id)
            ->where('status', 'approved')
            ->whereIn('player_id', $expected->all())
            ->pluck('player_id')
            ->map(fn ($id): int => (int) $id)
            ->unique();

        if ($registered->count() !== $expected->count()) {
            throw CategoryEntryIntegrityException::teamPlayersNotRegistered();
        }
    }
}
