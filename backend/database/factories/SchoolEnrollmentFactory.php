<?php

namespace Database\Factories;

use App\Enums\SchoolEnrollmentStatus;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolEnrollment>
 */
class SchoolEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_program_id' => SchoolProgram::factory(),
            'school_level_id' => null,
            'user_id' => null,
            'participant_name' => fake()->name(),
            'participant_birth_date' => CarbonImmutable::now()->subYears(25)->toDateString(),
            'contact_phone' => fake()->numerify('6########'),
            'contact_email' => fake()->unique()->safeEmail(),
            'guardian_name' => null,
            'guardian_relationship' => null,
            'status' => SchoolEnrollmentStatus::PENDING->value,
            'requested_at' => CarbonImmutable::now(),
            'activated_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
            'admin_notes' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => SchoolEnrollmentStatus::PENDING->value,
            'activated_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this
            ->assignedToLevel()
            ->state(fn () => [
                'status' => SchoolEnrollmentStatus::ACTIVE->value,
                'activated_at' => CarbonImmutable::now(),
                'rejected_at' => null,
                'withdrawn_at' => null,
            ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => SchoolEnrollmentStatus::REJECTED->value,
            'activated_at' => null,
            'rejected_at' => CarbonImmutable::now(),
            'withdrawn_at' => null,
        ]);
    }

    public function withdrawn(): static
    {
        return $this
            ->assignedToLevel()
            ->state(function () {
                $activatedAt = CarbonImmutable::now()->subMonth();

                return [
                    'status' => SchoolEnrollmentStatus::WITHDRAWN->value,
                    'activated_at' => $activatedAt,
                    'rejected_at' => null,
                    'withdrawn_at' => $activatedAt->addMonth(),
                ];
            });
    }

    public function minor(): static
    {
        return $this->state(fn () => [
            'participant_birth_date' => CarbonImmutable::now()->subYears(12)->toDateString(),
            'guardian_name' => fake()->name(),
            'guardian_relationship' => 'Madre, padre o tutor legal',
        ]);
    }

    public function adult(): static
    {
        return $this->state(fn () => [
            'participant_birth_date' => CarbonImmutable::now()->subYears(25)->toDateString(),
            'guardian_name' => null,
            'guardian_relationship' => null,
        ]);
    }

    public function withGuardian(): static
    {
        return $this->state(fn () => [
            'guardian_name' => fake()->name(),
            'guardian_relationship' => 'Tutor legal',
        ]);
    }

    public function linkedToUser(?User $user = null): static
    {
        return $this->for($user ?? User::factory(), 'user');
    }

    public function assignedToLevel(?SchoolLevel $level = null): static
    {
        if ($level !== null) {
            return $this
                ->for($level->program, 'program')
                ->for($level, 'level');
        }

        return $this->afterMaking(function (SchoolEnrollment $enrollment): void {
            if ($enrollment->school_level_id !== null) {
                return;
            }

            $level = SchoolLevel::factory()
                ->for(
                    SchoolProgram::query()->findOrFail($enrollment->school_program_id),
                    'program'
                )
                ->active()
                ->create();

            $enrollment->school_level_id = $level->id;
        });
    }
}
