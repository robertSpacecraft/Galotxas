<?php

namespace App\Services;

use App\Enums\ContactRequestEventType;
use App\Models\ContactRequest;
use App\Models\ContactRequestEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

class ContactRequestEventService
{
    /** @param array<string, bool|int|string|null>|null $metadata */
    public function record(
        ContactRequest $contactRequest,
        ContactRequestEventType $type,
        ?User $actor = null,
        ?array $metadata = null
    ): ContactRequestEvent {
        return $contactRequest->events()->create([
            'type' => $type,
            'actor_user_id' => $actor?->id,
            'occurred_at' => CarbonImmutable::now(),
            'metadata' => $metadata,
        ]);
    }
}
