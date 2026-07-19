<?php

namespace Tests\Feature;

use App\Enums\SeasonStatus;
use App\Models\Championship;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminSeasonTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_open_the_creation_form_with_real_statuses_and_date_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.seasons.create'));

        $response
            ->assertOk()
            ->assertSee('Nueva temporada')
            ->assertSee('id="name"', false)
            ->assertSee('id="status"', false)
            ->assertSee('id="is_public"', false)
            ->assertSee('name="is_public" value="0"', false)
            ->assertSee('Visible públicamente')
            ->assertSee('id="start_date"', false)
            ->assertSee('type="date"', false)
            ->assertSee('id="end_date"', false);

        foreach (SeasonStatus::cases() as $status) {
            $response->assertSee('value="'.$status->value.'"', false);
        }

        $this->assertStatusSelected($response, SeasonStatus::PLANNED);
    }

    public function test_active_admin_can_create_a_season_with_all_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.seasons.store'), [
            'name' => 'Temporada 2027',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => 1,
            'start_date' => '2027-01-15',
            'end_date' => '2027-11-30',
        ]);

        $response
            ->assertRedirect(route('admin.seasons.index'))
            ->assertSessionHas('success', 'Temporada creada correctamente.');

        $this->assertDatabaseHas('seasons', [
            'name' => 'Temporada 2027',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => 1,
            'start_date' => '2027-01-15',
            'end_date' => '2027-11-30',
        ]);
    }

    public function test_active_admin_can_create_a_season_without_nullable_dates(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.seasons.store'), [
            'name' => 'Temporada sin fechas',
            'status' => SeasonStatus::PLANNED->value,
            'is_public' => 0,
            'start_date' => '',
            'end_date' => '',
        ])->assertRedirect(route('admin.seasons.index'));

        $this->assertDatabaseHas('seasons', [
            'name' => 'Temporada sin fechas',
            'status' => SeasonStatus::PLANNED->value,
            'is_public' => 0,
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function test_non_admin_user_cannot_open_or_submit_the_creation_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.seasons.create'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.seasons.store'), [
                'name' => 'Temporada no autorizada',
                'status' => SeasonStatus::ACTIVE->value,
                'is_public' => 0,
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('seasons', ['name' => 'Temporada no autorizada']);
    }

    public function test_inactive_admin_is_logged_out_when_opening_the_creation_form(): void
    {
        $admin = User::factory()->admin()->create(['active' => false]);

        $response = $this->actingAs($admin)
            ->withSession(['session_sentinel' => 'must-be-removed'])
            ->get(route('admin.seasons.create'));

        $response
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'email' => 'Tu usuario está inactivo.',
            ])
            ->assertSessionMissing('session_sentinel');

        $this->assertGuest('web');
    }

    public function test_creation_requires_a_name_and_a_real_status(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.seasons.store'), [
                'name' => '',
                'status' => SeasonStatus::ACTIVE->value,
                'is_public' => 0,
            ])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.seasons.store'), [
                'name' => 'Temporada inválida',
                'status' => 'pending',
                'is_public' => 0,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseCount('seasons', 0);
    }

    public function test_creation_validates_each_date_and_their_chronology_without_persisting_invalid_data(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.seasons.store'), [
                'name' => 'Inicio inválido',
                'status' => SeasonStatus::PLANNED->value,
                'is_public' => 0,
                'start_date' => 'no-es-fecha',
                'end_date' => '2027-12-31',
            ])
            ->assertSessionHasErrors('start_date');

        $this->actingAs($admin)
            ->post(route('admin.seasons.store'), [
                'name' => 'Fin inválido',
                'status' => SeasonStatus::PLANNED->value,
                'is_public' => 0,
                'start_date' => '2027-01-01',
                'end_date' => 'no-es-fecha',
            ])
            ->assertSessionHasErrors('end_date');

        $this->actingAs($admin)
            ->post(route('admin.seasons.store'), [
                'name' => 'Cronología inválida',
                'status' => SeasonStatus::PLANNED->value,
                'is_public' => 0,
                'start_date' => '2027-06-02',
                'end_date' => '2027-06-01',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('seasons', 0);
    }

    public function test_edit_form_selects_the_cast_enum_and_formats_existing_dates(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create([
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => true,
            'start_date' => '2027-02-03',
            'end_date' => '2027-10-09',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.seasons.edit', $season));

        $response
            ->assertOk()
            ->assertSee('value="2027-02-03"', false)
            ->assertSee('value="2027-10-09"', false);

        $this->assertStatusSelected($response, SeasonStatus::ACTIVE);
        $this->assertStatusNotSelected($response, SeasonStatus::PLANNED);
        $this->assertVisibilityChecked($response);
    }

    public function test_old_input_precedes_stored_values_after_an_update_validation_error(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create([
            'name' => 'Nombre persistido',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => true,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.seasons.edit', $season))
            ->put(route('admin.seasons.update', $season), [
                'name' => 'Nombre recuperado',
                'status' => SeasonStatus::FINISHED->value,
                'is_public' => 0,
                'start_date' => '2028-09-20',
                'end_date' => '2028-09-01',
            ])
            ->assertRedirect(route('admin.seasons.edit', $season))
            ->assertSessionHasErrors('end_date')
            ->assertSessionHasInput([
                'name' => 'Nombre recuperado',
                'status' => SeasonStatus::FINISHED->value,
                'is_public' => 0,
                'start_date' => '2028-09-20',
                'end_date' => '2028-09-01',
            ]);

        $response = $this->actingAs($admin)->get(route('admin.seasons.edit', $season));

        $response
            ->assertOk()
            ->assertSee('value="Nombre recuperado"', false)
            ->assertSee('value="2028-09-20"', false)
            ->assertSee('value="2028-09-01"', false);

        $this->assertStatusSelected($response, SeasonStatus::FINISHED);
        $this->assertStatusNotSelected($response, SeasonStatus::ACTIVE);
        $this->assertVisibilityNotChecked($response);
    }

    public function test_active_admin_can_update_all_season_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create([
            'name' => 'Temporada original',
            'status' => SeasonStatus::PLANNED->value,
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.seasons.update', $season), [
                'name' => 'Temporada actualizada',
                'status' => SeasonStatus::FINISHED->value,
                'is_public' => 1,
                'start_date' => '2027-03-01',
                'end_date' => '2027-11-15',
            ]);

        $response
            ->assertRedirect(route('admin.seasons.index'))
            ->assertSessionHas('success', 'Temporada actualizada.');

        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'name' => 'Temporada actualizada',
            'status' => SeasonStatus::FINISHED->value,
            'is_public' => 1,
            'start_date' => '2027-03-01',
            'end_date' => '2027-11-15',
        ]);
    }

    public function test_active_admin_can_clear_nullable_dates_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create([
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.seasons.update', $season), [
                'name' => $season->name,
                'status' => SeasonStatus::ACTIVE->value,
                'is_public' => 0,
                'start_date' => '',
                'end_date' => '',
            ])
            ->assertRedirect(route('admin.seasons.index'));

        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function test_invalid_update_does_not_change_the_previous_data(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create([
            'name' => 'Temporada intacta',
            'status' => SeasonStatus::ACTIVE->value,
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.seasons.update', $season), [
                'name' => 'No debe persistirse',
                'status' => SeasonStatus::CANCELLED->value,
                'is_public' => 0,
                'start_date' => '2028-12-31',
                'end_date' => '2028-01-01',
            ])
            ->assertSessionHasErrors('end_date');

        $season->refresh();

        $this->assertSame('Temporada intacta', $season->name);
        $this->assertSame(SeasonStatus::ACTIVE, $season->status);
        $this->assertSame('2027-01-01', $season->start_date?->format('Y-m-d'));
        $this->assertSame('2027-12-31', $season->end_date?->format('Y-m-d'));
    }

    public function test_list_keeps_showing_existing_season_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create([
            'name' => 'Temporada visible',
            'status' => SeasonStatus::CANCELLED->value,
            'start_date' => '2027-04-05',
            'end_date' => '2027-08-09',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.seasons.index'))
            ->assertOk()
            ->assertSee('Temporada visible')
            ->assertSee('cancelled')
            ->assertSee('Privada')
            ->assertSee('05/04/2027')
            ->assertSee('09/08/2027')
            ->assertSee(route('admin.seasons.edit', $season));
    }

    public function test_active_admin_can_delete_an_existing_season(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.seasons.destroy', $season))
            ->assertRedirect(route('admin.seasons.index'))
            ->assertSessionHas('success', 'Temporada eliminada.');

        $this->assertDatabaseMissing('seasons', ['id' => $season->id]);
    }

    public function test_public_season_can_be_hidden_without_changing_status_or_child_flags(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->publiclyVisible()->create([
            'status' => SeasonStatus::ACTIVE->value,
        ]);
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.seasons.update', $season), [
                'name' => $season->name,
                'status' => SeasonStatus::ACTIVE->value,
                'is_public' => 0,
                'start_date' => $season->start_date?->format('Y-m-d'),
                'end_date' => $season->end_date?->format('Y-m-d'),
            ])
            ->assertRedirect(route('admin.seasons.index'));

        $this->assertFalse($season->fresh()->is_public);
        $this->assertSame(SeasonStatus::ACTIVE, $season->fresh()->status);
        $this->assertTrue($championship->fresh()->is_public);
    }

    public function test_season_visibility_must_be_a_boolean(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.seasons.store'), [
                'name' => 'Visibilidad manipulada',
                'status' => SeasonStatus::PLANNED->value,
                'is_public' => 'publicada',
            ])
            ->assertSessionHasErrors('is_public');

        $this->assertDatabaseMissing('seasons', ['name' => 'Visibilidad manipulada']);
    }

    public function test_public_season_contract_remains_unchanged(): void
    {
        $season = Season::factory()->create([
            'name' => 'Temporada pública',
            'status' => SeasonStatus::ACTIVE->value,
            'is_public' => false,
            'start_date' => '2027-01-02',
            'end_date' => '2027-12-30',
        ]);

        $response = $this->getJson('/api/v1/seasons');

        $response
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.0.id', $season->id)
            ->assertJsonPath('data.0.name', 'Temporada pública')
            ->assertJsonPath('data.0.slug', null)
            ->assertJsonPath('data.0.status', SeasonStatus::ACTIVE->value)
            ->assertJsonPath('data.0.start_date', '2027-01-02')
            ->assertJsonPath('data.0.end_date', '2027-12-30')
            ->assertJsonPath('data.0.championships', []);

        $this->assertSame([
            'id',
            'name',
            'slug',
            'status',
            'start_date',
            'end_date',
            'championships',
            'created_at',
            'updated_at',
        ], array_keys($response->json('data.0')));
        $response->assertJsonMissingPath('data.0.is_public');
    }

    private function assertStatusSelected(TestResponse $response, SeasonStatus $status): void
    {
        $this->assertMatchesRegularExpression(
            '/<option\s+value="'.preg_quote($status->value, '/').'"[^>]*\bselected\b[^>]*>/',
            $response->getContent()
        );
    }

    private function assertStatusNotSelected(TestResponse $response, SeasonStatus $status): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<option\s+value="'.preg_quote($status->value, '/').'"[^>]*\bselected\b[^>]*>/',
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
