<?php

namespace App\Services;

use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Mail;

class ContactRequestNotificationService
{
    public function notify(ContactRequest $contactRequest): void
    {
        if (! config('contact.notification.enabled')) {
            return;
        }

        $recipient = trim((string) config('contact.notification.to'));

        if ($recipient === '') {
            return;
        }

        Mail::to($recipient)->send(
            new ContactRequestReceived($contactRequest)
        );
    }
}
