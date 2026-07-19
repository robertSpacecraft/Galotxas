<?php

namespace Tests\Feature;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipStatus;
use App\Enums\ChampionshipType;
use App\Enums\SeasonStatus;
use App\Models\Category;
use App\Models\Championship;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCompetitionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_competition_crud_route_requires_an_active_administrator(): void
    {
        $season = Season::factory()->create();
        $championship = Championship::factory()->create(['season_id' => $season->id]);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $routes = $this->crudRoutes($season, $championship, $category);

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden();
        }

        Sanctum::actingAs(User::factory()->admin()->create(['active' => false]));

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri)->assertForbidden();
        }
    }

    public function test_active_admin_manages_seasons_with_validation_and_a_stable_contract(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/seasons', [
            'id' => 999999,
            'name' => 'Temporada API completa',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => true,
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
            'created_at' => '2000-01-01 00:00:00',
            'image_path' => 'manipulada.jpg',
            'unknown_field' => 'ignorado',
        ])->assertCreated()
            ->assertJsonPath('message', 'Temporada creada correctamente.')
            ->assertJsonPath('data.name', 'Temporada API completa')
            ->assertJsonPath('data.status', SeasonStatus::ACTIVE->value)
            ->assertJsonPath('data.is_public', true)
            ->assertJsonMissingPath('data.slug')
            ->assertJsonMissingPath('data.image_path')
            ->assertJsonMissingPath('data.unknown_field');

        $this->assertSame($this->seasonKeys(), array_keys($response->json('data')));
        $season = Season::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame(999999, $season->id);
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'name' => 'Temporada API completa',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => true,
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
        ]);

        $nullableResponse = $this->postJson('/api/v1/admin/seasons', [
            'name' => 'Temporada sin fechas',
            'status' => SeasonStatus::PLANNED->value,
            'is_public' => false,
            'start_date' => null,
            'end_date' => null,
        ])->assertCreated()
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.end_date', null);

        $nullableSeason = Season::query()->findOrFail($nullableResponse->json('data.id'));
        $child = Championship::factory()->publiclyVisible()->create(['season_id' => $season->id]);

        $this->putJson('/api/v1/admin/seasons/'.$season->id, [
            'id' => $nullableSeason->id,
            'name' => 'Temporada API actualizada',
            'status' => SeasonStatus::FINISHED->value,
            'is_public' => false,
            'start_date' => null,
            'end_date' => null,
            'updated_at' => '2000-01-01 00:00:00',
            'unknown_field' => 'ignorado',
        ])->assertOk()
            ->assertJsonPath('message', 'Temporada actualizada correctamente.')
            ->assertJsonPath('data.id', $season->id)
            ->assertJsonPath('data.name', 'Temporada API actualizada')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.end_date', null);

        $this->assertSame($season->id, $season->fresh()->id);
        $this->assertTrue($child->fresh()->is_public);

        $beforeInvalidUpdate = $season->fresh()->getAttributes();
        $this->putJson('/api/v1/admin/seasons/'.$season->id, [
            'name' => 'No debe persistir',
            'status' => 'invalid',
            'is_public' => true,
            'start_date' => '2031-12-31',
            'end_date' => '2031-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'end_date']);
        $this->assertSame($beforeInvalidUpdate, $season->fresh()->getAttributes());

        $index = $this->getJson('/api/v1/admin/seasons')->assertOk();
        $this->assertSame(['message', 'data'], array_keys($index->json()));
        $this->assertSame($this->seasonKeys(), array_keys($index->json('data.0')));
        $this->getJson('/api/v1/admin/seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('data.id', $season->id)
            ->assertJsonPath('data.is_public', false);

        $this->deleteJson('/api/v1/admin/seasons/'.$nullableSeason->id)->assertNoContent();
        $this->assertDatabaseMissing('seasons', ['id' => $nullableSeason->id]);
    }

    public function test_active_admin_manages_championships_without_mutating_protected_fields(): void
    {
        $this->actingAsAdmin();
        $publicSeason = Season::factory()->publiclyVisible()->create();
        $otherPublicSeason = Season::factory()->publiclyVisible()->create();
        $privateSeason = Season::factory()->privatelyVisible()->create();

        $response = $this->postJson('/api/v1/admin/championships', [
            ...$this->championshipPayload($publicSeason),
            'id' => 999999,
            'slug' => 'slug-manipulado',
            'image_path' => 'imagen-manipulada.jpg',
            'created_at' => '2000-01-01 00:00:00',
            'unknown_field' => 'ignorado',
        ])->assertCreated()
            ->assertJsonPath('message', 'Campeonato creado correctamente.')
            ->assertJsonPath('data.slug', 'campeonato-api-completo')
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.season.id', $publicSeason->id)
            ->assertJsonMissingPath('data.image_path')
            ->assertJsonMissingPath('data.unknown_field');

        $this->assertSame($this->championshipKeys(), array_keys($response->json('data')));
        $championship = Championship::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame(999999, $championship->id);
        $this->assertNull($championship->image_path);
        $this->assertDatabaseHas('championships', [
            'id' => $championship->id,
            'season_id' => $publicSeason->id,
            'slug' => 'campeonato-api-completo',
            'description' => 'Descripción administrativa completa.',
            'type' => ChampionshipType::SINGLES->value,
            'status' => ChampionshipStatus::PENDING->value,
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'is_public' => true,
        ]);

        $this->postJson('/api/v1/admin/championships', [
            ...$this->championshipPayload($privateSeason),
            'name' => 'Campeonato público inválido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_public');

        $this->postJson('/api/v1/admin/championships', [
            ...$this->championshipPayload($publicSeason),
            'season_id' => 999999,
            'type' => 'invalid',
            'status' => 'invalid',
            'registration_status' => 'invalid',
            'start_date' => '2031-12-31',
            'end_date' => '2031-01-01',
            'registration_starts_at' => '2031-12-31 10:00:00',
            'registration_ends_at' => '2031-01-01 10:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'season_id',
                'type',
                'status',
                'registration_status',
                'end_date',
                'registration_ends_at',
            ]);

        $championship->image_path = 'images/protegida.jpg';
        $championship->save();
        $child = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $updatedPayload = [
            ...$this->championshipPayload($otherPublicSeason),
            'name' => 'Campeonato API actualizado',
            'description' => null,
            'type' => ChampionshipType::DOUBLES->value,
            'status' => ChampionshipStatus::FINISHED->value,
            'is_public' => false,
            'start_date' => null,
            'end_date' => null,
            'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
            'registration_starts_at' => null,
            'registration_ends_at' => null,
            'id' => 999999,
            'slug' => 'slug-manipulado-en-update',
            'image_path' => 'images/manipulada.jpg',
            'championship_entries' => ['manipulado'],
        ];

        $this->putJson('/api/v1/admin/championships/'.$championship->id, $updatedPayload)
            ->assertOk()
            ->assertJsonPath('message', 'Campeonato actualizado correctamente.')
            ->assertJsonPath('data.id', $championship->id)
            ->assertJsonPath('data.season_id', $otherPublicSeason->id)
            ->assertJsonPath('data.slug', 'campeonato-api-actualizado')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.is_public', false)
            ->assertJsonMissingPath('data.image_path');

        $championship->refresh();
        $this->assertSame('images/protegida.jpg', $championship->image_path);
        $this->assertSame($otherPublicSeason->id, $championship->season_id);
        $this->assertTrue($child->fresh()->is_public);

        $beforeInvalidUpdate = $championship->getAttributes();
        $this->putJson('/api/v1/admin/championships/'.$championship->id, [
            ...$updatedPayload,
            'season_id' => $privateSeason->id,
            'name' => 'No debe persistir',
            'is_public' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_public');
        $this->assertSame($beforeInvalidUpdate, $championship->fresh()->getAttributes());

        $index = $this->getJson('/api/v1/admin/championships')->assertOk();
        $this->assertSame($this->championshipKeys(), array_keys($index->json('data.0')));
        $this->getJson('/api/v1/admin/championships/'.$championship->id)
            ->assertOk()
            ->assertJsonPath('data.id', $championship->id)
            ->assertJsonPath('data.is_public', false);

        $deletable = Championship::factory()->create(['season_id' => $publicSeason->id]);
        $this->deleteJson('/api/v1/admin/championships/'.$deletable->id)->assertNoContent();
        $this->assertDatabaseMissing('championships', ['id' => $deletable->id]);
    }

    public function test_active_admin_manages_categories_without_moving_them_by_payload(): void
    {
        $this->actingAsAdmin();
        $publicSeason = Season::factory()->publiclyVisible()->create();
        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $otherPublicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $championshipUnderPrivateSeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);

        $response = $this->postJson('/api/v1/admin/categories', [
            ...$this->categoryPayload($publicChampionship),
            'id' => 999999,
            'slug' => 'slug-manipulado',
            'image_path' => 'imagen-manipulada.jpg',
            'created_at' => '2000-01-01 00:00:00',
            'unknown_field' => 'ignorado',
        ])->assertCreated()
            ->assertJsonPath('message', 'Categoría creada correctamente.')
            ->assertJsonPath('data.slug', 'categoria-api-completa')
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.championship.id', $publicChampionship->id)
            ->assertJsonPath('data.championship.season.id', $publicSeason->id)
            ->assertJsonMissingPath('data.image_path')
            ->assertJsonMissingPath('data.unknown_field');

        $this->assertSame($this->categoryKeys(), array_keys($response->json('data')));
        $category = Category::query()->findOrFail($response->json('data.id'));
        $this->assertNotSame(999999, $category->id);
        $this->assertNull($category->image_path);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'championship_id' => $publicChampionship->id,
            'slug' => 'categoria-api-completa',
            'description' => 'Descripción administrativa completa.',
            'level' => 4,
            'gender' => CategoryGender::MIXED->value,
            'status' => CategoryStatus::PENDING->value,
            'is_public' => true,
        ]);

        foreach ([$privateChampionship, $championshipUnderPrivateSeason] as $privateParent) {
            $this->postJson('/api/v1/admin/categories', [
                ...$this->categoryPayload($privateParent),
                'name' => 'Categoría pública inválida '.$privateParent->id,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('is_public');
        }

        $this->postJson('/api/v1/admin/categories', [
            ...$this->categoryPayload($publicChampionship),
            'championship_id' => 999999,
            'name' => '',
            'level' => 11,
            'gender' => 'invalid',
            'status' => 'invalid',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['championship_id', 'name', 'level', 'gender', 'status']);

        $category->image_path = 'images/categoria-protegida.jpg';
        $category->save();
        $updatePayload = [
            ...$this->categoryPayload($publicChampionship),
            'name' => 'Categoría API actualizada',
            'description' => null,
            'level' => null,
            'gender' => CategoryGender::FEMALE->value,
            'status' => CategoryStatus::ACTIVE->value,
            'is_public' => false,
            'championship_id' => $otherPublicChampionship->id,
            'id' => 999999,
            'slug' => 'slug-manipulado-en-update',
            'image_path' => 'images/categoria-manipulada.jpg',
            'entries' => [['id' => 123]],
        ];

        $this->putJson('/api/v1/admin/categories/'.$category->id, $updatePayload)
            ->assertOk()
            ->assertJsonPath('message', 'Categoría actualizada correctamente.')
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.championship_id', $publicChampionship->id)
            ->assertJsonPath('data.slug', 'categoria-api-actualizada')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.level', null)
            ->assertJsonPath('data.is_public', false)
            ->assertJsonMissingPath('data.image_path');

        $category->refresh();
        $this->assertSame($publicChampionship->id, $category->championship_id);
        $this->assertSame('images/categoria-protegida.jpg', $category->image_path);

        $publicChampionship->is_public = false;
        $publicChampionship->save();
        $beforeInvalidUpdate = $category->getAttributes();
        $this->putJson('/api/v1/admin/categories/'.$category->id, [
            ...$updatePayload,
            'name' => 'No debe persistir',
            'is_public' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('is_public');
        $this->assertSame($beforeInvalidUpdate, $category->fresh()->getAttributes());

        $index = $this->getJson('/api/v1/admin/categories')->assertOk();
        $this->assertSame($this->categoryKeys(), array_keys($index->json('data.0')));
        $this->getJson('/api/v1/admin/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.is_public', false);

        $deletable = Category::factory()->create([
            'championship_id' => $otherPublicChampionship->id,
        ]);
        $this->deleteJson('/api/v1/admin/categories/'.$deletable->id)->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $deletable->id]);
    }

    public function test_private_entities_remain_administrable_while_public_contracts_stay_hidden(): void
    {
        $season = Season::factory()->privatelyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'status' => ChampionshipStatus::CANCELLED->value,
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/seasons/'.$season->id)
            ->assertOk()
            ->assertJsonPath('data.id', $season->id)
            ->assertJsonPath('data.is_public', false);
        $this->getJson('/api/v1/admin/championships/'.$championship->id)
            ->assertOk()
            ->assertJsonPath('data.id', $championship->id)
            ->assertJsonPath('data.is_public', true)
            ->assertJsonPath('data.status', ChampionshipStatus::CANCELLED->value);
        $this->getJson('/api/v1/admin/categories/'.$category->id)
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.is_public', true);

        $publicSeasons = $this->getJson('/api/v1/seasons')->assertOk();
        $this->assertFalse(collect($publicSeasons->json('data'))->contains('id', $season->id));
        $this->getJson('/api/v1/championships/'.$championship->id)->assertNotFound();
        $this->getJson('/api/v1/categories/'.$category->id)->assertNotFound();
        $this->assertStringNotContainsString('is_public', $publicSeasons->getContent());
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /**
     * @return array<int, array{string, string}>
     */
    private function crudRoutes(
        Season $season,
        Championship $championship,
        Category $category
    ): array {
        return [
            ['GET', '/api/v1/admin/seasons'],
            ['POST', '/api/v1/admin/seasons'],
            ['GET', '/api/v1/admin/seasons/'.$season->id],
            ['PUT', '/api/v1/admin/seasons/'.$season->id],
            ['DELETE', '/api/v1/admin/seasons/'.$season->id],
            ['GET', '/api/v1/admin/championships'],
            ['POST', '/api/v1/admin/championships'],
            ['GET', '/api/v1/admin/championships/'.$championship->id],
            ['PUT', '/api/v1/admin/championships/'.$championship->id],
            ['DELETE', '/api/v1/admin/championships/'.$championship->id],
            ['GET', '/api/v1/admin/categories'],
            ['POST', '/api/v1/admin/categories'],
            ['GET', '/api/v1/admin/categories/'.$category->id],
            ['PUT', '/api/v1/admin/categories/'.$category->id],
            ['DELETE', '/api/v1/admin/categories/'.$category->id],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function championshipPayload(Season $season): array
    {
        return [
            'season_id' => $season->id,
            'name' => 'Campeonato API completo',
            'description' => 'Descripción administrativa completa.',
            'type' => ChampionshipType::SINGLES->value,
            'status' => ChampionshipStatus::PENDING->value,
            'is_public' => true,
            'start_date' => '2030-02-01',
            'end_date' => '2030-11-30',
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => '2030-01-01 09:00:00',
            'registration_ends_at' => '2030-01-31 23:59:59',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(Championship $championship): array
    {
        return [
            'championship_id' => $championship->id,
            'name' => 'Categoría API completa',
            'description' => 'Descripción administrativa completa.',
            'level' => 4,
            'gender' => CategoryGender::MIXED->value,
            'status' => CategoryStatus::PENDING->value,
            'is_public' => true,
        ];
    }

    /**
     * @return list<string>
     */
    private function seasonKeys(): array
    {
        return [
            'id',
            'name',
            'status',
            'is_public',
            'start_date',
            'end_date',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @return list<string>
     */
    private function championshipKeys(): array
    {
        return [
            'id',
            'season_id',
            'name',
            'slug',
            'description',
            'type',
            'status',
            'is_public',
            'start_date',
            'end_date',
            'registration_status',
            'registration_starts_at',
            'registration_ends_at',
            'season',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @return list<string>
     */
    private function categoryKeys(): array
    {
        return [
            'id',
            'championship_id',
            'name',
            'slug',
            'description',
            'level',
            'gender',
            'status',
            'is_public',
            'championship',
            'created_at',
            'updated_at',
        ];
    }
}
