<?php

namespace App\Services;

use App\Enums\PublicIdentityAuthorizationEventType;
use App\Models\PublicIdentityAuthorization;
use App\Models\PublicIdentityAuthorizationEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

class PublicIdentityAuthorizationEventService
{
    /** @param array<string, bool|int|string|null>|null $metadata */
    public function record(
        PublicIdentityAuthorization $authorization,
        PublicIdentityAuthorizationEventType $type,
        ?User $actor = null,
        ?array $metadata = null
    ): PublicIdentityAuthorizationEvent {
        return $authorization->events()->create([
            'type' => $type,
            'actor_user_id' => $actor?->id,
            'occurred_at' => CarbonImmutable::now(),
            'metadata' => $metadata,
        ]);
    }
}
