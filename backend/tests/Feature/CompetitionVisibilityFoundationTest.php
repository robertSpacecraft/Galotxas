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

    public function test_legacy_admin_api_cannot_read_or_write_visibility_flags(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $seasonResponse = $this->postJson('/api/v1/admin/seasons', [
            'name' => 'Temporada desde API heredada',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => true,
        ])->assertCreated()->assertJsonMissingPath('is_public');
        $season = Season::query()->findOrFail($seasonResponse->json('id'));

        $championshipResponse = $this->postJson('/api/v1/admin/championships', [
            'season_id' => $season->id,
            'name' => 'Campeonato desde API heredada',
            'slug' => 'campeonato-desde-api-heredada',
            'type' => ChampionshipType::SINGLES->value,
            'status' => 'pending',
            'is_public' => true,
        ])->assertCreated()->assertJsonMissingPath('is_public');
        $championship = Championship::query()->findOrFail($championshipResponse->json('id'));

        $categoryResponse = $this->postJson('/api/v1/admin/categories', [
            'championship_id' => $championship->id,
            'name' => 'Categoría desde API heredada',
            'slug' => 'categoria-desde-api-heredada',
            'gender' => CategoryGender::MIXED->value,
            'status' => 'pending',
            'is_public' => true,
        ])->assertCreated()->assertJsonMissingPath('is_public');
        $category = Category::query()->findOrFail($categoryResponse->json('id'));

        $this->assertFalse($season->is_public);
        $this->assertFalse($championship->is_public);
        $this->assertFalse($category->is_public);

        $season->is_public = true;
        $season->save();
        $championship->is_public = true;
        $championship->save();
        $category->is_public = true;
        $category->save();

        $this->putJson('/api/v1/admin/seasons/'.$season->id, ['is_public' => false])
            ->assertOk()
            ->assertJsonMissingPath('is_public');
        $this->putJson('/api/v1/admin/championships/'.$championship->id, ['is_public' => false])
            ->assertOk()
            ->assertJsonMissingPath('is_public');
        $this->putJson('/api/v1/admin/categories/'.$category->id, ['is_public' => false])
            ->assertOk()
            ->assertJsonMissingPath('is_public');

        $this->assertTrue($season->fresh()->is_public);
        $this->assertTrue($championship->fresh()->is_public);
        $this->assertTrue($category->fresh()->is_public);
    }
}
