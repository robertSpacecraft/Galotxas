<?php

namespace Database\Factories;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'subject' => fake()->sentence(5),
            'message' => fake()->paragraphs(2, true),
            'status' => ContactRequestStatus::NEW->value,
            'consent_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }

    public function newRequest(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequestStatus::NEW->value,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequestStatus::READ->value,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ContactRequestStatus::CLOSED->value,
        ]);
    }
}
