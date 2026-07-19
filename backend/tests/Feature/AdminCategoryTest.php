<?php

namespace Tests\Feature;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_open_create_and_edit_forms_with_real_options(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'name' => 'Campionat principal',
        ]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Categoria existent',
            'description' => 'Descripció existent',
            'level' => 7,
            'gender' => CategoryGender::MIXED->value,
            'status' => CategoryStatus::ACTIVE->value,
            'is_public' => true,
        ]);

        $createResponse = $this->actingAs($admin)->get(route('admin.categories.create', $championship));

        $createResponse
            ->assertOk()
            ->assertSee('Nueva categoría')
            ->assertSee('Campionat principal')
            ->assertSee('id="description"', false)
            ->assertSee('id="status"', false)
            ->assertSee('id="is_public"', false)
            ->assertSee('Campeonato: Público')
            ->assertSee('Temporada: Pública')
            ->assertDontSee('name="championship_id"', false)
            ->assertDontSee('name="image_path"', false);

        foreach (CategoryGender::cases() as $gender) {
            $createResponse->assertSee('value="'.$gender->value.'"', false);
        }

        foreach (CategoryStatus::cases() as $status) {
            $createResponse->assertSee('value="'.$status->value.'"', false);
        }

        $this->assertOptionSelected($createResponse, CategoryStatus::PENDING->value);

        $editResponse = $this->actingAs($admin)->get(route('admin.categories.edit', $category));

        $editResponse
            ->assertOk()
            ->assertSee('value="Categoria existent"', false)
            ->assertSee('Descripció existent')
            ->assertDontSee('name="championship_id"', false)
            ->assertDontSee('name="image_path"', false);

        $this->assertOptionSelected($editResponse, '7');
        $this->assertOptionSelected($editResponse, CategoryGender::MIXED->value);
        $this->assertOptionSelected($editResponse, CategoryStatus::ACTIVE->value);
        $this->assertVisibilityChecked($editResponse);
    }

    public function test_non_admin_cannot_create_or_edit_categories(): void
    {
        $user = User::factory()->create();
        $championship = Championship::factory()->create();
        $category = Category::factory()->create(['championship_id' => $championship->id]);

        $this->actingAs($user)
            ->get(route('admin.categories.create', $championship))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.categories.store', $championship), $this->validPayload([
                'name' => 'Alta no autorizada',
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.categories.edit', $category))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.categories.update', $category), $this->validPayload([
                'name' => 'Edición no autorizada',
            ]))
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Alta no autorizada']);
        $this->assertDatabaseMissing('categories', ['name' => 'Edición no autorizada']);
    }

    public function test_inactive_admin_loses_access_to_category_management(): void
    {
        $admin = User::factory()->admin()->create(['active' => false]);
        $championship = Championship::factory()->create();

        $response = $this->actingAs($admin)
            ->withSession(['session_sentinel' => 'must-be-removed'])
            ->get(route('admin.categories.create', $championship));

        $response
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Tu usuario está inactivo.'])
            ->assertSessionMissing('session_sentinel');

        $this->assertGuest('web');
    }

    public function test_anonymous_user_is_redirected_from_create_and_edit_forms(): void
    {
        $category = Category::factory()->create();

        $this->get(route('admin.categories.create', $category->championship))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.categories.edit', $category))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_a_category_with_every_non_multimedia_field(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $otherChampionship = Championship::factory()->create();

        $response = $this->actingAs($admin)->post(
            route('admin.categories.store', $championship),
            $this->validPayload([
                'championship_id' => $otherChampionship->id,
                'name' => 'Categoria completa 2029',
                'description' => 'Descripció administrativa completa.',
                'level' => 8,
                'gender' => CategoryGender::FEMALE->value,
                'status' => CategoryStatus::ACTIVE->value,
                'image_path' => 'categories/no-permitido.jpg',
            ])
        );

        $response
            ->assertRedirect(route('admin.championships.categories', $championship))
            ->assertSessionHas('success', 'Categoría creada');

        $this->assertDatabaseHas('categories', [
            'championship_id' => $championship->id,
            'name' => 'Categoria completa 2029',
            'slug' => 'categoria-completa-2029',
            'description' => 'Descripció administrativa completa.',
            'level' => 8,
            'gender' => CategoryGender::FEMALE->value,
            'status' => CategoryStatus::ACTIVE->value,
            'image_path' => null,
            'is_public' => 0,
        ]);
    }

    public function test_admin_can_create_a_category_with_nullable_fields_empty(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store', $championship), $this->validPayload([
                'name' => 'Categoria sense opcionals',
                'description' => '',
                'level' => '',
            ]))
            ->assertRedirect(route('admin.championships.categories', $championship));

        $this->assertDatabaseHas('categories', [
            'championship_id' => $championship->id,
            'name' => 'Categoria sense opcionals',
            'description' => null,
            'level' => null,
            'image_path' => null,
        ]);
    }

    public function test_creation_validates_every_managed_field_without_persisting(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store', $championship), $this->validPayload([
                'name' => '',
                'description' => str_repeat('a', 5001),
                'level' => 11,
                'gender' => 'open',
                'status' => 'finished',
            ]))
            ->assertSessionHasErrors(['name', 'description', 'level', 'gender', 'status']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store', $championship), $this->validPayload([
                'name' => str_repeat('n', 256),
            ]))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_creation_requires_an_existing_championship_route_binding(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store', 999999), $this->validPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_edit_form_recovers_every_managed_value_and_omits_image_path(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create(['season_id' => $season->id]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Categoria editable',
            'description' => 'Text editable',
            'level' => 4,
            'gender' => CategoryGender::MALE->value,
            'status' => CategoryStatus::ACTIVE->value,
            'image_path' => 'categories/existent.jpg',
            'is_public' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.categories.edit', $category));

        $response
            ->assertOk()
            ->assertSee('value="Categoria editable"', false)
            ->assertSee('Text editable')
            ->assertDontSee('name="image_path"', false)
            ->assertDontSee('name="championship_id"', false);

        $this->assertOptionSelected($response, '4');
        $this->assertOptionSelected($response, CategoryGender::MALE->value);
        $this->assertOptionSelected($response, CategoryStatus::ACTIVE->value);
        $this->assertVisibilityChecked($response);
    }

    public function test_old_input_precedes_stored_values_after_update_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create(['season_id' => $season->id]);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'name' => 'Valor persistit',
            'description' => 'Descripció persistida',
            'level' => 2,
            'gender' => CategoryGender::MALE->value,
            'status' => CategoryStatus::PENDING->value,
            'is_public' => true,
        ]);
        $payload = $this->validPayload([
            'name' => 'Valor recuperat',
            'description' => str_repeat('d', 5001),
            'level' => 9,
            'gender' => CategoryGender::FEMALE->value,
            'status' => CategoryStatus::ACTIVE->value,
            'is_public' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.categories.edit', $category))
            ->put(route('admin.categories.update', $category), $payload)
            ->assertRedirect(route('admin.categories.edit', $category))
            ->assertSessionHasErrors('description')
            ->assertSessionHasInput($payload);

        $response = $this->actingAs($admin)->get(route('admin.categories.edit', $category));

        $response
            ->assertOk()
            ->assertSee('value="Valor recuperat"', false)
            ->assertSee(str_repeat('d', 5001));

        $this->assertOptionSelected($response, '9');
        $this->assertOptionSelected($response, CategoryGender::FEMALE->value);
        $this->assertOptionSelected($response, CategoryStatus::ACTIVE->value);
        $this->assertVisibilityNotChecked($response);
    }

    public function test_valid_update_persists_every_field_and_preserves_image_championship_and_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();
        $otherChampionship = Championship::factory()->create();
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'image_path' => 'categories/conservar.jpg',
            'is_public' => false,
        ]);
        $player = Player::factory()->create();
        $registration = CategoryRegistration::factory()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]);
        $entry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]);
        $round = Round::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->put(
            route('admin.categories.update', $category),
            $this->validPayload([
                'championship_id' => $otherChampionship->id,
                'name' => 'Categoria actualitzada',
                'description' => 'Descripció actualitzada',
                'level' => 10,
                'gender' => CategoryGender::MIXED->value,
                'status' => CategoryStatus::ACTIVE->value,
                'image_path' => 'categories/no-sustituir.jpg',
                'is_public' => 0,
            ])
        );

        $response
            ->assertRedirect(route('admin.championships.categories', $championship))
            ->assertSessionHas('success', 'Categoría actualizada');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'championship_id' => $championship->id,
            'name' => 'Categoria actualitzada',
            'slug' => 'categoria-actualitzada',
            'description' => 'Descripció actualitzada',
            'level' => 10,
            'gender' => CategoryGender::MIXED->value,
            'status' => CategoryStatus::ACTIVE->value,
            'image_path' => 'categories/conservar.jpg',
            'is_public' => 0,
        ]);
        $this->assertDatabaseHas('category_registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('category_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('rounds', ['id' => $round->id]);
    }

    public function test_update_can_clear_nullable_fields_without_clearing_image_path(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create([
            'description' => 'Descripció anterior',
            'level' => 6,
            'image_path' => 'categories/conservar-nullables.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), $this->validPayload([
                'name' => $category->name,
                'description' => '',
                'level' => '',
            ]))
            ->assertRedirect(route('admin.championships.categories', $category->championship));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'description' => null,
            'level' => null,
            'image_path' => 'categories/conservar-nullables.jpg',
        ]);
    }

    public function test_invalid_update_does_not_change_existing_data(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create([
            'name' => 'Categoria intacta',
            'description' => 'No canviar',
            'level' => 3,
            'gender' => CategoryGender::MALE->value,
            'status' => CategoryStatus::ACTIVE->value,
            'image_path' => 'categories/intacta.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), $this->validPayload([
                'name' => 'No persistir',
                'description' => 'Tampoc persistir',
                'status' => 'invalid',
                'image_path' => null,
            ]))
            ->assertSessionHasErrors('status');

        $category->refresh();

        $this->assertSame('Categoria intacta', $category->name);
        $this->assertSame('No canviar', $category->description);
        $this->assertSame(3, $category->level);
        $this->assertSame(CategoryGender::MALE, $category->gender);
        $this->assertSame(CategoryStatus::ACTIVE->value, $category->status);
        $this->assertSame('categories/intacta.jpg', $category->image_path);
    }

    public function test_list_and_detail_keep_showing_category_data_and_registrations(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create([
            'name' => 'Categoria relacional',
            'description' => 'Descripció visible al detall',
            'status' => CategoryStatus::ACTIVE->value,
        ]);
        $player = Player::factory()->create(['nickname' => 'JugadorVinculat']);
        CategoryRegistration::factory()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.championships.categories', $category->championship))
            ->assertOk()
            ->assertSee('Categoria relacional')
            ->assertSee(CategoryStatus::ACTIVE->value);

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee('Descripció visible al detall')
            ->assertSee('JugadorVinculat');
    }

    public function test_public_category_contract_remains_unchanged(): void
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
            'description' => 'No forma parte del contrato público',
            'image_path' => 'categories/no-publica.jpg',
            'status' => CategoryStatus::PENDING->value,
        ]);

        $response = $this->getJson('/api/v1/categories/'.$category->id);

        $response
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.status', CategoryStatus::PENDING->value)
            ->assertJsonMissingPath('data.description')
            ->assertJsonMissingPath('data.image_path');

        $this->assertSame([
            'id',
            'championship_id',
            'name',
            'slug',
            'level',
            'gender',
            'status',
            'championship',
            'created_at',
            'updated_at',
        ], array_keys($response->json('data')));
        $response->assertJsonMissingPath('data.is_public');

        $this->getJson('/api/v1/championships/'.$category->championship_id)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $category->id,
                'status' => CategoryStatus::PENDING->value,
            ]);
    }

    public function test_standings_and_schedule_keep_responding_with_related_matches(): void
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $homeEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'status' => 'approved',
        ]);
        $round = Round::factory()->create([
            'category_id' => $category->id,
            'name' => 'Jornada compatible',
            'type' => 'league',
        ]);
        $venue = Venue::factory()->create(['name' => 'Pista compatible']);
        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'venue_id' => $venue->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'status' => 'scheduled',
        ]);

        $this->getJson('/api/v1/categories/'.$category->id.'/standings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['entry_id' => $homeEntry->id])
            ->assertJsonFragment(['entry_id' => $awayEntry->id]);

        $this->getJson('/api/v1/categories/'.$category->id.'/schedule')
            ->assertOk()
            ->assertJsonPath('data.0.id', $round->id)
            ->assertJsonPath('data.0.matches.0.id', $match->id)
            ->assertJsonPath('data.0.matches.0.venue.id', $venue->id);
    }

    public function test_admin_can_delete_an_empty_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $championship = $category->championship;

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.championships.categories', $championship))
            ->assertSessionHas('success', 'Categoría eliminada');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_creation_enforces_both_public_parents(): void
    {
        $admin = User::factory()->admin()->create();
        $publicSeason = Season::factory()->publiclyVisible()->create();
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $publicChampionshipWithPrivateSeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);

        $this->actingAs($admin)
            ->post(
                route('admin.categories.store', $privateChampionship),
                $this->validPayload([
                    'name' => 'Privada bajo padres válidos',
                    'is_public' => false,
                ])
            )
            ->assertRedirect(route('admin.championships.categories', $privateChampionship));

        $this->actingAs($admin)
            ->post(
                route('admin.categories.store', $publicChampionship),
                $this->validPayload([
                    'name' => 'Pública bajo padres públicos',
                    'is_public' => true,
                ])
            )
            ->assertRedirect(route('admin.championships.categories', $publicChampionship));

        $this->actingAs($admin)
            ->post(
                route('admin.categories.store', $privateChampionship),
                $this->validPayload([
                    'name' => 'Pública con campeonato privado',
                    'is_public' => 1,
                ])
            )
            ->assertSessionHasErrors('is_public');

        $this->actingAs($admin)
            ->post(
                route('admin.categories.store', $publicChampionshipWithPrivateSeason),
                $this->validPayload([
                    'name' => 'Pública con temporada privada',
                    'is_public' => 1,
                ])
            )
            ->assertSessionHasErrors([
                'is_public' => 'No puedes hacer pública la categoría mientras su campeonato o temporada sean privados.',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Privada bajo padres válidos',
            'is_public' => 0,
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Pública bajo padres públicos',
            'is_public' => 1,
        ]);
        $this->assertDatabaseMissing('categories', ['name' => 'Pública con campeonato privado']);
        $this->assertDatabaseMissing('categories', ['name' => 'Pública con temporada privada']);
    }

    public function test_category_visibility_updates_respect_hierarchy_and_preserve_other_data(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->privatelyVisible()->create([
            'championship_id' => $championship->id,
            'status' => CategoryStatus::ACTIVE->value,
            'image_path' => 'categories/preservar.jpg',
        ]);

        $this->actingAs($admin)
            ->put(
                route('admin.categories.update', $category),
                $this->validPayload([
                    'championship_id' => Championship::factory()->create()->id,
                    'name' => $category->name,
                    'status' => CategoryStatus::ACTIVE->value,
                    'is_public' => 1,
                    'image_path' => 'categories/no-sustituir.jpg',
                ])
            )
            ->assertRedirect(route('admin.championships.categories', $championship));

        $category->refresh();
        $this->assertTrue($category->is_public);
        $this->assertSame($championship->id, $category->championship_id);
        $this->assertSame(CategoryStatus::ACTIVE->value, $category->status);
        $this->assertSame('categories/preservar.jpg', $category->image_path);

        $championship->is_public = false;
        $championship->save();

        $this->actingAs($admin)
            ->put(
                route('admin.categories.update', $category),
                $this->validPayload([
                    'name' => 'No debe persistirse',
                    'is_public' => true,
                ])
            )
            ->assertSessionHasErrors('is_public');

        $this->actingAs($admin)
            ->put(
                route('admin.categories.update', $category),
                $this->validPayload([
                    'name' => $category->name,
                    'status' => CategoryStatus::ACTIVE->value,
                    'is_public' => 0,
                ])
            )
            ->assertRedirect(route('admin.championships.categories', $championship));

        $this->assertFalse($category->fresh()->is_public);
    }

    public function test_category_visibility_must_be_a_boolean(): void
    {
        $admin = User::factory()->admin()->create();
        $championship = Championship::factory()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.categories.store', $championship),
                $this->validPayload([
                    'name' => 'Visibilidad manipulada',
                    'is_public' => 'publicada',
                ])
            )
            ->assertSessionHasErrors('is_public');

        $this->assertDatabaseMissing('categories', ['name' => 'Visibilidad manipulada']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Categoria vàlida',
            'description' => 'Descripció vàlida.',
            'level' => 5,
            'gender' => CategoryGender::MALE->value,
            'status' => CategoryStatus::PENDING->value,
            'is_public' => 0,
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
