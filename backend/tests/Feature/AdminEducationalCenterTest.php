<?php

namespace Tests\Feature;

use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEducationalCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_access_centers_and_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.school.educational-centers.index'))
            ->assertOk()
            ->assertSee('No hay centros educativos para los filtros seleccionados.');
        $this->actingAs($admin)
            ->get(route('admin.school.educational-centers.create'))
            ->assertOk()
            ->assertSee('Los centros nuevos permanecen inactivos');
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Centros educativos')
            ->assertSee('Actividades con centros')
            ->assertSee(route('admin.school.educational-centers.index'))
            ->assertSee(route('admin.school.educational-activities.index'));
    }

    public function test_center_routes_reject_anonymous_normal_and_inactive_admin_users(): void
    {
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);
        $center = EducationalCenter::factory()->active()->create();

        foreach ([
            route('admin.school.educational-centers.index'),
            route('admin.school.educational-centers.create'),
            route('admin.school.educational-centers.show', $center),
            route('admin.school.educational-centers.edit', $center),
        ] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->actingAs($inactiveAdmin)
                ->get($url)
                ->assertRedirect(route('admin.login'));
        }
    }

    public function test_admin_creates_inactive_center_with_normalized_controlled_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.educational-centers.store'),
                $this->centerPayload([
                    'name' => '  CEIP La Pau  ',
                    'locality' => '  Elche  ',
                    'contact_name' => '  Ana Pérez  ',
                    'contact_phone' => '  600 000 000  ',
                    'contact_email' => '  ESCUELA@EXAMPLE.TEST  ',
                    'is_active' => '0',
                    'admin_notes' => '  Entrada por conserjería.  ',
                    'official_code' => 'NO-PERSISTIR',
                ])
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('educational_centers', [
            'name' => 'CEIP La Pau',
            'locality' => 'Elche',
            'contact_name' => 'Ana Pérez',
            'contact_phone' => '600 000 000',
            'contact_email' => 'escuela@example.test',
            'is_active' => false,
            'admin_notes' => 'Entrada por conserjería.',
        ]);
    }

    public function test_admin_activates_updates_and_views_private_center_data(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->inactive()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.school.educational-centers.update', $center),
                $this->centerPayload([
                    'name' => 'Centro actualizado',
                    'locality' => 'Alicante',
                    'contact_name' => '',
                    'contact_email' => '',
                    'is_active' => '1',
                    'admin_notes' => 'Nota sólo administrativa',
                ])
            )
            ->assertRedirect(
                route('admin.school.educational-centers.show', $center)
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('educational_centers', [
            'id' => $center->id,
            'name' => 'Centro actualizado',
            'locality' => 'Alicante',
            'contact_name' => null,
            'contact_email' => null,
            'is_active' => true,
            'admin_notes' => 'Nota sólo administrativa',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.school.educational-centers.show', $center))
            ->assertOk()
            ->assertSee('Nota sólo administrativa')
            ->assertSee('Información privada')
            ->assertSee(
                route('admin.school.educational-activities.create', [
                    'center' => $center->id,
                ])
            );
    }

    public function test_center_validation_preserves_old_input_and_requires_valid_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $url = route('admin.school.educational-centers.create');

        $this->actingAs($admin)
            ->from($url)
            ->post(
                route('admin.school.educational-centers.store'),
                $this->centerPayload([
                    'name' => '',
                    'locality' => '',
                    'contact_email' => 'correo-invalido',
                ])
            )
            ->assertRedirect($url)
            ->assertSessionHasErrors([
                'name',
                'locality',
                'contact_email',
            ])
            ->assertSessionHasInput('contact_email', 'correo-invalido');
    }

    public function test_center_index_filters_state_and_locality_and_shows_activity_summary(): void
    {
        $admin = User::factory()->admin()->create();
        $visible = EducationalCenter::factory()->active()->withContact()->create([
            'name' => 'Centro Alicante',
            'locality' => 'Alicante',
        ]);
        EducationalCenter::factory()->inactive()->create([
            'name' => 'Centro Elche',
            'locality' => 'Elche',
        ]);
        EducationalActivity::factory()
            ->for($visible, 'center')
            ->create(['activity_date' => '2026-09-20']);

        $this->actingAs($admin)
            ->get(route('admin.school.educational-centers.index', [
                'active' => '1',
                'locality' => 'Alicante',
            ]))
            ->assertOk()
            ->assertSee('Centro Alicante')
            ->assertSee('20/09/2026')
            ->assertDontSee('Centro Elche');

        $this->actingAs($admin)
            ->get(route('admin.school.educational-centers.index', [
                'active' => '0',
            ]))
            ->assertOk()
            ->assertSee('Centro Elche')
            ->assertDontSee('Centro Alicante');
    }

    public function test_center_and_location_deletes_are_blocked_by_activity_history(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->active()->create();
        $location = SchoolLocation::factory()->active()->create();
        EducationalActivity::factory()
            ->for($center, 'center')
            ->withLocation($location)
            ->create();

        $this->actingAs($admin)
            ->delete(route('admin.school.educational-centers.destroy', $center))
            ->assertSessionHas('error');
        $this->actingAs($admin)
            ->delete(route('admin.school.locations.destroy', $location))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('educational_centers', ['id' => $center->id]);
        $this->assertDatabaseHas('school_locations', ['id' => $location->id]);
    }

    public function test_unused_center_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.school.educational-centers.destroy', $center))
            ->assertRedirect(route('admin.school.educational-centers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('educational_centers', ['id' => $center->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function centerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Centro educativo',
            'locality' => 'Alicante',
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'is_active' => '0',
            'admin_notes' => null,
        ], $overrides);
    }
}
