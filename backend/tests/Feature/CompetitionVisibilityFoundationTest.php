<?php

namespace Tests\Feature;

use App\Enums\CategoryGender;
use App\Enums\ChampionshipType;
use App\Enums\SeasonStatus;
use App\Models\Category;
use App\Models\Championship;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionVisibilityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_defaults_and_model_casts_make_new_competition_records_private(): void
    {
        $season = Season::query()->create([
            'name' => 'Temporada por defecto',
            'status' => SeasonStatus::PLANNED->value,
        ]);
        $championship = Championship::query()->create([
            'season_id' => $season->id,
            'name' => 'Campeonato por defecto',
            'slug' => 'campeonato-por-defecto',
            'type' => ChampionshipType::SINGLES->value,
            'status' => 'pending',
        ]);
        $category = Category::query()->create([
            'championship_id' => $championship->id,
            'name' => 'Categoría por defecto',
            'slug' => 'categoria-por-defecto',
            'gender' => CategoryGender::MIXED->value,
            'status' => 'pending',
        ]);

        $this->assertFalse($season->fresh()->is_public);
        $this->assertFalse($championship->fresh()->is_public);
        $this->assertFalse($category->fresh()->is_public);
        $this->assertIsBool($season->fresh()->is_public);
        $this->assertIsBool($championship->fresh()->is_public);
        $this->assertIsBool($category->fresh()->is_public);
    }

    public function test_factories_offer_explicit_public_and_private_visibility_states(): void
    {
        $this->assertFalse(Season::factory()->create()->is_public);
        $this->assertFalse(Championship::factory()->create()->is_public);
        $this->assertFalse(Category::factory()->create()->is_public);

        $this->assertTrue(Season::factory()->publiclyVisible()->create()->is_public);
        $this->assertTrue(Championship::factory()->publiclyVisible()->create()->is_public);
        $this->assertTrue(Category::factory()->publiclyVisible()->create()->is_public);

        $this->assertFalse(Season::factory()->publiclyVisible()->privatelyVisible()->create()->is_public);
        $this->assertFalse(Championship::factory()->publiclyVisible()->privatelyVisible()->create()->is_public);
        $this->assertFalse(Category::factory()->publiclyVisible()->privatelyVisible()->create()->is_public);
    }

    public function test_admin_api_manages_visibility_flags_through_its_controlled_contract(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $seasonResponse = $this->postJson('/api/v1/admin/seasons', [
            'name' => 'Temporada desde API administrativa',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => true,
            'start_date' => null,
            'end_date' => null,
        ])->assertCreated()->assertJsonPath('data.is_public', true);
        $season = Season::query()->findOrFail($seasonResponse->json('data.id'));

        $championshipResponse = $this->postJson('/api/v1/admin/championships', [
            'season_id' => $season->id,
            'name' => 'Campeonato desde API administrativa',
            'description' => null,
            'type' => ChampionshipType::SINGLES->value,
            'status' => 'pending',
            'is_public' => true,
            'start_date' => null,
            'end_date' => null,
            'registration_status' => 'closed',
            'registration_starts_at' => null,
            'registration_ends_at' => null,
        ])->assertCreated()->assertJsonPath('data.is_public', true);
        $championship = Championship::query()->findOrFail($championshipResponse->json('data.id'));

        $categoryResponse = $this->postJson('/api/v1/admin/categories', [
            'championship_id' => $championship->id,
            'name' => 'Categoría desde API administrativa',
            'description' => null,
            'level' => null,
            'gender' => CategoryGender::MIXED->value,
            'status' => 'pending',
            'is_public' => true,
        ])->assertCreated()->assertJsonPath('data.is_public', true);
        $category = Category::query()->findOrFail($categoryResponse->json('data.id'));

        $this->putJson('/api/v1/admin/seasons/'.$season->id, [
            'name' => $season->name,
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => false,
            'start_date' => null,
            'end_date' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_public', false);
        $this->putJson('/api/v1/admin/championships/'.$championship->id, [
            'season_id' => $season->id,
            'name' => $championship->name,
            'description' => null,
            'type' => ChampionshipType::SINGLES->value,
            'status' => 'pending',
            'is_public' => false,
            'start_date' => null,
            'end_date' => null,
            'registration_status' => 'closed',
            'registration_starts_at' => null,
            'registration_ends_at' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_public', false);
        $this->putJson('/api/v1/admin/categories/'.$category->id, [
            'name' => $category->name,
            'description' => null,
            'level' => null,
            'gender' => CategoryGender::MIXED->value,
            'status' => 'pending',
            'is_public' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_public', false);

        $this->assertFalse($season->fresh()->is_public);
        $this->assertFalse($championship->fresh()->is_public);
        $this->assertFalse($category->fresh()->is_public);
    }
}
