<?php

namespace Tests\Feature;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipStatus;
use App\Enums\ChampionshipType;
use App\Models\Category;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminChampionshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_open_create_and_edit_forms_with_real_options(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create(['name' => 'Temporada principal']);
        $otherSeason = Season::factory()->create(['name' => 'Temporada alternativa']);
        $championship = Championship::factory()->create([
            'season_id' => $otherSeason->id,
            'type' => ChampionshipType::DOUBLES->value,
            'status' => ChampionshipStatus::ACTIVE->value,
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'is_public' => false,
        ]);

        $createResponse = $this->actingAs($admin)->get(route('admin.championships.create', $season));

        $createResponse
            ->assertOk()
            ->assertSee('Crear campeonato')
            ->assertSee('Temporada principal')
            ->assertSee('Temporada alternativa')
            ->assertSee('id="season_id"', false)
            ->assertSee('id="description"', false)
            ->assertSee('id="start_date"', false)
            ->assertSee('id="registration_ends_at"', false)
            ->assertSee('id="is_public"', false)
            ->assertSee('Temporada principal — Pública')
            ->assertSee('Temporada alternativa — Privada')
            ->assertDontSee('name="image_path"', false);

        foreach (ChampionshipType::cases() as $type) {
            $createResponse->assertSee('value="'.$type->value.'"', false);
        }

        foreach (ChampionshipStatus::cases() as $status) {
            $createResponse->assertSee('value="'.$status->value.'"', false);
        }

        foreach (ChampionshipRegistrationStatus::cases() as $status) {
            $createResponse->assertSee('value="'.$status->value.'"', false);
        }

        $this->assertOptionSelected($createResponse, (string) $season->id);
        $this->assertOptionSelected($createResponse, ChampionshipStatus::PENDING->value);
        $this->assertOptionSelected($createResponse, ChampionshipRegistrationStatus::CLOSED->value);

        $editResponse = $this->actingAs($admin)->get(route('admin.championships.edit', $championship));

        $editResponse->assertOk()->assertSee('Editar campeonato');
        $this->assertOptionSelected($editResponse, (string) $otherSeason->id);
        $this->assertOptionSelected($editResponse, ChampionshipType::DOUBLES->value);
        $this->assertOptionSelected($editResponse, ChampionshipStatus::ACTIVE->value);
        $this->assertOptionSelected($editResponse, ChampionshipRegistrationStatus::OPEN->value);
        $this->assertVisibilityNotChecked($editResponse);
    }

    public function test_non_admin_cannot_create_or_edit_championships(): void
    {
        $user = User::factory()->create();
        $season = Season::factory()->create();
        $championship = Championship::factory()->create(['season_id' => $season->id]);

        $this->actingAs($user)
            ->get(route('admin.championships.create', $season))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.championships.store', $season), $this->validPayload($season, [
                'name' => 'Alta no autorizada',
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.championships.edit', $championship))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.championships.update', $championship), $this->validPayload($season, [
                'name' => 'Edición no autorizada',
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('championships', ['name' => 'Alta no autorizada']);
        $this->assertDatabaseMissing('championships', ['name' => 'Edición no autorizada']);
    }

    public function test_inactive_admin_loses_access_to_championship_management(): void
    {
        $admin = User::factory()->admin()->create(['active' => false]);
        $season = Season::factory()->create();

        $response = $this->actingAs($admin)
            ->withSession(['session_sentinel' => 'must-be-removed'])
            ->get(route('admin.championships.create', $season));

        $response
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Tu usuario está inactivo.'])
            ->assertSessionMissing('session_sentinel');

        $this->assertGuest('web');
    }

    public function test_anonymous_user_is_redirected_from_create_and_edit_forms(): void
    {
        $championship = Championship::factory()->create();

        $this->get(route('admin.championships.create', $championship->season))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.championships.edit', $championship))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_a_championship_with_every_non_multimedia_field(): void
    {
        $admin = User::factory()->admin()->create();
        $routeSeason = Season::factory()->create();
        $selectedSeason = Season::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.championships.store', $routeSeason),
            $this->validPayload($selectedSeason, [
                'name' => 'Campionat complet 2029',
                'description' => 'Descripció administrativa completa.',
                'type' => ChampionshipType::DOUBLES->value,
                'status' => ChampionshipStatus::ACTIVE->value,
                'start_date' => '2029-02-01',
                'end_date' => '2029-10-31',
                'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
                'registration_starts_at' => '2029-01-02T09:30',
                'registration_ends_at' => '2029-01-25T20:00',
            ])
        );

        $response
            ->assertRedirect(route('admin.seasons.championships', $selectedSeason))
            ->assertSessionHas('success', 'Campeonato creado correctamente.');

        $this->assertDatabaseHas('championships', [
            'season_id' => $selectedSeason->id,
            'name' => 'Campionat complet 2029',
            'slug' => 'campionat-complet-2029',
            'description' => 'Descripció administrativa completa.',
            'type' => ChampionshipType::DOUBLES->value,
            'status' => ChampionshipStatus::ACTIVE->value,
            'start_date' => '2029-02-01',
            'end_date' => '2029-10-31',
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => '2029-01-02 09:30:00',
            'registration_ends_at' => '2029-01-25 20:00:00',
            'image_path' => null,
            'is_public' => 0,
        ]);
    }

    public function test_admin_can_create_a_championship_with_nullable_fields_empty(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();
        $payload = $this->validPayload($season, [
            'name' => 'Campionat sense opcionals',
            'description' => '',
            'start_date' => '',
            'end_date' => '',
            'registration_starts_at' => '',
            'registration_ends_at' => '',
            'image_path' => 'uploads/no-permitido.jpg',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.championships.store', $season), $payload)
            ->assertRedirect(route('admin.seasons.championships', $season));

        $this->assertDatabaseHas('championships', [
            'season_id' => $season->id,
            'name' => 'Campionat sense opcionals',
            'description' => null,
            'start_date' => null,
            'end_date' => null,
            'registration_starts_at' => null,
            'registration_ends_at' => null,
            'image_path' => null,
        ]);
    }

    public function test_creation_validates_name_season_enums_and_description_without_persisting(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.championships.store', $season), $this->validPayload($season, [
                'season_id' => 999999,
                'name' => '',
                'description' => str_repeat('a', 5001),
                'type' => 'teams',
                'status' => 'published',
                'registration_status' => 'scheduled',
            ]))
            ->assertSessionHasErrors([
                'season_id',
                'name',
                'description',
                'type',
                'status',
                'registration_status',
            ]);

        $this->assertDatabaseCount('championships', 0);
    }

    public function test_creation_validates_all_date_formats_without_persisting(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.championships.store', $season), $this->validPayload($season, [
                'name' => 'Campionat amb dates invàlides',
                'start_date' => 'inici-invàlid',
                'end_date' => 'final-invàlid',
                'registration_starts_at' => 'obertura-invàlida',
                'registration_ends_at' => 'tancament-invàlid',
            ]))
            ->assertSessionHasErrors([
                'start_date',
                'end_date',
                'registration_starts_at',
                'registration_ends_at',
            ]);

        $this->assertDatabaseCount('championships', 0);
    }

    public function test_creation_validates_championship_and_registration_chronologies(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.championships.store', $season), $this->validPayload($season, [
                'name' => 'Campionat amb cronologia invàlida',
                'start_date' => '2029-08-10',
                'end_date' => '2029-08-01',
                'registration_starts_at' => '2029-07-20T12:00',
                'registration_ends_at' => '2029-07-20T11:59',
            ]))
            ->assertSessionHasErrors(['end_date', 'registration_ends_at']);

        $this->assertDatabaseCount('championships', 0);
    }

    public function test_edit_form_recovers_every_managed_value_and_omits_image_path(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Campionat existent',
            'description' => 'Descripció existent',
            'type' => ChampionshipType::DOUBLES->value,
            'status' => ChampionshipStatus::FINISHED->value,
            'start_date' => '2029-03-04',
            'end_date' => '2029-09-10',
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => '2029-02-01 08:15:00',
            'registration_ends_at' => '2029-02-28 21:45:00',
            'image_path' => 'championships/existent.jpg',
            'is_public' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.championships.edit', $championship));

        $response
            ->assertOk()
            ->assertSee('value="Campionat existent"', false)
            ->assertSee('Descripció existent')
            ->assertSee('value="2029-03-04"', false)
            ->assertSee('value="2029-09-10"', false)
            ->assertSee('value="2029-02-01T08:15"', false)
            ->assertSee('value="2029-02-28T21:45"', false)
            ->assertDontSee('name="image_path"', false);

        $this->assertOptionSelected($response, (string) $season->id);
        $this->assertOptionSelected($response, ChampionshipType::DOUBLES->value);
        $this->assertOptionSelected($response, ChampionshipStatus::FINISHED->value);
        $this->assertOptionSelected($response, ChampionshipRegistrationStatus::OPEN->value);
        $this->assertVisibilityChecked($response);
    }

    public function test_old_input_precedes_stored_values_after_update_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();
        $oldSeason = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->create([
            'season_id' => $season->id,
            'type' => ChampionshipType::SINGLES->value,
            'status' => ChampionshipStatus::ACTIVE->value,
            'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
        ]);
        $payload = $this->validPayload($oldSeason, [
            'name' => 'Valor recuperat',
            'description' => 'Descripció recuperada',
            'type' => ChampionshipType::DOUBLES->value,
            'status' => ChampionshipStatus::CANCELLED->value,
            'start_date' => '2030-10-10',
            'end_date' => '2030-10-01',
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => '2030-09-20T09:00',
            'registration_ends_at' => '2030-09-20T08:00',
            'is_public' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.championships.edit', $championship))
            ->put(route('admin.championships.update', $championship), $payload)
            ->assertRedirect(route('admin.championships.edit', $championship))
            ->assertSessionHasErrors(['end_date', 'registration_ends_at'])
            ->assertSessionHasInput($payload);

        $response = $this->actingAs($admin)->get(route('admin.championships.edit', $championship));

        $response
            ->assertOk()
            ->assertSee('value="Valor recuperat"', false)
            ->assertSee('Descripció recuperada')
            ->assertSee('value="2030-10-10"', false)
            ->assertSee('value="2030-10-01"', false)
            ->assertSee('value="2030-09-20T09:00"', false)
            ->assertSee('value="2030-09-20T08:00"', false);

        $this->assertOptionSelected($response, (string) $oldSeason->id);
        $this->assertOptionSelected($response, ChampionshipType::DOUBLES->value);
        $this->assertOptionSelected($response, ChampionshipStatus::CANCELLED->value);
        $this->assertOptionSelected($response, ChampionshipRegistrationStatus::OPEN->value);
        $this->assertVisibilityChecked($response);
    }

    public function test_valid_update_persists_every_field_and_preserves_image_and_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();
        $newSeason = Season::factory()->create();
        $championship = Championship::factory()->create([
            'season_id' => $season->id,
            'name' => 'Nom anterior',
            'image_path' => 'championships/conservar.jpg',
            'is_public' => false,
        ]);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $player = Player::factory()->create();
        $registrationRequest = ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        $response = $this->actingAs($admin)->put(
            route('admin.championships.update', $championship),
            $this->validPayload($newSeason, [
                'name' => 'Nom actualitzat',
                'description' => 'Descripció actualitzada',
                'type' => ChampionshipType::DOUBLES->value,
                'status' => ChampionshipStatus::FINISHED->value,
                'start_date' => '2030-03-01',
                'end_date' => '2030-11-30',
                'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
                'registration_starts_at' => '2030-01-15T10:00',
                'registration_ends_at' => '2030-02-15T20:30',
                'image_path' => 'championships/no-sustituir.jpg',
                'is_public' => 0,
            ])
        );

        $response
            ->assertRedirect(route('admin.seasons.championships', $newSeason))
            ->assertSessionHas('success', 'Campeonato actualizado correctamente.');

        $this->assertDatabaseHas('championships', [
            'id' => $championship->id,
            'season_id' => $newSeason->id,
            'name' => 'Nom actualitzat',
            'slug' => 'nom-actualitzat',
            'description' => 'Descripció actualitzada',
            'type' => ChampionshipType::DOUBLES->value,
            'status' => ChampionshipStatus::FINISHED->value,
            'start_date' => '2030-03-01',
            'end_date' => '2030-11-30',
            'registration_status' => ChampionshipRegistrationStatus::OPEN->value,
            'registration_starts_at' => '2030-01-15 10:00:00',
            'registration_ends_at' => '2030-02-15 20:30:00',
            'image_path' => 'championships/conservar.jpg',
            'is_public' => 0,
        ]);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'championship_id' => $championship->id,
        ]);
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'championship_id' => $championship->id,
        ]);
    }

    public function test_update_can_clear_all_nullable_managed_fields_without_clearing_image_path(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create([
            'description' => 'Descripció anterior',
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
            'registration_starts_at' => '2029-12-01 10:00:00',
            'registration_ends_at' => '2029-12-20 20:00:00',
            'image_path' => 'championships/conservar-nullables.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.championships.update', $championship), $this->validPayload(
                $championship->season,
                [
                    'name' => $championship->name,
                    'description' => '',
                    'start_date' => '',
                    'end_date' => '',
                    'registration_starts_at' => '',
                    'registration_ends_at' => '',
                ]
            ))
            ->assertRedirect(route('admin.seasons.championships', $championship->season));

        $this->assertDatabaseHas('championships', [
            'id' => $championship->id,
            'description' => null,
            'start_date' => null,
            'end_date' => null,
            'registration_starts_at' => null,
            'registration_ends_at' => null,
            'image_path' => 'championships/conservar-nullables.jpg',
        ]);
    }

    public function test_invalid_update_does_not_change_existing_data(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create([
            'name' => 'Campionat intacte',
            'description' => 'No canviar',
            'type' => ChampionshipType::SINGLES->value,
            'status' => ChampionshipStatus::ACTIVE->value,
            'start_date' => '2030-01-01',
            'end_date' => '2030-12-31',
            'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
            'image_path' => 'championships/intacte.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.championships.update', $championship), $this->validPayload(
                $championship->season,
                [
                    'name' => 'No persistir',
                    'status' => 'invalid',
                    'description' => 'Tampoc persistir',
                    'image_path' => null,
                ]
            ))
            ->assertSessionHasErrors('status');

        $championship->refresh();

        $this->assertSame('Campionat intacte', $championship->name);
        $this->assertSame('No canviar', $championship->description);
        $this->assertSame(ChampionshipType::SINGLES, $championship->type);
        $this->assertSame(ChampionshipStatus::ACTIVE->value, $championship->status);
        $this->assertSame('2030-01-01', $championship->start_date?->format('Y-m-d'));
        $this->assertSame('2030-12-31', $championship->end_date?->format('Y-m-d'));
        $this->assertSame(ChampionshipRegistrationStatus::CLOSED, $championship->registration_status);
        $this->assertSame('championships/intacte.jpg', $championship->image_path);
    }

    public function test_list_detail_categories_and_registration_relations_still_work(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create([
            'name' => 'Campionat relacional',
            'type' => ChampionshipType::DOUBLES->value,
        ]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Categoria vinculada',
        ]);
        $player = Player::factory()->create(['nickname' => 'JugadorVinculat']);
        ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.championships.index'))
            ->assertOk()
            ->assertSee('Campionat relacional')
            ->assertSee(ChampionshipType::DOUBLES->value);

        $this->actingAs($admin)
            ->get(route('admin.championships.show', $championship))
            ->assertOk()
            ->assertSee('Categoria vinculada')
            ->assertSee('JugadorVinculat');

        $this->actingAs($admin)
            ->get(route('admin.championships.categories', $championship))
            ->assertOk()
            ->assertSee($category->name);
    }

    public function test_public_contract_and_current_visibility_by_status_remain_unchanged(): void
    {
        $active = Championship::factory()->create([
            'name' => 'Campionat públic actiu',
            'status' => ChampionshipStatus::ACTIVE->value,
            'is_public' => false,
        ]);
        $cancelled = Championship::factory()->create([
            'name' => 'Campionat públic cancel·lat',
            'status' => ChampionshipStatus::CANCELLED->value,
            'is_public' => false,
        ]);

        $this->getJson('/api/v1/championships')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $active->id,
                'status' => ChampionshipStatus::ACTIVE->value,
            ])
            ->assertJsonFragment([
                'id' => $cancelled->id,
                'status' => ChampionshipStatus::CANCELLED->value,
            ]);

        $response = $this->getJson('/api/v1/championships/'.$cancelled->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.id', $cancelled->id)
            ->assertJsonPath('data.status', ChampionshipStatus::CANCELLED->value);

        $this->assertSame([
            'id',
            'season_id',
            'name',
            'slug',
            'description',
            'type',
            'status',
            'start_date',
            'end_date',
            'registration_status',
            'registration_starts_at',
            'registration_ends_at',
            'registration_is_open',
            'season',
            'categories',
            'created_at',
            'updated_at',
        ], array_keys($response->json('data')));
        $response->assertJsonMissingPath('data.is_public');
    }

    public function test_championship_creation_enforces_public_parent_hierarchy(): void
    {
        $admin = User::factory()->admin()->create();
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $publicSeason = Season::factory()->publiclyVisible()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.championships.store', $privateSeason),
                $this->validPayload($privateSeason, [
                    'name' => 'Privado bajo temporada privada',
                    'is_public' => 0,
                ])
            )
            ->assertRedirect(route('admin.seasons.championships', $privateSeason));

        $this->actingAs($admin)
            ->post(
                route('admin.championships.store', $publicSeason),
                $this->validPayload($publicSeason, [
                    'name' => 'Privado bajo temporada pública',
                    'is_public' => false,
                ])
            )
            ->assertRedirect(route('admin.seasons.championships', $publicSeason));

        $this->actingAs($admin)
            ->post(
                route('admin.championships.store', $publicSeason),
                $this->validPayload($publicSeason, [
                    'name' => 'Público bajo temporada pública',
                    'is_public' => true,
                ])
            )
            ->assertRedirect(route('admin.seasons.championships', $publicSeason));

        $this->actingAs($admin)
            ->post(
                route('admin.championships.store', $privateSeason),
                $this->validPayload($privateSeason, [
                    'name' => 'Público imposible',
                    'is_public' => 1,
                ])
            )
            ->assertSessionHasErrors([
                'is_public' => 'No puedes hacer público el campeonato mientras su temporada sea privada.',
            ]);

        $this->assertDatabaseHas('championships', [
            'name' => 'Privado bajo temporada privada',
            'is_public' => 0,
        ]);
        $this->assertDatabaseHas('championships', [
            'name' => 'Privado bajo temporada pública',
            'is_public' => 0,
        ]);
        $this->assertDatabaseHas('championships', [
            'name' => 'Público bajo temporada pública',
            'is_public' => 1,
        ]);
        $this->assertDatabaseMissing('championships', ['name' => 'Público imposible']);
    }

    public function test_championship_visibility_updates_respect_hierarchy_and_do_not_cascade(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $season->id,
            'status' => ChampionshipStatus::ACTIVE->value,
            'image_path' => 'championships/preservar.jpg',
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);

        $this->actingAs($admin)
            ->put(
                route('admin.championships.update', $championship),
                $this->validPayload($season, [
                    'name' => $championship->name,
                    'status' => ChampionshipStatus::ACTIVE->value,
                    'is_public' => 1,
                ])
            )
            ->assertRedirect(route('admin.seasons.championships', $season));

        $this->assertTrue($championship->fresh()->is_public);
        $this->assertSame(ChampionshipStatus::ACTIVE->value, $championship->fresh()->status);
        $this->assertSame('championships/preservar.jpg', $championship->fresh()->image_path);

        $season->is_public = false;
        $season->save();

        $this->actingAs($admin)
            ->put(
                route('admin.championships.update', $championship),
                $this->validPayload($season, [
                    'name' => 'No debe hacerse público',
                    'is_public' => true,
                ])
            )
            ->assertSessionHasErrors('is_public');

        $this->actingAs($admin)
            ->put(
                route('admin.championships.update', $championship),
                $this->validPayload($season, [
                    'name' => $championship->name,
                    'status' => ChampionshipStatus::ACTIVE->value,
                    'is_public' => 0,
                ])
            )
            ->assertRedirect(route('admin.seasons.championships', $season));

        $this->assertFalse($championship->fresh()->is_public);
        $this->assertTrue($category->fresh()->is_public);
    }

    public function test_championship_visibility_must_be_a_boolean(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.championships.store', $season),
                $this->validPayload($season, [
                    'name' => 'Visibilidad manipulada',
                    'is_public' => 'publicada',
                ])
            )
            ->assertSessionHasErrors('is_public');

        $this->assertDatabaseMissing('championships', ['name' => 'Visibilidad manipulada']);
    }

    public function test_admin_can_delete_an_empty_championship(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.championships.destroy', $championship))
            ->assertRedirect(route('admin.seasons.championships', $championship->season))
            ->assertSessionHas('success', 'Campeonato eliminado correctamente.');

        $this->assertDatabaseMissing('championships', ['id' => $championship->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Season $season, array $overrides = []): array
    {
        return array_merge([
            'season_id' => $season->id,
            'name' => 'Campionat vàlid '.fake()->unique()->numerify('####'),
            'description' => 'Descripció vàlida.',
            'type' => ChampionshipType::SINGLES->value,
            'status' => ChampionshipStatus::PENDING->value,
            'is_public' => 0,
            'start_date' => '2029-03-01',
            'end_date' => '2029-11-30',
            'registration_status' => ChampionshipRegistrationStatus::CLOSED->value,
            'registration_starts_at' => '2029-01-10T09:00',
            'registration_ends_at' => '2029-02-20T20:00',
        ], $overrides);
    }

    private function assertOptionSelected(TestResponse $response, string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/<option\s+value="'.preg_quote($value, '/').'"[^>]*\bselected\b[^>]*>/',
            $response->getContent()
        );
    }

    private function assertVisibilityChecked(TestResponse $response): void
    {
        $this->assertMatchesRegularExpression(
            '/<input\s+[^>]*id="is_public"[^>]*\bchecked\b[^>]*>/',
            $response->getContent()
        );
    }

    private function assertVisibilityNotChecked(TestResponse $response): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<input\s+[^>]*id="is_public"[^>]*\bchecked\b[^>]*>/',
            $response->getContent()
        );
    }
}
