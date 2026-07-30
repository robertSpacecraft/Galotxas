<?php

namespace Database\Factories;

use App\Enums\SchoolDayOfWeek;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolSchedule>
 */
class SchoolScheduleFactory extends Factory
{
    public function definition(): array
    {
        $startHour = $this->faker->numberBetween(9, 19);

        return [
            'school_level_id' => SchoolLevel::factory()->active(),
            'school_location_id' => SchoolLocation::factory()->active(),
            'day_of_week' => $this->faker->numberBetween(1, 7),
            'starts_at' => sprintf('%02d:00', $startHour),
            'ends_at' => sprintf('%02d:00', $startHour + 1),
            'is_active' => false,
            'sort_order' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function onDay(SchoolDayOfWeek|int $day): static
    {
        return $this->state(fn () => [
            'day_of_week' => $day instanceof SchoolDayOfWeek ? $day->value : $day,
        ]);
    }

    public function between(string $startsAt, string $endsAt): static
    {
        return $this->state(fn () => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
