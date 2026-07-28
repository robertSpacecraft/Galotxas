<?php

namespace Tests\Feature;

use App\Enums\EducationalActivityStatus;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEducationalActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_routes_require_an_active_administrator(): void
    {
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);
        $activity = EducationalActivity::factory()->create();

        foreach ([
            route('admin.school.educational-activities.index'),
            route('admin.school.educational-activities.create'),
            route('admin.school.educational-activities.show', $activity),
            route('admin.school.educational-activities.edit', $activity),
        ] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->actingAs($inactiveAdmin)
                ->get($url)
                ->assertRedirect(route('admin.login'));
        }

        foreach ([
            'admin.school.educational-activities.complete',
            'admin.school.educational-activities.cancel',
        ] as $routeName) {
            $url = route($routeName, $activity);
            $this->post($url)->assertRedirect(route('admin.login'));
            $this->actingAs($user)->post($url)->assertForbidden();
            $this->actingAs($inactiveAdmin)
                ->post($url)
                ->assertRedirect(route('admin.login'));
        }
    }

    public function test_admin_creates_planned_activity_with_all_operational_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->active()->create();
        $location = SchoolLocation::factory()->active()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.educational-activities.store'),
                $this->activityPayload($center, [
                    'school_location_id' => $location->id,
                    'name' => '  Taller de iniciación  ',
                    'activity_date' => '2026-10-15',
                    'starts_at' => '09:30',
                    'ends_at' => '12:00',
                    'expected_students' => '35',
                    'admin_notes' => '  Confirmar material.  ',
                    'status' => EducationalActivityStatus::COMPLETED->value,
                    'participant_email' => 'no@example.test',
                ])
            )
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('educational_activities', [
            'educational_center_id' => $center->id,
            'school_location_id' => $location->id,
            'name' => 'Taller de iniciación',
            'activity_date' => '2026-10-15',
            'starts_at' => '09:30:00',
            'ends_at' => '12:00:00',
            'expected_students' => 35,
            'status' => EducationalActivityStatus::PLANNED->value,
            'admin_notes' => 'Confirmar material.',
        ]);
    }

    public function test_create_form_preselects_active_center_and_excludes_inactive_options(): void
    {
        $admin = User::factory()->admin()->create();
        $active = EducationalCenter::factory()->active()->create([
            'name' => 'Centro activo',
        ]);
        $inactive = EducationalCenter::factory()->inactive()->create([
            'name' => 'Centro inactivo',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.school.educational-activities.create', [
                'center' => $active->id,
            ]))
            ->assertOk()
            ->assertSee('Centro activo')
            ->assertSee('selected', false)
            ->assertDontSee('Centro inactivo');

        $this->actingAs($admin)
            ->get(route('admin.school.educational-activities.create', [
                'center' => $inactive->id,
            ]))
            ->assertOk()
            ->assertDontSee('Centro inactivo');
    }

    public function test_validation_rejects_inactive_relations_invalid_hours_and_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $inactiveCenter = EducationalCenter::factory()->inactive()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.educational-activities.store'),
                $this->activityPayload($inactiveCenter, [
                    'school_location_id' => $inactiveLocation->id,
                    'name' => '',
                    'activity_date' => '15/10/2026',
                    'starts_at' => '12:00',
                    'ends_at' => '09:00',
                    'expected_students' => '0',
                ])
            )
            ->assertSessionHasErrors([
                'educational_center_id',
                'school_location_id',
                'name',
                'activity_date',
                'ends_at',
                'expected_students',
            ]);

        $activeCenter = EducationalCenter::factory()->active()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.educational-activities.store'),
                $this->activityPayload($activeCenter, [
                    'starts_at' => '09:00',
                    'ends_at' => null,
                ])
            )
            ->assertSessionHasErrors('ends_at');
        $this->actingAs($admin)
            ->post(
                route('admin.school.educational-activities.store'),
                $this->activityPayload($activeCenter, [
                    'starts_at' => null,
                    'ends_at' => '12:00',
                ])
            )
            ->assertSessionHasErrors('starts_at');
    }

    public function test_activity_validation_preserves_old_input(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->active()->create();
        $url = route('admin.school.educational-activities.create');

        $this->actingAs($admin)
            ->from($url)
            ->post(
                route('admin.school.educational-activities.store'),
                $this->activityPayload($center, [
                    'name' => '',
                    'admin_notes' => 'Dato que debe volver al formulario',
                ])
            )
            ->assertRedirect($url)
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput(
                'admin_notes',
                'Dato que debe volver al formulario'
            );
    }

    public function test_admin_updates_data_without_allowing_arbitrary_status_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->active()->create();
        $otherCenter = EducationalCenter::factory()->active()->create();
        $activity = EducationalActivity::factory()
            ->for($center, 'center')
            ->withExpectedStudents()
            ->create();

        $this->actingAs($admin)
            ->put(
                route('admin.school.educational-activities.update', $activity),
                $this->activityPayload($otherCenter, [
                    'name' => 'Actividad corregida',
                    'activity_date' => '2026-11-20',
                    'expected_students' => 40,
                    'status' => EducationalActivityStatus::CANCELLED->value,
                ])
            )
            ->assertRedirect(
                route('admin.school.educational-activities.show', $activity)
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('educational_activities', [
            'id' => $activity->id,
            'educational_center_id' => $otherCenter->id,
            'name' => 'Actividad corregida',
            'activity_date' => '2026-11-20',
            'expected_students' => 40,
            'status' => EducationalActivityStatus::PLANNED->value,
        ]);
    }

    public function test_edit_preserves_inactive_historical_relations_but_rejects_changes_to_them(): void
    {
        $admin = User::factory()->admin()->create();
        $historicalCenter = EducationalCenter::factory()->active()->create();
        $historicalLocation = SchoolLocation::factory()->active()->create();
        $inactiveCenter = EducationalCenter::factory()->inactive()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();
        $activity = EducationalActivity::factory()
            ->for($historicalCenter, 'center')
            ->withLocation($historicalLocation)
            ->create();
        $historicalCenter->update(['is_active' => false]);
        $historicalLocation->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get(route('admin.school.educational-activities.edit', $activity))
            ->assertOk()
            ->assertSee('inactivo, relación histórica')
            ->assertSee('inactiva, relación histórica');

        $this->actingAs($admin)
            ->put(
                route('admin.school.educational-activities.update', $activity),
                $this->activityPayload($historicalCenter, [
                    'school_location_id' => $historicalLocation->id,
                    'name' => 'Corrección histórica',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put(
                route('admin.school.educational-activities.update', $activity),
                $this->activityPayload($inactiveCenter, [
                    'school_location_id' => $inactiveLocation->id,
                ])
            )
            ->assertSessionHasErrors([
                'educational_center_id',
                'school_location_id',
            ]);
    }

    public function test_completion_requires_students_and_transitions_are_final(): void
    {
        $admin = User::factory()->admin()->create();
        $withoutStudents = EducationalActivity::factory()->planned()->create();

        $this->actingAs($admin)
            ->post(route(
                'admin.school.educational-activities.complete',
                $withoutStudents
            ))
            ->assertSessionHasErrors('expected_students');
        $this->assertSame(
            EducationalActivityStatus::PLANNED,
            $withoutStudents->fresh()->status
        );

        $completed = EducationalActivity::factory()
            ->planned()
            ->withExpectedStudents(28)
            ->create();
        $this->actingAs($admin)
            ->post(route(
                'admin.school.educational-activities.complete',
                $completed
            ))
            ->assertSessionHas('success');
        $this->assertSame(
            EducationalActivityStatus::COMPLETED,
            $completed->fresh()->status
        );

        $this->actingAs($admin)
            ->post(route(
                'admin.school.educational-activities.cancel',
                $completed
            ))
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)
            ->post(route(
                'admin.school.educational-activities.complete',
                $completed
            ))
            ->assertSessionHasErrors('status');

        $cancelled = EducationalActivity::factory()->planned()->create();
        $this->actingAs($admin)
            ->post(route(
                'admin.school.educational-activities.cancel',
                $cancelled
            ))
            ->assertSessionHas('success');
        $this->assertSame(
            EducationalActivityStatus::CANCELLED,
            $cancelled->fresh()->status
        );
        $this->actingAs($admin)
            ->post(route(
                'admin.school.educational-activities.complete',
                $cancelled
            ))
            ->assertSessionHasErrors('status');
    }

    public function test_completed_activity_corrections_keep_positive_students(): void
    {
        $admin = User::factory()->admin()->create();
        $activity = EducationalActivity::factory()->completed()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.school.educational-activities.update', $activity),
                $this->activityPayload($activity->center, [
                    'expected_students' => null,
                ])
            )
            ->assertSessionHasErrors('expected_students');
    }

    public function test_only_planned_activities_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $planned = EducationalActivity::factory()->planned()->create();
        $completed = EducationalActivity::factory()->completed()->create();
        $cancelled = EducationalActivity::factory()->cancelled()->create();

        $this->actingAs($admin)
            ->delete(route(
                'admin.school.educational-activities.destroy',
                $planned
            ))
            ->assertRedirect(
                route('admin.school.educational-activities.index')
            )
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('educational_activities', ['id' => $planned->id]);

        foreach ([$completed, $cancelled] as $historical) {
            $this->actingAs($admin)
                ->delete(route(
                    'admin.school.educational-activities.destroy',
                    $historical
                ))
                ->assertSessionHasErrors('status');
            $this->assertDatabaseHas('educational_activities', [
                'id' => $historical->id,
            ]);
        }
    }

    public function test_index_filters_center_status_and_date_range_and_orders_results(): void
    {
        $admin = User::factory()->admin()->create();
        $center = EducationalCenter::factory()->active()->create();
        $otherCenter = EducationalCenter::factory()->active()->create();
        $older = EducationalActivity::factory()
            ->for($center, 'center')
            ->completed()
            ->create([
                'name' => 'Actividad incluida antigua',
                'activity_date' => '2026-09-10',
            ]);
        $newer = EducationalActivity::factory()
            ->for($center, 'center')
            ->completed()
            ->create([
                'name' => 'Actividad incluida nueva',
                'activity_date' => '2026-09-20',
            ]);
        EducationalActivity::factory()
            ->for($otherCenter, 'center')
            ->completed()
            ->create([
                'name' => 'Otro centro',
                'activity_date' => '2026-09-15',
            ]);
        EducationalActivity::factory()
            ->for($center, 'center')
            ->planned()
            ->create([
                'name' => 'Otro estado',
                'activity_date' => '2026-09-15',
            ]);

        $this->actingAs($admin)
            ->get(route('admin.school.educational-activities.index', [
                'center' => $center->id,
                'status' => EducationalActivityStatus::COMPLETED->value,
                'date_from' => '2026-09-01',
                'date_to' => '2026-09-30',
            ]))
            ->assertOk()
            ->assertSeeInOrder([$newer->name, $older->name])
            ->assertDontSee('Otro centro')
            ->assertDontSee('Otro estado');
    }

    public function test_detail_only_shows_valid_actions_for_current_status(): void
    {
        $admin = User::factory()->admin()->create();
        $planned = EducationalActivity::factory()->planned()->create();
        $completed = EducationalActivity::factory()->completed()->create();

        $this->actingAs($admin)
            ->get(route('admin.school.educational-activities.show', $planned))
            ->assertOk()
            ->assertSee(route(
                'admin.school.educational-activities.complete',
                $planned
            ))
            ->assertSee(route(
                'admin.school.educational-activities.cancel',
                $planned
            ))
            ->assertSee('Información privada');

        $this->actingAs($admin)
            ->get(route('admin.school.educational-activities.show', $completed))
            ->assertOk()
            ->assertDontSee(route(
                'admin.school.educational-activities.complete',
                $completed
            ))
            ->assertDontSee(route(
                'admin.school.educational-activities.cancel',
                $completed
            ))
            ->assertSee('El estado histórico es definitivo');
    }

    public function test_no_public_or_api_routes_expose_centers_or_activities(): void
    {
        foreach ([
            '/api/v1/educational-centers',
            '/api/v1/educational-activities',
            '/api/v1/school/educational-centers',
            '/api/v1/school/educational-activities',
        ] as $url) {
            $this->getJson($url)->assertNotFound();
        }

        foreach ([
            '/educational-centers',
            '/educational-activities',
            '/school/educational-centers',
            '/school/educational-activities',
        ] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function activityPayload(
        EducationalCenter $center,
        array $overrides = []
    ): array {
        return array_merge([
            'educational_center_id' => $center->id,
            'school_location_id' => null,
            'name' => 'Actividad educativa',
            'activity_date' => '2026-10-15',
            'starts_at' => null,
            'ends_at' => null,
            'expected_students' => null,
            'admin_notes' => null,
        ], $overrides);
    }
}
