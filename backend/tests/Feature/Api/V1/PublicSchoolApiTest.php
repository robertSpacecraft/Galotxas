<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SchoolDayOfWeek;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Models\SchoolSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSchoolApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_or_private_program_returns_the_same_public_empty_contract(): void
    {
        $privateProgram = SchoolProgram::factory()->create([
            'name' => 'Programa interno confidencial',
            'contact_phone' => '600 999 999',
            'contact_email' => 'privado@example.test',
            'enrollments_open' => true,
        ]);

        $before = $privateProgram->fresh()->getAttributes();

        $response = $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => null,
            ]);

        $this->assertSame($before, $privateProgram->fresh()->getAttributes());
        $this->assertStringNotContainsString(
            'Programa interno confidencial',
            $response->getContent()
        );
        $this->assertStringNotContainsString(
            'privado@example.test',
            $response->getContent()
        );

        $privateProgram->delete();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => null,
            ]);
    }

    public function test_anonymous_public_read_returns_the_exact_minimum_contract(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsClosed()
            ->create([
                'name' => 'Escuela permanente',
            ]);

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'name' => 'Escuela permanente',
                    'description' => null,
                    'enrollment_information' => null,
                    'enrollment_status' => 'unavailable',
                    'enrollments_open' => false,
                    'privacy_notice' => [
                        'id' => 'NOTICE-SCHOOL-ENROLLMENT',
                        'version' => '1.0.0',
                        'privacy_url' => '/legal/privacidad',
                    ],
                    'default_location' => null,
                    'levels' => [],
                    'public_identity_authorization' => [
                        'enabled' => false,
                    ],
                ],
            ]);
    }

    public function test_complete_public_program_uses_closed_resources_and_hh_mm_times(): void
    {
        config(['school.enrollment_enabled' => true]);

        $defaultLocation = SchoolLocation::factory()->active()->create([
            'name' => 'Canchas de Monóvar',
            'locality' => 'Monóvar',
            'address' => null,
            'admin_notes' => 'Nunca pública',
        ]);
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create([
                'name' => 'Escuela de Galotxas',
                'public_description' => 'Presentación pública de prueba.',
                'enrollment_information' => 'Proceso público de prueba.',
                'default_school_location_id' => $defaultLocation->id,
                'contact_phone' => '600 000 000',
                'contact_email' => 'escuela@example.test',
            ]);
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create([
                'name' => 'Infantil/juvenil',
                'minimum_age' => 8,
                'maximum_age' => 17,
            ]);
        $schedule = SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($defaultLocation, 'location')
            ->active()
            ->onDay(SchoolDayOfWeek::TUESDAY)
            ->between('17:00:00', '18:30:00')
            ->create();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'name' => 'Escuela de Galotxas',
                    'description' => 'Presentación pública de prueba.',
                    'enrollment_information' => 'Proceso público de prueba.',
                    'enrollment_status' => 'open',
                    'enrollments_open' => true,
                    'privacy_notice' => [
                        'id' => 'NOTICE-SCHOOL-ENROLLMENT',
                        'version' => '1.0.0',
                        'privacy_url' => '/legal/privacidad',
                    ],
                    'default_location' => [
                        'id' => $defaultLocation->id,
                        'name' => 'Canchas de Monóvar',
                        'locality' => 'Monóvar',
                        'address' => null,
                    ],
                    'levels' => [
                        [
                            'id' => $level->id,
                            'name' => 'Infantil/juvenil',
                            'minimum_age' => 8,
                            'maximum_age' => 17,
                            'schedules' => [
                                [
                                    'id' => $schedule->id,
                                    'day_of_week' => 2,
                                    'starts_at' => '17:00',
                                    'ends_at' => '18:30',
                                    'location' => [
                                        'id' => $defaultLocation->id,
                                        'name' => 'Canchas de Monóvar',
                                        'locality' => 'Monóvar',
                                        'address' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'public_identity_authorization' => [
                        'enabled' => false,
                    ],
                ],
            ]);
    }

    public function test_incomplete_configuration_is_unavailable_without_exposing_operational_contact(): void
    {
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->create([
                'default_school_location_id' => $inactiveLocation->id,
                'contact_phone' => '600 111 111',
                'contact_email' => null,
            ]);
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'unavailable')
            ->assertJsonMissingPath('data.contact')
            ->assertJsonPath('data.default_location', null)
            ->assertJsonPath('data.levels.0.id', $level->id)
            ->assertJsonCount(0, 'data.levels.0.schedules');
    }

    public function test_only_public_active_levels_and_effective_schedules_are_returned(): void
    {
        $program = SchoolProgram::factory()->publiclyVisible()->create();
        $otherProgram = SchoolProgram::factory()->create();
        $activeLocation = SchoolLocation::factory()->active()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();

        $visibleLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create(['name' => 'Visible']);
        $privateLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create(['name' => 'Privado']);
        $inactiveLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->publiclyVisible()
            ->create(['name' => 'Inactivo']);
        $otherLevel = SchoolLevel::factory()
            ->for($otherProgram, 'program')
            ->active()
            ->publiclyVisible()
            ->create(['name' => 'Otro programa']);

        $visibleSchedule = SchoolSchedule::factory()
            ->for($visibleLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('09:00', '10:00')
            ->create();
        SchoolSchedule::factory()
            ->for($visibleLevel, 'level')
            ->for($activeLocation, 'location')
            ->inactive()
            ->between('10:00', '11:00')
            ->create();
        SchoolSchedule::factory()
            ->for($visibleLevel, 'level')
            ->for($inactiveLocation, 'location')
            ->active()
            ->between('11:00', '12:00')
            ->create();
        SchoolSchedule::factory()
            ->for($privateLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('12:00', '13:00')
            ->create();
        SchoolSchedule::factory()
            ->for($inactiveLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('13:00', '14:00')
            ->create();
        SchoolSchedule::factory()
            ->for($otherLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('14:00', '15:00')
            ->create();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonCount(1, 'data.levels')
            ->assertJsonPath('data.levels.0.id', $visibleLevel->id)
            ->assertJsonCount(1, 'data.levels.0.schedules')
            ->assertJsonPath(
                'data.levels.0.schedules.0.id',
                $visibleSchedule->id
            );
    }

    public function test_levels_and_schedules_have_stable_public_order(): void
    {
        $program = SchoolProgram::factory()->publiclyVisible()->create();
        $location = SchoolLocation::factory()->active()->create();
        $levelWithLowestOrder = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create(['sort_order' => 5]);
        $firstTiedLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create(['sort_order' => 10]);
        $secondTiedLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create(['sort_order' => 10]);

        $latestDay = SchoolSchedule::factory()
            ->for($levelWithLowestOrder, 'level')
            ->for($location, 'location')
            ->active()
            ->onDay(5)
            ->between('09:00', '10:00')
            ->create(['sort_order' => 0]);
        $laterTime = SchoolSchedule::factory()
            ->for($levelWithLowestOrder, 'level')
            ->for($location, 'location')
            ->active()
            ->onDay(2)
            ->between('18:00', '19:00')
            ->create(['sort_order' => 0]);
        $higherSortOrder = SchoolSchedule::factory()
            ->for($levelWithLowestOrder, 'level')
            ->for($location, 'location')
            ->active()
            ->onDay(2)
            ->between('17:00', '18:00')
            ->create(['sort_order' => 20]);
        $lowerSortOrder = SchoolSchedule::factory()
            ->for($levelWithLowestOrder, 'level')
            ->for($location, 'location')
            ->active()
            ->onDay(2)
            ->between('17:00', '18:30')
            ->create(['sort_order' => 10]);

        $response = $this->getJson('/api/v1/school')->assertOk();

        $this->assertSame(
            [
                $levelWithLowestOrder->id,
                $firstTiedLevel->id,
                $secondTiedLevel->id,
            ],
            array_column($response->json('data.levels'), 'id')
        );
        $this->assertSame(
            [
                $lowerSortOrder->id,
                $higherSortOrder->id,
                $laterTime->id,
                $latestDay->id,
            ],
            array_column($response->json('data.levels.0.schedules'), 'id')
        );
    }

    public function test_response_recursively_excludes_private_school_data_and_entities(): void
    {
        $location = SchoolLocation::factory()->active()->create([
            'admin_notes' => 'Nota privada de ubicación',
        ]);
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create([
                'default_school_location_id' => $location->id,
            ]);
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create();
        SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->active()
            ->create();
        SchoolEnrollment::factory()
            ->for($program, 'program')
            ->create([
                'participant_name' => 'Participante privado',
                'contact_email' => 'inscripcion-privada@example.test',
                'admin_notes' => 'Nota privada de inscripción',
            ]);
        $center = EducationalCenter::factory()->active()->create([
            'name' => 'Centro privado',
            'contact_email' => 'centro-privado@example.test',
        ]);
        EducationalActivity::factory()
            ->for($center, 'center')
            ->create([
                'name' => 'Actividad privada',
            ]);

        $response = $this->getJson('/api/v1/school')->assertOk();
        $content = $response->getContent();
        $keys = $this->recursiveKeys($response->json());

        foreach ([
            'is_public',
            'is_active',
            'sort_order',
            'public_slot',
            'admin_notes',
            'school_program_id',
            'school_level_id',
            'school_location_id',
            'created_at',
            'updated_at',
            'enrollments',
            'users',
            'educational_centers',
            'educational_activities',
        ] as $privateKey) {
            $this->assertNotContains($privateKey, $keys);
        }

        foreach ([
            'Participante privado',
            'inscripcion-privada@example.test',
            'Nota privada de inscripción',
            'Centro privado',
            'centro-privado@example.test',
            'Actividad privada',
            'Nota privada de ubicación',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $content);
        }
    }

    public function test_query_count_is_constant_as_levels_schedules_and_locations_grow(): void
    {
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->withDefaultLocation()
            ->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create();
        $location = SchoolLocation::factory()->active()->create();
        SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->active()
            ->create();

        $baselineQueries = $this->publicReadQueryCount();

        foreach (range(1, 3) as $index) {
            $extraLevel = SchoolLevel::factory()
                ->for($program, 'program')
                ->active()
                ->publiclyVisible()
                ->create(['sort_order' => $index]);
            $extraLocation = SchoolLocation::factory()->active()->create();

            foreach (range(1, 3) as $scheduleIndex) {
                SchoolSchedule::factory()
                    ->for($extraLevel, 'level')
                    ->for($extraLocation, 'location')
                    ->active()
                    ->onDay($scheduleIndex)
                    ->between(
                        sprintf('%02d:00', 8 + $scheduleIndex),
                        sprintf('%02d:00', 9 + $scheduleIndex)
                    )
                    ->create();
            }
        }

        $expandedQueries = $this->publicReadQueryCount();

        $this->assertSame($baselineQueries, $expandedQueries);
        $this->assertLessThanOrEqual(5, $expandedQueries);
    }

    private function publicReadQueryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->getJson('/api/v1/school')->assertOk();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function recursiveKeys(array $payload): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            if (is_array($value)) {
                $keys = [...$keys, ...$this->recursiveKeys($value)];
            }
        }

        return $keys;
    }
}
