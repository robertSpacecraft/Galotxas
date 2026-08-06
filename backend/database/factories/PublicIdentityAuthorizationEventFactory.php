<?php

namespace Database\Factories;

use App\Enums\PublicIdentityAuthorizationEventType;
use App\Models\PublicIdentityAuthorization;
use App\Models\PublicIdentityAuthorizationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublicIdentityAuthorizationEvent> */
class PublicIdentityAuthorizationEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'public_identity_authorization_id' => PublicIdentityAuthorization::factory(),
            'type' => PublicIdentityAuthorizationEventType::REQUESTED,
            'actor_user_id' => null,
            'occurred_at' => CarbonImmutable::now(),
            'metadata' => null,
        ];
    }
}
