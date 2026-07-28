<?php

namespace Tests\Feature;

use App\Enums\EducationalActivityStatus;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use App\Services\EducationalActivityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchoolEducationalActivityModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_defaults_casts_and_controlled_mass_assignment(): void
    {
        $this->assertTrue(Schema::hasColumns('educational_centers', [
            'id',
            'name',
            'locality',
            'contact_name',
            'contact_phone',
            'contact_email',
            'is_active',
            'admin_notes',
        ]));
        $this->assertTrue(Schema::hasColumns('educational_activities', [
            'id',
            'educational_center_id',
            'school_location_id',
            'name',
            'activity_date',
            'starts_at',
            'ends_at',
            'expected_students',
            'status',
            'admin_notes',
        ]));

        $center = EducationalCenter::query()->create([
            'name' => 'Centro por defecto',
            'locality' => 'Elche',
        ]);
        $activity = EducationalActivity::query()->create([
            'educational_center_id' => $center->id,
            'name' => 'Actividad por defecto',
            'activity_date' => '2026-10-01',
            'status' => EducationalActivityStatus::CANCELLED->value,
        ]);

        $this->assertFalse($center->fresh()->is_active);
        $this->assertSame(
            EducationalActivityStatus::PLANNED,
            $activity->fresh()->status
        );
        $this->assertSame(
            '2026-10-01',
            $activity->fresh()->activity_date->format('Y-m-d')
        );
        $this->assertNull($activity->fresh()->school_location_id);
        $this->assertNull($activity->fresh()->expected_students);
    }

    public function test_factories_expose_valid_expressive_states_and_relations(): void
    {
        $center = EducationalCenter::factory()
            ->active()
            ->withContact()
            ->create();
        $location = SchoolLocation::factory()->active()->create();
        $completed = EducationalActivity::factory()
            ->for($center, 'center')
            ->withLocation($location)
            ->withSchedule('10:00', '12:30')
            ->completed()
            ->withExpectedStudents(42)
            ->create();
        $cancelled = EducationalActivity::factory()
            ->for($center, 'center')
            ->withoutLocation()
            ->withoutSchedule()
            ->cancelled()
            ->create();

        $this->assertTrue($center->is_active);
        $this->assertNotNull($center->contact_email);
        $this->assertTrue($completed->center->is($center));
        $this->assertTrue($completed->location->is($location));
        $this->assertSame(EducationalActivityStatus::COMPLETED, $completed->status);
        $this->assertSame(42, $completed->expected_students);
        $this->assertSame('10:00', $completed->startsAtLabel());
        $this->assertSame('12:30', $completed->endsAtLabel());
        $this->assertSame(EducationalActivityStatus::CANCELLED, $cancelled->status);
        $this->assertNull($cancelled->location);
        $this->assertTrue($center->activities->contains($completed));
        $this->assertTrue($location->educationalActivities->contains($completed));
        $this->assertFalse(EducationalCenter::factory()->inactive()->create()->is_active);
        $this->assertNull(
            EducationalCenter::factory()->withoutContact()->create()->contact_name
        );
    }

    public function test_scopes_filter_and_order_centers_and_activities_stably(): void
    {
        $centerB = EducationalCenter::factory()->active()->create([
            'name' => 'Colegio B',
            'locality' => 'Alicante',
        ]);
        $centerA = EducationalCenter::factory()->active()->create([
            'name' => 'Colegio A',
            'locality' => 'Alicante',
        ]);
        $centerC = EducationalCenter::factory()->inactive()->create([
            'name' => 'Colegio C',
            'locality' => 'Elche',
        ]);

        $old = EducationalActivity::factory()
            ->for($centerA, 'center')
            ->planned()
            ->create(['activity_date' => '2026-09-01']);
        $newerFirst = EducationalActivity::factory()
            ->for($centerA, 'center')
            ->completed()
            ->create(['activity_date' => '2026-10-01']);
        $newerSecond = EducationalActivity::factory()
            ->for($centerB, 'center')
            ->cancelled()
            ->create(['activity_date' => '2026-10-01']);

        $this->assertEquals(
            [$centerA->id, $centerB->id, $centerC->id],
            EducationalCenter::query()->ordered()->pluck('id')->all()
        );
        $this->assertEquals(
            [$centerA->id, $centerB->id],
            EducationalCenter::query()->active()->ordered()->pluck('id')->all()
        );
        $this->assertEquals(
            [$newerSecond->id, $newerFirst->id, $old->id],
            EducationalActivity::query()->ordered()->pluck('id')->all()
        );
        $this->assertEquals(
            [$newerFirst->id],
            EducationalActivity::query()
                ->forCenter($centerA->id)
                ->withStatus(EducationalActivityStatus::COMPLETED)
                ->betweenDates('2026-09-15', '2026-10-15')
                ->pluck('id')
                ->all()
        );
    }

    public function test_database_restricts_parent_deletes_and_allows_homonymous_centers(): void
    {
        $first = EducationalCenter::factory()->active()->create([
            'name' => 'CEIP La Pau',
            'locality' => 'Alicante',
        ]);
        EducationalCenter::factory()->active()->create([
            'name' => 'CEIP La Pau',
            'locality' => 'Elche',
        ]);
        $location = SchoolLocation::factory()->active()->create();
        EducationalActivity::factory()
            ->for($first, 'center')
            ->withLocation($location)
            ->create();

        $this->assertSame(
            2,
            EducationalCenter::query()->where('name', 'CEIP La Pau')->count()
        );

        foreach ([$first, $location] as $model) {
            try {
                $model->delete();
                $this->fail('La clave foránea debería haber protegido el histórico.');
            } catch (QueryException) {
                $this->assertDatabaseHas($model->getTable(), ['id' => $model->id]);
            }
        }
    }

    public function test_service_controls_status_transitions_completion_and_deletion(): void
    {
        $service = app(EducationalActivityService::class);
        $center = EducationalCenter::factory()->active()->create();
        $activity = $service->create([
            'educational_center_id' => $center->id,
            'school_location_id' => null,
            'name' => 'Actividad controlada',
            'activity_date' => '2026-11-12',
            'starts_at' => null,
            'ends_at' => null,
            'expected_students' => null,
            'admin_notes' => null,
            'status' => EducationalActivityStatus::COMPLETED->value,
        ]);

        $this->assertSame(EducationalActivityStatus::PLANNED, $activity->status);

        try {
            $service->complete($activity);
            $this->fail('No debería completarse sin alumnado previsto.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                EducationalActivityService::COMPLETION_STUDENTS_ERROR,
                $exception->errors()['expected_students'][0]
            );
        }

        $activity = $service->update($activity, [
            'educational_center_id' => $center->id,
            'school_location_id' => null,
            'name' => $activity->name,
            'activity_date' => '2026-11-12',
            'starts_at' => null,
            'ends_at' => null,
            'expected_students' => 30,
            'admin_notes' => null,
        ]);
        $completed = $service->complete($activity);

        $this->assertSame(EducationalActivityStatus::COMPLETED, $completed->status);

        foreach (['cancel', 'complete', 'delete'] as $operation) {
            try {
                $service->{$operation}($completed);
                $this->fail('El estado histórico debería impedir la operación.');
            } catch (ValidationException) {
                $this->assertDatabaseHas('educational_activities', [
                    'id' => $completed->id,
                    'status' => EducationalActivityStatus::COMPLETED->value,
                ]);
            }
        }

        $planned = EducationalActivity::factory()
            ->for($center, 'center')
            ->planned()
            ->create();
        $service->delete($planned);
        $this->assertDatabaseMissing('educational_activities', ['id' => $planned->id]);
    }

    public function test_service_preserves_inactive_historical_relations_but_rejects_new_ones(): void
    {
        $service = app(EducationalActivityService::class);
        $activeCenter = EducationalCenter::factory()->active()->create();
        $inactiveCenter = EducationalCenter::factory()->inactive()->create();
        $activeLocation = SchoolLocation::factory()->active()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();
        $activity = EducationalActivity::factory()
            ->for($activeCenter, 'center')
            ->withLocation($activeLocation)
            ->withExpectedStudents()
            ->create();

        $activeCenter->update(['is_active' => false]);
        $activeLocation->update(['is_active' => false]);

        $updated = $service->update($activity, [
            'educational_center_id' => $activeCenter->id,
            'school_location_id' => $activeLocation->id,
            'name' => 'Corrección histórica',
            'activity_date' => '2026-12-01',
            'starts_at' => null,
            'ends_at' => null,
            'expected_students' => 25,
            'admin_notes' => null,
        ]);

        $this->assertSame('Corrección histórica', $updated->name);

        try {
            $service->update($updated, [
                'educational_center_id' => $inactiveCenter->id,
                'school_location_id' => $inactiveLocation->id,
                'name' => $updated->name,
                'activity_date' => '2026-12-01',
                'starts_at' => null,
                'ends_at' => null,
                'expected_students' => 25,
                'admin_notes' => null,
            ]);
            $this->fail('No debería admitir relaciones inactivas nuevas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'educational_center_id',
                $exception->errors()
            );
        }

        try {
            $service->create([
                'educational_center_id' => $inactiveCenter->id,
                'school_location_id' => null,
                'name' => 'No válida',
                'activity_date' => '2026-12-02',
                'starts_at' => null,
                'ends_at' => null,
                'expected_students' => null,
                'admin_notes' => null,
            ]);
            $this->fail('Un centro inactivo no debe recibir actividades nuevas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'educational_center_id',
                $exception->errors()
            );
        }
    }
}
