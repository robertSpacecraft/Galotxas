<?php

namespace Database\Factories;

use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Models\SchoolSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolProgram>
 */
class SchoolProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Programa '.$this->faker->unique()->numerify('###'),
            'public_description' => null,
            'enrollment_information' => null,
            'is_public' => false,
            'enrollments_open' => false,
            'default_school_location_id' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'sort_order' => 0,
        ];
    }

    public function publiclyVisible(): static
    {
        return $this->state(fn () => [
            'is_public' => true,
        ]);
    }

    public function privatelyVisible(): static
    {
        return $this->state(fn () => [
            'is_public' => false,
        ]);
    }

    public function enrollmentsOpen(): static
    {
        return $this->state(fn () => [
            'enrollments_open' => true,
        ]);
    }

    public function enrollmentsClosed(): static
    {
        return $this->state(fn () => [
            'enrollments_open' => false,
        ]);
    }

    public function withDefaultLocation(): static
    {
        return $this->for(SchoolLocation::factory()->active(), 'defaultLocation');
    }

    public function withOperationalContent(): static
    {
        return $this->state(fn () => [
            'public_description' => 'Presentación pública ficticia para pruebas.',
            'enrollment_information' => 'Proceso de inscripción ficticio para pruebas.',
            'contact_email' => 'school-operations@example.test',
        ]);
    }

    public function operationallyReady(): static
    {
        return $this
            ->publiclyVisible()
            ->withOperationalContent()
            ->afterCreating(function (SchoolProgram $program): void {
                $location = $program->defaultLocation;
                if ($location === null || ! $location->is_active) {
                    $location = SchoolLocation::factory()->active()->create();
                    $program->forceFill([
                        'default_school_location_id' => $location->id,
                    ])->save();
                }

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
            });
    }

    public function withoutDefaultLocation(): static
    {
        return $this->state(fn () => [
            'default_school_location_id' => null,
        ]);
    }
}
