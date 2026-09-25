<?php

namespace Tests\Feature;

use App\Enums\ChampionshipType;
use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\CategoryEntryIntegrityException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Player;
use App\Models\Team;
use App\Services\CategoryEntryService;
use App\Services\OfficialResultLock;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCategoryParticipantFixtures;
use Tests\TestCase;

class CategoryEntryServiceTest extends TestCase
{
    use CreatesCategoryParticipantFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, Closure(int, int): array{string, ?int, ?int}, string}>
     */
    public static function invalidIdentities(): array
    {
        return [
            'both identities' => [
                'Un participante debe referenciar exactamente un jugador o un equipo.',
                'entry_type',
                fn (int $player, int $team): array => ['player', $player, $team],
                'singles',
            ],
            'no identity' => [
                'Un participante debe referenciar exactamente un jugador o un equipo.',
                'entry_type',
                fn (int $player, int $team): array => ['player', null, null],
                'singles',
            ],
            'unknown type' => [
                'Un participante debe referenciar exactamente un jugador o un equipo.',
                'entry_type',
                fn (int $player, int $team): array => ['duo', $player, null],
                'singles',
            ],
            'player type with a team source' => [
                'El tipo de participante no coincide con el jugador o el equipo indicado.',
                'entry_type',
                fn (int $player, int $team): array => ['player', null, $team],
                'singles',
            ],
            'team type with a player source' => [
                'El tipo de participante no coincide con el jugador o el equipo indicado.',
                'entry_type',
                fn (int $player, int $team): array => ['team', $player, null],
                'doubles',
            ],
        ];
    }

    #[DataProvider('invalidIdentities')]
    public function test_the_service_alone_rejects_invalid_identities(
        string $message,
        string $field,
        Closure $identity,
        string $modality,
    ): void {
        $category = $this->categoryOfType($modality);
        $player = $this->registeredPlayer($category);
        $team = Team::factory()->create(['category_id' => $category->id]);
        [$type, $playerId, $teamId] = $identity($player->id, $team->id);

        try {
            $this->inLock($category, fn ($lock) => $this->service()->create($lock, $type, $playerId, $teamId));
            $this->fail('El servicio debía rechazar la identidad.');
        } catch (CategoryEntryIntegrityException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame($field, $exception->field);
        }

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_the_service_creates_approved_entries_for_each_modality(): void
    {
        $singles = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($singles);
        $doubles = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($doubles)['team'];

        $playerEntry = $this->inLock($singles, fn ($lock) => $this->service()->createForPlayer($lock, $player->id));
        $teamEntry = $this->inLock($doubles, fn ($lock) => $this->service()->createForTeam($lock, $team->id));

        $this->assertSame(['player', $player->id, null, 'approved'], [
            $playerEntry->entry_type, $playerEntry->player_id, $playerEntry->team_id, $playerEntry->status,
        ]);
        $this->assertSame(['team', null, $team->id, 'approved'], [
            $teamEntry->entry_type, $teamEntry->player_id, $teamEntry->team_id, $teamEntry->status,
        ]);
    }

    public function test_a_missing_player_or_team_is_a_controlled_error_not_a_foreign_key_failure(): void
    {
        $singles = $this->categoryOfType('singles');
        $doubles = $this->categoryOfType('doubles');

        foreach ([
            [$singles, fn ($lock) => $this->service()->createForPlayer($lock, 999999), 'El jugador indicado no existe.'],
            [$doubles, fn ($lock) => $this->service()->createForTeam($lock, 999999), 'El equipo indicado no existe.'],
        ] as [$category, $action, $message]) {
            try {
                $this->inLock($category, $action);
                $this->fail('Debía fallar de forma controlada.');
            } catch (CategoryEntryIntegrityException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }
        }
    }

    public function test_modality_is_read_under_the_lock_from_the_committed_championship(): void
    {
        $category = $this->categoryOfType('singles');
        $this->assertSame(ChampionshipType::SINGLES, $this->inLock($category, fn ($lock) => $this->service()->modality($lock)));

        $category->championship()->update(['type' => 'doubles']);

        $this->assertSame(ChampionshipType::DOUBLES, $this->inLock($category, fn ($lock) => $this->service()->modality($lock)));
    }

    public function test_ensure_for_player_creates_an_approved_entry_when_none_exists(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);

        $entry = $this->inLock($category, fn ($lock) => $this->service()->ensureForPlayer($lock, $player->id));

        $this->assertSame(['player', $player->id, null, 'approved'], [
            $entry->entry_type, $entry->player_id, $entry->team_id, $entry->status,
        ]);
        $this->assertSame(1, CategoryEntry::query()->count());
    }

    public function test_ensure_for_player_is_idempotent_only_for_an_exactly_coherent_approved_entry(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);

        $first = $this->inLock($category, fn ($lock) => $this->service()->ensureForPlayer($lock, $player->id));
        $before = $first->fresh()->getAttributes();
        $second = $this->inLock($category, fn ($lock) => $this->service()->ensureForPlayer($lock, $player->id));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertSame($before, $second->fresh()->getAttributes());
    }

    #[DataProvider('incompatibleStatuses')]
    public function test_ensure_for_player_fails_closed_on_an_existing_entry_that_is_not_approved(string $status): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        $legacy = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => $status,
        ]);
        $before = $legacy->fresh()->getAttributes();

        try {
            $this->inLock($category, fn ($lock) => $this->service()->ensureForPlayer($lock, $player->id));
            $this->fail('Debía fallar de forma cerrada.');
        } catch (CategoryEntryIntegrityException $exception) {
            $this->assertSame(CategoryEntryIntegrityException::existingEntryIncompatible()->getMessage(), $exception->getMessage());
            $this->assertSame('player_id', $exception->field);
        }

        // Neither promoted, replaced nor duplicated.
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertSame($before, $legacy->fresh()->getAttributes());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function incompatibleStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'another status' => ['archived'],
            'differently cased approved' => ['Approved'],
        ];
    }

    /**
     * The DB CHECK makes malformed rows unpersistable, so the compatibility rule is
     * exercised on unsaved entries, the shape a legacy or direct-SQL row would have.
     */
    #[DataProvider('malformedExistingEntries')]
    public function test_a_malformed_existing_identity_is_never_reusable(array $attributes): void
    {
        $entry = new CategoryEntry(array_merge([
            'category_id' => 1,
            'entry_type' => 'player',
            'player_id' => 7,
            'team_id' => null,
            'status' => 'approved',
        ], $attributes));

        $this->expectException(CategoryEntryIntegrityException::class);

        $this->service()->assertReusablePlayerEntry($entry, 7);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function malformedExistingEntries(): array
    {
        return [
            'team type with the player id' => [['entry_type' => 'team']],
            'both identities set' => [['team_id' => 3]],
            'another player' => [['player_id' => 8]],
            'no player' => [['player_id' => null]],
            'not approved' => [['status' => 'pending']],
        ];
    }

    public function test_a_coherent_approved_player_entry_is_reusable(): void
    {
        $entry = new CategoryEntry([
            'category_id' => 1,
            'entry_type' => 'player',
            'player_id' => 7,
            'team_id' => null,
            'status' => 'approved',
        ]);

        $this->service()->assertReusablePlayerEntry($entry, 7);

        $this->addToAssertionCount(1);
    }

    public function test_delete_helpers_only_remove_the_identity_in_that_category(): void
    {
        $category = $this->categoryOfType('singles');
        $other = $this->categoryOfType('singles');
        $player = Player::factory()->create();
        foreach ([$category, $other] as $each) {
            CategoryEntry::factory()->playerEntry()->create(['category_id' => $each->id, 'player_id' => $player->id]);
        }

        $deleted = $this->inLock($category, fn ($lock) => $this->service()->deleteForPlayer($lock, $player->id));

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('category_entries', ['category_id' => $other->id, 'player_id' => $player->id]);

        $doubles = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($doubles)['team'];
        CategoryEntry::factory()->teamEntry()->create(['category_id' => $doubles->id, 'team_id' => $team->id]);

        $this->assertSame(1, $this->inLock($doubles, fn ($lock) => $this->service()->deleteForTeam($lock, $team->id)));
        $this->assertSame(0, $this->inLock($doubles, fn ($lock) => $this->service()->deleteForTeam($lock, $team->id)));
    }

    public function test_team_player_helpers_reflect_registrations_and_assignments(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category);
        $loose = $this->registeredPlayer($category);
        $pending = $this->registeredPlayer($category, 'pending');

        $assigned = $this->inLock($category, fn ($lock) => $this->service()->assignedTeamPlayerIds($lock))->all();
        $this->assertEqualsCanonicalizing([$team['front']->id, $team['back']->id], $assigned);

        $this->inLock($category, fn ($lock) => $this->service()->assertPlayersRegistered($lock, [$team['front']->id, $loose->id]));

        $this->expectException(CategoryEntryIntegrityException::class);
        $this->inLock($category, fn ($lock) => $this->service()->assertPlayersRegistered($lock, [$loose->id, $pending->id]));
    }

    public function test_the_lock_helper_applies_the_official_result_guard(): void
    {
        $category = $this->categoryOfType('singles');
        $this->officialResult($category, OfficialResultCompetitionPart::LEAGUE);

        $this->expectException(OfficialResultMutationBlockedException::class);
        $this->inLock($category, fn ($lock) => $lock);
    }

    public function test_real_database_errors_are_translated_and_unrelated_ones_are_not(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        CategoryEntry::factory()->playerEntry()->create(['category_id' => $category->id, 'player_id' => $player->id]);
        $service = $this->service();

        $duplicate = $this->capture(fn () => CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]));
        $this->assertSame('Este jugador ya participa en esta categoría.', $service->integrityViolationFrom($duplicate)?->getMessage());
        $this->assertSame('player_id', $service->integrityViolationFrom($duplicate)?->field);

        $malformed = $this->capture(fn () => DB::table('category_entries')->insert([
            'category_id' => $category->id,
            'entry_type' => 'player',
            'player_id' => null,
            'team_id' => null,
            'status' => 'approved',
        ]));
        $this->assertSame(
            'Un participante debe referenciar exactamente un jugador o un equipo.',
            $service->integrityViolationFrom($malformed)?->getMessage()
        );

        $foreignKey = $this->capture(fn () => DB::table('category_entries')->insert([
            'category_id' => 999999,
            'entry_type' => 'player',
            'player_id' => $player->id,
            'team_id' => null,
            'status' => 'approved',
        ]));
        $this->assertNull($service->integrityViolationFrom($foreignKey));
        $this->assertFalse($service->isDuplicateRegistration($foreignKey));
    }

    public function test_a_duplicate_registration_is_recognised_from_the_real_database_error(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        $service = $this->service();

        $duplicate = $this->capture(fn () => CategoryRegistration::factory()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]));

        $this->assertTrue($service->isDuplicateRegistration($duplicate));
        $this->assertNull($service->integrityViolationFrom($duplicate));
    }

    private function service(): CategoryEntryService
    {
        return app(CategoryEntryService::class);
    }

    /**
     * @template T
     *
     * @param  Closure(OfficialResultLock): T  $action
     * @return T
     */
    private function inLock(Category $category, Closure $action): mixed
    {
        return DB::transaction(fn () => $action($this->service()->lockForParticipantMutation($category)));
    }

    private function capture(Closure $write): QueryException
    {
        try {
            $write();
        } catch (QueryException $exception) {
            return $exception;
        }

        $this->fail('La escritura debía fallar en la base de datos.');
    }
}
