<?php

namespace App\Services;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestEventType;
use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactRequestService
{
    public function __construct(
        private readonly ContactRequestFingerprintService $fingerprints,
        private readonly ContactRequestNotificationService $notifications,
        private readonly ContactRequestEventService $events
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?string $ip): ContactRequest
    {
        $now = CarbonImmutable::now();
        $notificationStatus = $this->notifications->isReady()
            ? ContactNotificationStatus::PENDING
            : ContactNotificationStatus::DISABLED;

        $contactRequest = DB::transaction(function () use (
            $attributes,
            $ip,
            $now,
            $notificationStatus
        ): ContactRequest {
            $contactRequest = new ContactRequest;
            $contactRequest->forceFill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'subject' => $attributes['subject'],
                'message' => $attributes['message'],
                'status' => ContactRequestStatus::NEW,
                'consent_at' => $now,
                'privacy_notice_id' => $attributes['privacy_notice_id'],
                'privacy_notice_version' => $attributes['privacy_notice_version'],
                'ip_hash' => $this->fingerprints->ipHash($ip),
                'ip_hash_expires_at' => $now->addDays(
                    max(1, (int) config('contact.abuse_hash_retention_days', 30))
                ),
                'notification_status' => $notificationStatus,
            ]);
            $contactRequest->save();
            $this->events->record($contactRequest, ContactRequestEventType::RECEIVED);

            return $contactRequest;
        });

        if ($notificationStatus === ContactNotificationStatus::PENDING) {
            $this->attemptNotification($contactRequest);
        } else {
            $this->events->record(
                $contactRequest,
                ContactRequestEventType::NOTIFICATION_DISABLED,
                metadata: ['reason' => 'configuration']
            );
        }

        return $contactRequest->refresh();
    }

    public function retryNotification(ContactRequest $contactRequest, User $actor): ContactRequest
    {
        if ($contactRequest->isAnonymized()) {
            throw ValidationException::withMessages([
                'notification' => 'No se puede notificar una solicitud anonimizada.',
            ]);
        }

        if (! in_array($contactRequest->notification_status, [
            ContactNotificationStatus::FAILED,
            ContactNotificationStatus::DISABLED,
        ], true)) {
            throw ValidationException::withMessages([
                'notification' => 'Esta notificación no admite reintento en su estado actual.',
            ]);
        }

        if (! $this->notifications->isReady()) {
            throw ValidationException::withMessages([
                'notification' => 'La configuración de correo no está preparada para reintentar.',
            ]);
        }

        $this->ensureNotificationAttemptAvailable($contactRequest);
        $this->events->record(
            $contactRequest,
            ContactRequestEventType::NOTIFICATION_RETRIED,
            $actor,
            ['next_attempt' => $contactRequest->notification_attempt_count + 1]
        );
        $this->attemptNotification($contactRequest, $actor);

        return $contactRequest->refresh();
    }

    public function markAsRead(ContactRequest $contactRequest, ?User $actor = null): ContactRequest
    {
        if ($contactRequest->status !== ContactRequestStatus::NEW) {
            return $contactRequest;
        }

        return DB::transaction(function () use ($contactRequest, $actor): ContactRequest {
            $contactRequest->forceFill(['status' => ContactRequestStatus::READ])->save();
            $this->events->record($contactRequest, ContactRequestEventType::MARKED_AS_READ, $actor);

            return $contactRequest->refresh();
        });
    }

    public function close(ContactRequest $contactRequest, ?User $actor = null): ContactRequest
    {
        if ($contactRequest->status === ContactRequestStatus::CLOSED) {
            return $contactRequest;
        }

        return DB::transaction(function () use ($contactRequest, $actor): ContactRequest {
            $closedAt = CarbonImmutable::now();
            $contactRequest->forceFill([
                'status' => ContactRequestStatus::CLOSED,
                'closed_at' => $closedAt,
                'retention_until' => $closedAt->addMonthsNoOverflow(
                    max(1, (int) config('contact.retention_months', 12))
                ),
            ])->save();
            $this->events->record($contactRequest, ContactRequestEventType::CLOSED, $actor);

            return $contactRequest->refresh();
        });
    }

    public function placeRetentionHold(
        ContactRequest $contactRequest,
        User $actor,
        string $reason
    ): ContactRequest {
        if ($contactRequest->status !== ContactRequestStatus::CLOSED || $contactRequest->isAnonymized()) {
            throw ValidationException::withMessages([
                'retention_hold_reason' => 'Sólo puede suspenderse la eliminación de una solicitud cerrada.',
            ]);
        }

        if ($contactRequest->retention_hold) {
            throw ValidationException::withMessages([
                'retention_hold_reason' => 'La solicitud ya tiene una suspensión activa.',
            ]);
        }

        return DB::transaction(function () use ($contactRequest, $actor, $reason): ContactRequest {
            $contactRequest->forceFill([
                'retention_hold' => true,
                'retention_hold_reason' => $reason,
                'retention_hold_placed_at' => CarbonImmutable::now(),
                'retention_hold_placed_by' => $actor->id,
                'retention_hold_released_at' => null,
                'retention_hold_released_by' => null,
            ])->save();
            $this->events->record(
                $contactRequest,
                ContactRequestEventType::RETENTION_HOLD_PLACED,
                $actor
            );

            return $contactRequest->refresh();
        });
    }

    public function releaseRetentionHold(ContactRequest $contactRequest, User $actor): ContactRequest
    {
        if (! $contactRequest->retention_hold) {
            throw ValidationException::withMessages([
                'retention_hold' => 'La solicitud no tiene una suspensión activa.',
            ]);
        }

        return DB::transaction(function () use ($contactRequest, $actor): ContactRequest {
            $contactRequest->forceFill([
                'retention_hold' => false,
                'retention_hold_released_at' => CarbonImmutable::now(),
                'retention_hold_released_by' => $actor->id,
            ])->save();
            $this->events->record(
                $contactRequest,
                ContactRequestEventType::RETENTION_HOLD_RELEASED,
                $actor
            );

            return $contactRequest->refresh();
        });
    }

    public function anonymize(ContactRequest $contactRequest, ?User $actor = null): ContactRequest
    {
        if (! $this->canAnonymize($contactRequest)) {
            throw ValidationException::withMessages([
                'anonymize' => 'La solicitud no cumple las condiciones de anonimización.',
            ]);
        }

        return DB::transaction(function () use ($contactRequest, $actor): ContactRequest {
            $contactRequest->forceFill([
                'name' => null,
                'email' => null,
                'subject' => null,
                'message' => null,
                'ip_hash' => null,
                'ip_hash_expires_at' => null,
                'notification_failure_code' => null,
                'anonymized_at' => CarbonImmutable::now(),
            ])->save();
            $this->events->record($contactRequest, ContactRequestEventType::ANONYMIZED, $actor);

            return $contactRequest->refresh();
        });
    }

    public function canAnonymize(ContactRequest $contactRequest): bool
    {
        return $contactRequest->status === ContactRequestStatus::CLOSED
            && $contactRequest->retention_until?->lessThanOrEqualTo(CarbonImmutable::now())
            && ! $contactRequest->retention_hold
            && ! $contactRequest->isAnonymized();
    }

    private function attemptNotification(ContactRequest $contactRequest, ?User $actor = null): bool
    {
        $this->ensureNotificationAttemptAvailable($contactRequest);

        $attemptedAt = CarbonImmutable::now();
        $contactRequest->forceFill([
            'notification_status' => ContactNotificationStatus::PENDING,
            'notification_attempt_count' => $contactRequest->notification_attempt_count + 1,
            'notification_attempted_at' => $attemptedAt,
            'notification_failure_code' => null,
        ])->save();

        try {
            $this->notifications->send($contactRequest);
            $contactRequest->forceFill([
                'notification_status' => ContactNotificationStatus::SENT,
                'notification_sent_at' => CarbonImmutable::now(),
                'notification_failed_at' => null,
            ])->save();
            $this->events->record(
                $contactRequest,
                ContactRequestEventType::NOTIFICATION_SENT,
                $actor,
                ['attempt' => $contactRequest->notification_attempt_count]
            );

            return true;
        } catch (Throwable $exception) {
            $code = preg_replace('/[^A-Za-z0-9_\\-]/', '', class_basename($exception)) ?: 'DeliveryError';
            $contactRequest->forceFill([
                'notification_status' => ContactNotificationStatus::FAILED,
                'notification_failed_at' => CarbonImmutable::now(),
                'notification_failure_code' => mb_substr($code, 0, 80),
            ])->save();
            $this->events->record(
                $contactRequest,
                ContactRequestEventType::NOTIFICATION_FAILED,
                $actor,
                [
                    'attempt' => $contactRequest->notification_attempt_count,
                    'failure_code' => $contactRequest->notification_failure_code,
                ]
            );
            Log::error('No se pudo notificar una solicitud de contacto persistida.', [
                'contact_request_id' => $contactRequest->id,
                'failure_code' => $contactRequest->notification_failure_code,
            ]);

            return false;
        }
    }

    private function ensureNotificationAttemptAvailable(ContactRequest $contactRequest): void
    {
        $maxAttempts = max(1, (int) config('contact.notification.max_attempts', 3));
        if ($contactRequest->notification_attempt_count >= $maxAttempts) {
            throw ValidationException::withMessages([
                'notification' => 'Se ha alcanzado el límite de intentos de notificación.',
            ]);
        }
    }
}
