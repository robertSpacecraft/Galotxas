<?php

namespace Tests\Feature\Admin;

use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\CategoryEntryIntegrityException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesCategoryParticipantFixtures;
use Tests\TestCase;

class CategoryParticipantFlowsTest extends TestCase
{
    use CreatesCategoryParticipantFixtures;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_singles_registration_creates_exactly_one_approved_player_entry(): void
    {
        $category = $this->categoryOfType('singles');
        $player = Player::factory()->create();
        $this->withApprovedChampionshipRequest($category, $player);

        $this->register($category, $player)
            ->assertSessionHas('success', 'Jugador inscrito correctamente en la categoría.');

        $this->assertSame(1, CategoryRegistration::query()->count());
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertDatabaseHas('category_entries', [
            'category_id' => $category->id,
            'entry_type' => 'player',
            'player_id' => $player->id,
            'team_id' => null,
            'status' => 'approved',
        ]);

        $this->register($category, $player)
            ->assertSessionHas('error', 'El jugador ya está inscrito en esta categoría.');

        $this->assertSame(1, CategoryRegistration::query()->count());
        $this->assertSame(1, CategoryEntry::query()->count());
    }

    public function test_singles_registration_reuses_an_existing_approved_player_entry(): void
    {
        $category = $this->categoryOfType('singles');
        $player = Player::factory()->create();
        $this->withApprovedChampionshipRequest($category, $player);
        $existing = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
        $before = $existing->fresh()->getAttributes();

        $this->register($category, $player)->assertSessionHas('success');

        $this->assertSame(1, CategoryRegistration::query()->count());
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertSame($before, $existing->fresh()->getAttributes());
    }

