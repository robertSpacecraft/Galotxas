<?php

namespace Database\Factories;

use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use App\Models\PublicIdentityAuthorization;
use App\Models\SchoolEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicIdentityAuthorization> */
class PublicIdentityAuthorizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_enrollment_id' => SchoolEnrollment::factory()->minor(),
            'player_id' => null,
            'scope' => PublicIdentityAuthorization::SCOPE,
            'mode' => PublicIdentityAuthorizationMode::ALIAS,
            'state' => PublicIdentityAuthorizationState::PENDING,
            'approval_slot' => null,
            'guardian_email' => fake()->unique()->safeEmail(),
            'guardian_name' => fake()->name(),
            'guardian_relationship' => 'Madre, padre o tutor legal',
            'guardian_authority_declared_at' => CarbonImmutable::now(),
            'notice_id' => 'NOTICE-PUBLIC-IDENTITY-MINORS',
            'notice_version' => '1.0.0',
            'requested_at' => CarbonImmutable::now(),
            'guardian_confirmed_at' => null,
            'guardian_denied_at' => null,
            'minor_assent_recorded_at' => null,
            'minor_assent_recorded_by' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'approved_at' => null,
            'denied_at' => null,
            'revoked_at' => null,
            'revoked_by' => null,
            'private_reason' => null,
            'expires_at' => null,
            'confirmation_token_hash' => null,
            'confirmation_token_expires_at' => null,
            'confirmation_token_used_at' => null,
        ];
    }

    public function mode(PublicIdentityAuthorizationMode $mode): static
    {
        return $this->state(fn () => ['mode' => $mode]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'state' => PublicIdentityAuthorizationState::APPROVED,
            'approval_slot' => 1,
            'guardian_confirmed_at' => CarbonImmutable::now(),
            'reviewed_at' => CarbonImmutable::now(),
            'reviewed_by' => User::factory()->admin(),
            'approved_at' => CarbonImmutable::now(),
            'confirmation_token_hash' => null,
            'confirmation_token_expires_at' => null,
            'confirmation_token_used_at' => CarbonImmutable::now(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn () => [
            'state' => PublicIdentityAuthorizationState::DENIED,
            'approval_slot' => null,
            'denied_at' => CarbonImmutable::now(),
        ]);
    }

    public function revoked(): static
    {
        return $this->approved()->state(fn () => [
            'state' => PublicIdentityAuthorizationState::REVOKED,
            'approval_slot' => null,
            'revoked_at' => CarbonImmutable::now(),
        ]);
    }
}
