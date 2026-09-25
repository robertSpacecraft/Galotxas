<?php

namespace Tests\Feature;

use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCategoryParticipantFixtures;
use Tests\TestCase;

class AdminCategoryEntryApiTest extends TestCase
{
    use CreatesCategoryParticipantFixtures;
    use RefreshDatabase;

    public function test_valid_player_entry_in_singles_is_created_approved_with_the_inherited_response(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);

        $this->postEntry($category, ['entry_type' => 'player', 'player_id' => $player->id, 'team_id' => null])
            ->assertCreated()
            ->assertJsonPath('category_id', $category->id)
            ->assertJsonPath('entry_type', 'player')
            ->assertJsonPath('player_id', $player->id)
            ->assertJsonPath('team_id', null)
            ->assertJsonPath('status', 'approved');

        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertDatabaseHas('category_entries', [
            'category_id' => $category->id,
            'entry_type' => 'player',
            'player_id' => $player->id,
            'team_id' => null,
            'status' => 'approved',
        ]);
    }

    public function test_valid_team_entry_in_doubles_is_created_approved(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category)['team'];

        $this->postEntry($category, ['entry_type' => 'team', 'team_id' => $team->id])
            ->assertCreated()
            ->assertJsonPath('entry_type', 'team')
            ->assertJsonPath('team_id', $team->id)
            ->assertJsonPath('player_id', null)
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('category_entries', [
            'category_id' => $category->id,
            'entry_type' => 'team',
            'team_id' => $team->id,
            'player_id' => null,
            'status' => 'approved',
        ]);
    }

    public function test_a_client_cannot_choose_the_status_of_a_new_entry(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);

        $this->postEntry($category, ['entry_type' => 'player', 'player_id' => $player->id, 'status' => 'rejected'])
            ->assertCreated()
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('category_entries', ['player_id' => $player->id, 'status' => 'approved']);
    }

    public function test_both_identities_set_is_rejected_without_a_row(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        $team = Team::factory()->create(['category_id' => $category->id]);

        $this->postEntry($category, ['entry_type' => 'player', 'player_id' => $player->id, 'team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');
        $this->postEntry($category, ['entry_type' => 'team', 'player_id' => $player->id, 'team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('player_id');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_no_identity_at_all_is_rejected_without_a_row(): void
    {
        $category = $this->categoryOfType('singles');

        foreach (['player' => 'player_id', 'team' => 'team_id'] as $type => $field) {
            $this->postEntry($category, ['entry_type' => $type, 'player_id' => null, 'team_id' => null])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->postEntry($category, ['entry_type' => 'player'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('player_id');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_player_type_with_a_wrong_or_missing_source_is_rejected(): void
    {
        $category = $this->categoryOfType('singles');
        $team = Team::factory()->create(['category_id' => $category->id]);

        $this->postEntry($category, ['entry_type' => 'player', 'team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['player_id', 'team_id']);
        $this->postEntry($category, ['entry_type' => 'player', 'player_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('player_id');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_team_type_with_a_wrong_or_missing_source_is_rejected(): void
    {
        $category = $this->categoryOfType('doubles');
        $player = $this->registeredPlayer($category);

        $this->postEntry($category, ['entry_type' => 'team', 'player_id' => $player->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['player_id', 'team_id']);
        $this->postEntry($category, ['entry_type' => 'team', 'team_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_unknown_entry_types_are_rejected(): void
    {
        $category = $this->categoryOfType('singles');

        $this->postEntry($category, ['entry_type' => 'duo', 'player_id' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entry_type');
        $this->postEntry($category, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('entry_type');
    }

    public function test_player_entry_in_a_doubles_category_is_rejected_by_the_domain(): void
    {
        $category = $this->categoryOfType('doubles');
        $player = $this->registeredPlayer($category);

        $this->postEntry($category, ['entry_type' => 'player', 'player_id' => $player->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.entry_type.0', 'Las categorías de dobles solo admiten participantes de tipo equipo.')
            ->assertJsonMissingPath('data');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_team_entry_in_a_singles_category_is_rejected_by_the_domain(): void
    {
        $category = $this->categoryOfType('singles');
        $team = Team::factory()->create(['category_id' => $category->id]);

        $this->postEntry($category, ['entry_type' => 'team', 'team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.entry_type.0', 'Las categorías de individuales solo admiten participantes de tipo jugador.');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_team_from_another_category_is_rejected(): void
    {
        $category = $this->categoryOfType('doubles');
        $foreign = $this->doublesTeam($this->categoryOfType('doubles'))['team'];
        $orphan = Team::factory()->create(['category_id' => null]);

        foreach ([$foreign, $orphan] as $team) {
            $this->postEntry($category, ['entry_type' => 'team', 'team_id' => $team->id])
                ->assertUnprocessable()
                ->assertJsonPath('errors.team_id.0', 'El equipo no pertenece a esta categoría.');
        }

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_invalid_doubles_team_composition_is_rejected(): void
    {
        $category = $this->categoryOfType('doubles');

        $oneMember = Team::factory()->create(['category_id' => $category->id]);
        $oneMember->players()->attach($this->registeredPlayer($category)->id, ['role_in_team' => 'front']);

        $sameRoles = Team::factory()->create(['category_id' => $category->id]);
        $sameRoles->players()->attach($this->registeredPlayer($category)->id, ['role_in_team' => 'front']);
        $sameRoles->players()->attach($this->registeredPlayer($category)->id, ['role_in_team' => 'front']);

        $noMembers = Team::factory()->create(['category_id' => $category->id]);

        foreach ([$oneMember, $sameRoles, $noMembers] as $team) {
            $this->postEntry($category, ['entry_type' => 'team', 'team_id' => $team->id])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('team_id');
        }

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_players_of_the_team_must_be_registered_and_approved_in_the_category(): void
    {
        $category = $this->categoryOfType('doubles');
        $pendingFront = $this->registeredPlayer($category, 'pending');
        $back = $this->registeredPlayer($category);
        $team = Team::factory()->create(['category_id' => $category->id]);
        $team->players()->attach($pendingFront->id, ['role_in_team' => 'front']);
        $team->players()->attach($back->id, ['role_in_team' => 'back']);

        $this->postEntry($category, ['entry_type' => 'team', 'team_id' => $team->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'Los jugadores del equipo deben estar inscritos en la categoría');

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_a_player_needs_an_approved_registration_in_the_category(): void
    {
        $category = $this->categoryOfType('singles');
        $unregistered = Player::factory()->create();
        $pending = $this->registeredPlayer($category, 'pending');

        foreach ([$unregistered, $pending] as $player) {
            $this->postEntry($category, ['entry_type' => 'player', 'player_id' => $player->id])
                ->assertUnprocessable()
                ->assertJsonPath('errors.player_id.0', 'El jugador debe estar inscrito y aprobado en la categoría para participar.');
        }

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_duplicate_player_identity_in_the_same_category_is_a_controlled_422(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        $payload = ['entry_type' => 'player', 'player_id' => $player->id];

        $this->postEntry($category, $payload)->assertCreated();
        $response = $this->postEntry($category, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.player_id.0', 'Este jugador ya participa en esta categoría.');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertSame(1, CategoryEntry::query()->count());
    }

    public function test_a_legacy_non_approved_entry_still_blocks_a_new_one_for_the_same_identity(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'rejected',
        ]);

        $this->postEntry($category, ['entry_type' => 'player', 'player_id' => $player->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.player_id.0', 'Este jugador ya participa en esta categoría.');

        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertDatabaseHas('category_entries', ['player_id' => $player->id, 'status' => 'rejected']);
    }

    public function test_duplicate_team_identity_in_the_same_category_is_a_controlled_422(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category)['team'];
        $payload = ['entry_type' => 'team', 'team_id' => $team->id];

        $this->postEntry($category, $payload)->assertCreated();
        $response = $this->postEntry($category, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.team_id.0', 'Este equipo ya participa en esta categoría.');

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertSame(1, CategoryEntry::query()->count());
    }

    public function test_the_same_player_can_enter_different_categories(): void
    {
        $first = $this->categoryOfType('singles');
        $second = $this->categoryOfType('singles');
        $player = Player::factory()->create();

        foreach ([$first, $second] as $category) {
            CategoryRegistration::factory()->create([
                'category_id' => $category->id,
                'player_id' => $player->id,
                'status' => 'approved',
            ]);
        }

        $this->postEntry($first, ['entry_type' => 'player', 'player_id' => $player->id])->assertCreated();
        $this->postEntry($second, ['entry_type' => 'player', 'player_id' => $player->id])->assertCreated();

        $this->assertSame(2, CategoryEntry::query()->where('player_id', $player->id)->count());
    }

    public function test_official_result_mutation_protection_remains_409_for_valid_and_invalid_input(): void
    {
        $singles = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($singles);
        $this->officialResult($singles, OfficialResultCompetitionPart::LEAGUE);

        $doubles = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($doubles)['team'];
        $this->officialResult($doubles, OfficialResultCompetitionPart::CUP);

        $this->postEntry($singles, ['entry_type' => 'player', 'player_id' => $player->id])
            ->assertConflict()
            ->assertJsonPath('message', OfficialResultMutationBlockedException::MESSAGE);
        $this->postEntry($doubles, ['entry_type' => 'team', 'team_id' => $team->id])
            ->assertConflict();
        // Domain-invalid input under an official result is still the guard's 409, never a 422.
        $this->postEntry($singles, ['entry_type' => 'team', 'team_id' => Team::factory()->create()->id])
            ->assertConflict();

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_the_endpoint_still_requires_an_active_administrator(): void
    {
        $category = $this->categoryOfType('singles');
        $player = $this->registeredPlayer($category);
        $payload = ['entry_type' => 'player', 'player_id' => $player->id];

        $this->postJson("/api/v1/admin/categories/{$category->id}/entries", $payload)->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->postJson("/api/v1/admin/categories/{$category->id}/entries", $payload)
            ->assertForbidden();

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postEntry(Category $category, array $payload): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/v1/admin/categories/{$category->id}/entries", $payload);
    }
}