    #[DataProvider('incompatibleExistingStatuses')]
    public function test_singles_registration_fails_closed_on_an_existing_non_approved_entry(string $status): void
    {
        $category = $this->categoryOfType('singles');
        $player = Player::factory()->create();
        $this->withApprovedChampionshipRequest($category, $player);
        $existing = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => $status,
        ]);
        $before = $existing->fresh()->getAttributes();

        $this->register($category, $player)
            ->assertSessionHas('error', CategoryEntryIntegrityException::existingEntryIncompatible()->getMessage())
            ->assertSessionMissing('success');

        // The registration created earlier in the same transaction rolled back.
        $this->assertSame(0, CategoryRegistration::query()->count());
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertSame($before, $existing->fresh()->getAttributes());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function incompatibleExistingStatuses(): array
    {
        return [
            'pending' => ['pending'],
            'rejected' => ['rejected'],
            'another status' => ['archived'],
            'differently cased approved' => ['Approved'],
        ];
    }

    public function test_doubles_registration_creates_only_the_registration(): void
    {
        $category = $this->categoryOfType('doubles');
        $player = Player::factory()->create();
        $this->withApprovedChampionshipRequest($category, $player);

        $this->register($category, $player)->assertSessionHas('success');

        $this->assertSame(1, CategoryRegistration::query()->count());
        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_a_player_still_cannot_be_registered_in_two_categories_of_one_championship(): void
    {
        $first = $this->categoryOfType('singles');
        $second = Category::factory()->create(['championship_id' => $first->championship_id]);
        $player = Player::factory()->create();
        $this->withApprovedChampionshipRequest($first, $player);

        $this->register($first, $player)->assertSessionHas('success');
        $this->register($second, $player)
            ->assertSessionHas('error', 'El jugador ya está asignado a otra categoría de este campeonato.');

        $this->assertSame(1, CategoryRegistration::query()->count());
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertDatabaseMissing('category_entries', ['category_id' => $second->id]);
    }

    public function test_singles_registration_deletion_removes_the_entry_and_the_registration(): void
    {
        $category = $this->categoryOfType('singles');
        $player = Player::factory()->create();
        $this->withApprovedChampionshipRequest($category, $player);
        $this->register($category, $player);
        $registration = CategoryRegistration::query()->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.registrations.destroy', [$category, $registration]))
            ->assertSessionHas('success', 'Inscripción eliminada');

        $this->assertSame(0, CategoryRegistration::query()->count());
        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_team_creation_creates_the_team_its_members_and_one_approved_team_entry(): void
    {
        $category = $this->categoryOfType('doubles');
        $front = $this->registeredPlayer($category);
        $back = $this->registeredPlayer($category);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.teams.store', $category), [
                'name' => 'Equipo Norte',
                'front_player_id' => $front->id,
                'back_player_id' => $back->id,
            ])
            ->assertSessionHas('success', 'Equipo creado correctamente');

        $team = Team::query()->sole();
        $this->assertSame($category->id, $team->category_id);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'player_id' => $front->id, 'role_in_team' => 'front']);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'player_id' => $back->id, 'role_in_team' => 'back']);
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertDatabaseHas('category_entries', [
            'category_id' => $category->id,
            'entry_type' => 'team',
            'team_id' => $team->id,
            'player_id' => null,
            'status' => 'approved',
        ]);
    }

    public function test_team_creation_requires_both_players_to_be_registered(): void
    {
        $category = $this->categoryOfType('doubles');
        $front = $this->registeredPlayer($category);
        $unregistered = Player::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.categories.teams.store', $category), [
                'front_player_id' => $front->id,
                'back_player_id' => $unregistered->id,
            ])
            ->assertSessionHas('error', 'Los jugadores del equipo deben estar inscritos en la categoría');

        $this->assertSame(0, Team::query()->count());
        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_team_creation_rejects_a_player_already_in_another_team_of_the_category(): void
    {
        $category = $this->categoryOfType('doubles');
        $existing = $this->doublesTeam($category);
        $other = $this->registeredPlayer($category);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.teams.store', $category), [
                'front_player_id' => $existing['front']->id,
                'back_player_id' => $other->id,
            ])
            ->assertSessionHas('error', 'Uno o ambos jugadores ya pertenecen a otro equipo de esta categoría');

        $this->assertSame(1, Team::query()->count());
        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_team_creation_is_rejected_in_a_singles_category(): void
    {
        $category = $this->categoryOfType('singles');
        $front = $this->registeredPlayer($category);
        $back = $this->registeredPlayer($category);

        $this->actingAs($this->admin)
            ->post(route('admin.categories.teams.store', $category), [
                'front_player_id' => $front->id,
                'back_player_id' => $back->id,
            ])
            ->assertSessionHas('error', 'Solo se pueden formar equipos en categorías de dobles');

        $this->assertSame(0, Team::query()->count());
        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_doubles_registration_can_be_removed_when_the_player_has_no_team(): void
    {
        $category = $this->categoryOfType('doubles');
        $player = $this->registeredPlayer($category);

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.registrations.destroy', [
                $category,
                CategoryRegistration::query()->where('player_id', $player->id)->sole(),
            ]))
            ->assertSessionHas('success', 'Inscripción eliminada');

        $this->assertSame(0, CategoryRegistration::query()->count());
    }

    public function test_doubles_registration_cannot_be_removed_while_the_player_belongs_to_a_team(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category);
        $entry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $team['team']->id,
            'status' => 'approved',
        ]);
        $registration = CategoryRegistration::query()->where('player_id', $team['front']->id)->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.registrations.destroy', [$category, $registration]))
            ->assertSessionHas(
                'error',
                'No se puede eliminar la inscripción porque el jugador pertenece a un equipo de esta categoría'
            );

        $this->assertDatabaseHas('category_registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('category_entries', ['id' => $entry->id]);
        $this->assertSame(2, $team['team']->players()->count());
    }

    public function test_team_deletion_removes_the_entry_the_team_and_frees_the_registration(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category);
        CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $team['team']->id,
            'status' => 'approved',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.teams.destroy', [$category, $team['team']]))
            ->assertSessionHas('success', 'Equipo eliminado correctamente');

        $this->assertSame(0, Team::query()->count());
        $this->assertSame(0, CategoryEntry::query()->count());
        $this->assertDatabaseCount('team_members', 0);

        $this->actingAs($this->admin)
            ->delete(route('admin.categories.registrations.destroy', [
                $category,
                CategoryRegistration::query()->where('player_id', $team['front']->id)->sole(),
            ]))
            ->assertSessionHas('success', 'Inscripción eliminada');
    }

    #[DataProvider('officialParts')]
    public function test_doubles_participant_mutations_are_blocked_by_a_current_official_result(
        OfficialResultCompetitionPart $part,
    ): void {
        $category = $this->categoryOfType('doubles');
        $registered = $this->registeredPlayer($category);
        $candidate = Player::factory()->create();
        $this->withApprovedChampionshipRequest($category, $candidate);
        $this->officialResult($category, $part);
        $registration = CategoryRegistration::query()->where('player_id', $registered->id)->sole();

        $this->register($category, $candidate)
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);
        $this->actingAs($this->admin)
            ->delete(route('admin.categories.registrations.destroy', [$category, $registration]))
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);

        $this->assertSame(1, CategoryRegistration::query()->count());
        $this->assertDatabaseHas('category_registrations', ['id' => $registration->id]);
        $this->assertDatabaseMissing('category_registrations', ['player_id' => $candidate->id]);
    }

    /**
     * @return array<string, array{OfficialResultCompetitionPart}>
     */
    public static function officialParts(): array
    {
        return [
            'league' => [OfficialResultCompetitionPart::LEAGUE],
            'cup' => [OfficialResultCompetitionPart::CUP],
        ];
    }

    public function test_deleting_a_player_who_is_only_registered_in_a_doubles_category_is_guarded(): void
    {
        $category = $this->categoryOfType('doubles');
        $player = $this->registeredPlayer($category);
        $this->officialResult($category, OfficialResultCompetitionPart::LEAGUE);

        $this->actingAs($this->admin)
            ->delete(route('admin.players.destroy', $player))
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);

        $this->assertDatabaseHas('players', ['id' => $player->id]);
        $this->assertSame(1, CategoryRegistration::query()->count());
    }

    private function register(Category $category, Player $player): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.categories.registrations.store', $category), ['player_id' => $player->id]);
    }
}
