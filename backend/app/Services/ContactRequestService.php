<?php

namespace App\Services;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactRequestService
{
    public function __construct(
        private readonly ContactRequestFingerprintService $fingerprints,
        private readonly ContactRequestNotificationService $notifications
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, ?string $ip): ContactRequest
    {
        $contactRequest = new ContactRequest;
        $contactRequest->forceFill([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'subject' => $attributes['subject'],
            'message' => $attributes['message'],
            'status' => ContactRequestStatus::NEW,
            'consent_at' => now(),
            'ip_hash' => $this->fingerprints->ipHash($ip),
        ]);
        $contactRequest->save();

        try {
            $this->notifications->notify($contactRequest);
        } catch (Throwable $exception) {
            Log::error('No se pudo notificar una solicitud de contacto persistida.', [
                'contact_request_id' => $contactRequest->id,
                'exception' => $exception::class,
            ]);
        }

        return $contactRequest->refresh();
    }

    public function markAsRead(ContactRequest $contactRequest): ContactRequest
    {
        if ($contactRequest->status !== ContactRequestStatus::NEW) {
            return $contactRequest;
        }

        $contactRequest->forceFill([
            'status' => ContactRequestStatus::READ,
        ])->save();

        return $contactRequest->refresh();
    }

    public function close(ContactRequest $contactRequest): ContactRequest
    {
        if ($contactRequest->status === ContactRequestStatus::CLOSED) {
            return $contactRequest;
        }

        $contactRequest->forceFill([
            'status' => ContactRequestStatus::CLOSED,
        ])->save();

        return $contactRequest->refresh();
    }
}
