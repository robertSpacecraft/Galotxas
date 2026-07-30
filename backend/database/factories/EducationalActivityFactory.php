<?php

namespace Database\Factories;

use App\Enums\EducationalActivityStatus;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationalActivity>
 */
class EducationalActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'educational_center_id' => EducationalCenter::factory()->active(),
            'school_location_id' => null,
            'name' => 'Jornada de galotxas '.$this->faker->unique()->numerify('###'),
            'activity_date' => $this->faker->dateTimeBetween('tomorrow', '+3 months'),
            'starts_at' => null,
            'ends_at' => null,
            'expected_students' => null,
            'status' => EducationalActivityStatus::PLANNED,
            'admin_notes' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => [
            'status' => EducationalActivityStatus::PLANNED,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => EducationalActivityStatus::COMPLETED,
            'expected_students' => $this->faker->numberBetween(1, 200),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => EducationalActivityStatus::CANCELLED,
        ]);
    }

    public function withSchedule(
        string $startsAt = '09:00',
        string $endsAt = '12:00'
    ): static {
        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function withoutSchedule(): static
    {
        return $this->state(fn () => [
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function withLocation(?SchoolLocation $location = null): static
    {
        return $this->state(fn () => [
            'school_location_id' => $location?->id
                ?? SchoolLocation::factory()->active(),
        ]);
    }

    public function withoutLocation(): static
    {
        return $this->state(fn () => [
            'school_location_id' => null,
        ]);
    }

    public function withExpectedStudents(int $expectedStudents = 25): static
    {
        return $this->state(fn () => [
            'expected_students' => $expectedStudents,
        ]);
    }
}
