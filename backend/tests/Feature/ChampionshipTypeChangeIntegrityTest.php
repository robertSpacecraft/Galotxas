<?php

namespace Tests\Feature;

use App\Enums\OfficialResultCompetitionPart;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesCategoryParticipantFixtures;
use Tests\TestCase;

class ChampionshipTypeChangeIntegrityTest extends TestCase
{
    use CreatesCategoryParticipantFixtures;
    use RefreshDatabase;

    public function test_type_change_is_blocked_while_registrations_exist(): void
    {
        $category = $this->categoryOfType('singles');
        $this->registeredPlayer($category);

        $this->changeType($category->championship, 'doubles')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type')
            ->assertJsonPath('errors.type.0', fn (string $message): bool => str_contains($message, 'inscripciones (1)'));

        $this->assertSame('singles', $category->championship->fresh()->type->value);
        $this->assertSame(1, CategoryRegistration::query()->count());
    }

    public function test_type_change_is_blocked_while_entries_exist(): void
    {
        $category = $this->categoryOfType('singles');
        CategoryEntry::factory()->playerEntry()->create(['category_id' => $category->id, 'status' => 'approved']);

        $this->changeType($category->championship, 'doubles')
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', fn (string $message): bool => str_contains($message, 'participantes (1)'));

        $this->assertSame('singles', $category->championship->fresh()->type->value);
        $this->assertSame(1, CategoryEntry::query()->count());
    }

    public function test_type_change_is_blocked_while_teams_exist_in_either_direction(): void
    {
        $doubles = $this->categoryOfType('doubles');
        Team::factory()->create(['category_id' => $doubles->id]);

        $this->changeType($doubles->championship, 'singles')
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', fn (string $message): bool => str_contains($message, 'equipos (1)'));

        $this->assertSame('doubles', $doubles->championship->fresh()->type->value);
        $this->assertSame(1, Team::query()->count());
    }

    public function test_the_message_lists_every_kind_of_dependent_setup_without_touching_it(): void
    {
        $category = $this->categoryOfType('doubles');
        $team = $this->doublesTeam($category)['team'];
        CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $team->id,
            'status' => 'approved',
        ]);

        $this->changeType($category->championship, 'singles')
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', fn (string $message): bool => str_contains($message, 'inscripciones (2)')
                && str_contains($message, 'participantes (1)')
                && str_contains($message, 'equipos (1)'));

        $this->assertSame(2, CategoryRegistration::query()->count());
        $this->assertSame(1, CategoryEntry::query()->count());
        $this->assertSame(1, Team::query()->count());
        $this->assertDatabaseCount('team_members', 2);
    }

    public function test_type_change_is_allowed_when_no_dependent_setup_exists(): void
    {
        $category = $this->categoryOfType('singles');
        $sibling = Category::factory()->create(['championship_id' => $category->championship_id]);

        $this->changeType($category->championship, 'doubles')
            ->assertOk()
            ->assertJsonPath('data.type', 'doubles');

        $this->assertSame('doubles', $category->championship->fresh()->type->value);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('categories', ['id' => $sibling->id]);
    }

    public function test_type_change_is_allowed_for_a_championship_without_categories(): void
    {
        $championship = Championship::factory()->create(['type' => 'doubles']);

        $this->changeType($championship, 'singles')->assertOk();

        $this->assertSame('singles', $championship->fresh()->type->value);
    }

    public function test_dependent_setup_of_another_championship_does_not_block(): void
    {
        $target = $this->categoryOfType('singles');
        $other = $this->categoryOfType('singles');
        $this->registeredPlayer($other);
        Team::factory()->create(['category_id' => $other->id]);

        $this->changeType($target->championship, 'doubles')->assertOk();

        $this->assertSame('doubles', $target->championship->fresh()->type->value);
        $this->assertSame('singles', $other->championship->fresh()->type->value);
    }

    public function test_ordinary_updates_are_unaffected_by_dependent_setup(): void
    {
        $category = $this->categoryOfType('singles');
        $this->registeredPlayer($category);

        $this->update($category->championship, ['name' => 'Nombre nuevo', 'status' => 'finished'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre nuevo');

        $championship = $category->championship->fresh();
        $this->assertSame('Nombre nuevo', $championship->name);
        $this->assertSame('singles', $championship->type->value);
    }

    public function test_a_current_official_result_still_returns_409_with_or_without_dependent_setup(): void
    {
        $bare = $this->categoryOfType('singles');
        $this->officialResult($bare, OfficialResultCompetitionPart::CUP);

        $loaded = $this->categoryOfType('singles');
        $this->registeredPlayer($loaded);
        $this->officialResult($loaded, OfficialResultCompetitionPart::LEAGUE);

        foreach ([$bare, $loaded] as $category) {
            $this->changeType($category->championship, 'doubles')
                ->assertConflict()
                ->assertJsonPath('message', OfficialResultMutationBlockedException::MESSAGE);

            $this->assertSame('singles', $category->championship->fresh()->type->value);
        }
    }

    public function test_blade_update_reports_the_block_on_the_type_field_and_persists_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $category = $this->categoryOfType('singles');
        $this->registeredPlayer($category);
        $championship = $category->championship;
        $payload = $this->payload($championship, ['type' => 'doubles', 'name' => 'No debe persistir']);

        $this->actingAs($admin)
            ->from(route('admin.championships.edit', $championship))
            ->put(route('admin.championships.update', $championship), $payload)
            ->assertRedirect(route('admin.championships.edit', $championship))
            ->assertSessionHasErrors('type');

        $championship->refresh();
        $this->assertSame('singles', $championship->type->value);
        $this->assertNotSame('No debe persistir', $championship->name);
        $this->assertSame(1, CategoryRegistration::query()->count());
    }

    public function test_blade_update_changes_the_type_when_nothing_depends_on_it(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create(['type' => 'singles']);

        $this->actingAs($admin)
            ->put(route('admin.championships.update', $championship), $this->payload($championship, ['type' => 'doubles']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('doubles', $championship->fresh()->type->value);
    }

    private function changeType(Championship $championship, string $type): TestResponse
    {
        return $this->update($championship, ['type' => $type]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function update(Championship $championship, array $overrides): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/v1/admin/championships/{$championship->id}", $this->payload($championship, $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Championship $championship, array $overrides = []): array
    {
        return array_merge([
            'season_id' => $championship->season_id,
            'name' => $championship->name,
            'description' => $championship->description,
            'type' => $championship->type->value,
            'status' => $championship->status,
            'is_public' => false,
            'start_date' => $championship->start_date?->format('Y-m-d'),
            'end_date' => $championship->end_date?->format('Y-m-d'),
            'registration_status' => 'closed',
            'registration_starts_at' => null,
            'registration_ends_at' => null,
        ], $overrides);
    }
}
