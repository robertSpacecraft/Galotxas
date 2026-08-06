<?php

namespace App\Services;

use App\Enums\PublicIdentityAuthorizationEventType;
use App\Mail\GuardianPublicIdentityConfirmation;
use App\Models\PublicIdentityAuthorization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PublicIdentityAuthorizationNotificationService
{
    public function __construct(
        private readonly PublicIdentityAuthorizationEventService $eventService
    ) {}

    public function send(
        PublicIdentityAuthorization $authorization,
        string $plainToken
    ): bool {
        if (! config('public_identity.notification_enabled')) {
            return false;
        }

        try {
            Mail::to($authorization->guardian_email)->send(
                new GuardianPublicIdentityConfirmation($authorization, $plainToken)
            );
            $this->eventService->record(
                $authorization,
                PublicIdentityAuthorizationEventType::NOTIFICATION_SENT
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar la confirmación de identidad pública.', [
                'authorization_id' => $authorization->id,
                'exception' => $exception::class,
            ]);
            $this->eventService->record(
                $authorization,
                PublicIdentityAuthorizationEventType::NOTIFICATION_FAILED,
                metadata: ['error_type' => $exception::class]
            );

            return false;
        }
    }
}
